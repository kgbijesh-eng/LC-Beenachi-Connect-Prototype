<?php

declare(strict_types=1);

namespace App\Services\Announcements;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubAnnouncementPublicationDecision;
use App\Models\ClubMembership;
use App\Models\User;
use App\Services\Access\OrganisationTransactionGuard;
use App\Support\Announcements\ClubAnnouncementFingerprint;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ClubAnnouncementWriteService
{
    private const FIELDS = ['title', 'body', 'priority', 'audience', 'scheduled_publish_at', 'expires_at'];

    private const ACTIONS = [
        'created' => 'announcements.created',
        'updated' => 'announcements.updated',
        'replacement' => 'announcements.replacement_draft_created',
        'approved' => 'announcements.approval_granted',
        'rejected' => 'announcements.approval_rejected',
        'revoked' => 'announcements.approval_revoked',
        'published' => 'announcements.published',
        'withdrawn' => 'announcements.withdrawn',
        'archived' => 'announcements.archived',
    ];

    public function __construct(
        private readonly ClubAnnouncementFingerprint $fingerprints,
        private readonly OrganisationTransactionGuard $transactions,
    ) {}

    public function createDraft(Club $club, User $actor, array $attributes): ClubAnnouncement
    {
        return $this->transactions->run($club, function () use ($club, $actor, $attributes): ClubAnnouncement {
            [$role] = $this->lockAndAuthorize($club, $actor, 'announcements.drafts.manage');
            $state = $this->canonicalAttributes($attributes, true);
            $announcement = ClubAnnouncement::query()->create($state + [
                'club_id' => $club->id,
                'status' => ClubAnnouncement::STATUS_DRAFT,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
                'lock_version' => 1,
            ]);
            $this->audit($announcement, $actor, self::ACTIONS['created'], [
                'acting_role' => $role,
                'new_lock_version' => 1,
            ]);

            return $announcement->fresh(['club']);
        });
    }

    public function updateDraft(Club $club, ClubAnnouncement $announcement, User $actor, int $lockVersion, array $attributes): ClubAnnouncement
    {
        return $this->transactions->run($club, function () use ($club, $announcement, $actor, $lockVersion, $attributes): ClubAnnouncement {
            $locked = $this->lockOne($club, (int) $announcement->getKey());
            [$role] = $this->lockAndAuthorize($club, $actor, 'announcements.drafts.manage');
            $this->assertVersion($locked, $lockVersion);
            $this->requireStatus($locked, [ClubAnnouncement::STATUS_DRAFT], 'Only Draft announcements can be edited.');
            $proposed = $this->canonicalAttributes($attributes, false, $this->state($locked));
            $before = $this->state($locked);
            if ($proposed === $before) {
                throw ValidationException::withMessages(['announcement' => 'At least one material announcement field must change.']);
            }
            $changed = array_keys(array_filter($proposed, fn (mixed $value, string $key): bool => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));
            $beforeFingerprint = $this->fingerprints->fingerprint($locked);
            $oldVersion = (int) $locked->lock_version;
            $locked->fill($proposed + ['updated_by_user_id' => $actor->id, 'lock_version' => $oldVersion + 1])->save();
            $after = $locked->fresh();
            $this->audit($after, $actor, self::ACTIONS['updated'], [
                'acting_role' => $role,
                'changed_fields' => $changed,
                'prior_lock_version' => $oldVersion,
                'new_lock_version' => $oldVersion + 1,
                'before_fingerprint' => $beforeFingerprint,
                'after_fingerprint' => $this->fingerprints->fingerprint($after),
            ]);

            return $after->load('club');
        });
    }

    public function createReplacementDraft(Club $club, ClubAnnouncement $source, User $actor, array $attributes): ClubAnnouncement
    {
        return $this->transactions->run($club, function () use ($club, $source, $actor, $attributes): ClubAnnouncement {
            $rows = $this->lockClubGraph($club, (int) $source->getKey());
            $locked = $rows->firstWhere('id', (int) $source->getKey());
            [$role] = $this->lockAndAuthorize($club, $actor, 'announcements.replace');
            $this->requireStatus($locked, [ClubAnnouncement::STATUS_PUBLISHED, ClubAnnouncement::STATUS_WITHDRAWN], 'Only Published or Withdrawn announcements can be replaced.');
            $this->assertValidGraph($rows);
            $direct = $rows->where('replaces_announcement_id', $locked->id);
            if ($direct->contains('status', ClubAnnouncement::STATUS_PUBLISHED)) {
                throw new ConflictHttpException('The announcement already has a Published replacement.');
            }
            if ($direct->contains(fn (ClubAnnouncement $item): bool => $item->status !== ClubAnnouncement::STATUS_ARCHIVED)) {
                throw new ConflictHttpException('Archive the existing replacement Draft before creating another.');
            }
            if (array_key_exists('audience', $attributes)) {
                throw ValidationException::withMessages(['audience' => 'Replacement audience is inherited and cannot be supplied.']);
            }
            $state = $this->canonicalAttributes($attributes + ['audience' => $locked->audience], true);
            $replacement = ClubAnnouncement::query()->create($state + [
                'club_id' => $club->id,
                'status' => ClubAnnouncement::STATUS_DRAFT,
                'replaces_announcement_id' => $locked->id,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
                'lock_version' => 1,
            ]);
            $this->audit($replacement, $actor, self::ACTIONS['replacement'], [
                'acting_role' => $role,
                'replacement_source_id' => $locked->id,
                'new_lock_version' => 1,
            ]);

            return $replacement->fresh(['club', 'replacedAnnouncement']);
        });
    }

    public function approve(Club $club, ClubAnnouncement $announcement, User $actor, int $lockVersion, ?string $reason = null): ClubAnnouncementPublicationDecision
    {
        return $this->decide($club, $announcement, $actor, $lockVersion, ClubAnnouncementPublicationDecision::DECISION_APPROVED, $reason);
    }

    public function rejectApproval(Club $club, ClubAnnouncement $announcement, User $actor, int $lockVersion, string $reason): ClubAnnouncementPublicationDecision
    {
        return $this->decide($club, $announcement, $actor, $lockVersion, ClubAnnouncementPublicationDecision::DECISION_REJECTED, $reason);
    }

    public function revokeApproval(Club $club, ClubAnnouncement $announcement, User $actor, int $lockVersion, ClubAnnouncementPublicationDecision $approval, string $reason): ClubAnnouncementPublicationDecision
    {
        return $this->transactions->run($club, function () use ($club, $announcement, $actor, $lockVersion, $approval, $reason): ClubAnnouncementPublicationDecision {
            $locked = $this->lockOne($club, (int) $announcement->getKey());
            $this->assertVersion($locked, $lockVersion);
            $this->requireStatus($locked, [ClubAnnouncement::STATUS_DRAFT], 'Only an unchanged Draft approval can be revoked.');
            $maker = User::query()->find($locked->updated_by_user_id);
            if (! $maker) {
                abort(403, 'The approval maker can no longer be classified.');
            }
            [$memberships] = $this->lockDecisionMemberships($club, $maker, $actor);
            $makerRole = $this->qualifyingRole($memberships[(int) $maker->id]);
            $checkerRole = $this->authorizeMembership($memberships[(int) $actor->id], 'announcements.approve');
            $lockedApproval = ClubAnnouncementPublicationDecision::query()->where('club_id', $club->id)
                ->where('club_announcement_id', $locked->id)->whereKey($approval->id)->lockForUpdate()->first();
            if (! $lockedApproval) {
                abort(404, 'Announcement publication approval not found for this club.');
            }
            $this->requireReason($reason, 'reason');
            $fingerprint = $this->fingerprints->fingerprint($locked);
            if ($lockedApproval->decision !== ClubAnnouncementPublicationDecision::DECISION_APPROVED
                || (int) $lockedApproval->decided_by_user_id !== (int) $actor->id
                || (int) $lockedApproval->maker_user_id !== (int) $maker->id
                || $lockedApproval->maker_role !== $makerRole
                || (int) $lockedApproval->announcement_lock_version !== (int) $locked->lock_version
                || $lockedApproval->content_fingerprint !== $fingerprint) {
                throw new ConflictHttpException('The approval is not revocable for the current Draft.');
            }
            $this->lockDecisions($locked);
            if ($lockedApproval->revocation()
                ->where('club_id', $club->id)
                ->where('club_announcement_id', $locked->id)
                ->exists()) {
                throw new ConflictHttpException('The approval has already been revoked.');
            }
            $decision = ClubAnnouncementPublicationDecision::query()->create([
                'club_id' => $club->id,
                'club_announcement_id' => $locked->id,
                'announcement_lock_version' => $locked->lock_version,
                'content_fingerprint' => $fingerprint,
                'maker_user_id' => $lockedApproval->maker_user_id,
                'maker_role' => $lockedApproval->maker_role,
                'decision' => ClubAnnouncementPublicationDecision::DECISION_REVOKED,
                'decided_by_user_id' => $actor->id,
                'decided_by_role' => $checkerRole,
                'supersedes_decision_id' => $lockedApproval->id,
                'reason' => $reason,
                'decided_at' => Carbon::now('UTC')->startOfSecond(),
            ]);
            $this->audit($locked, $actor, self::ACTIONS['revoked'], ['acting_role' => $checkerRole, 'decision_id' => $decision->id]);

            return $decision->fresh();
        });
    }

    public function publish(Club $club, ClubAnnouncement $announcement, User $actor, int $lockVersion): ClubAnnouncement
    {
        return $this->transactions->run($club, function () use ($club, $announcement, $actor, $lockVersion): ClubAnnouncement {
            $projection = ClubAnnouncement::query()->where('club_id', $club->id)->whereKey($announcement->getKey())->value('replaces_announcement_id');
            if (! ClubAnnouncement::query()->where('club_id', $club->id)->whereKey($announcement->getKey())->exists()) {
                abort(404, 'Announcement not found for this club.');
            }
            $rows = $projection === null ? null : $this->lockClubGraph($club, (int) $announcement->getKey());
            $locked = $rows?->firstWhere('id', (int) $announcement->getKey()) ?? $this->lockOne($club, (int) $announcement->getKey());
            [$role] = $this->lockAndAuthorize($club, $actor, 'announcements.publish');
            $this->assertVersion($locked, $lockVersion);
            $this->requireStatus($locked, [ClubAnnouncement::STATUS_DRAFT], 'Only Draft announcements can be published.');
            if ($rows !== null) {
                $this->assertReplacementPublishable($club, $locked, $rows);
            }
            $this->lockDecisions($locked);
            $this->assertPublicationDecision($locked);
            $publishedAt = Carbon::now('UTC')->startOfSecond();
            if ($locked->expires_at !== null && ! $locked->expires_at->greaterThan($publishedAt)) {
                throw new ConflictHttpException('Announcement expiry must be later than actual publication time.');
            }
            $oldVersion = (int) $locked->lock_version;
            $locked->fill([
                'status' => ClubAnnouncement::STATUS_PUBLISHED,
                'published_at' => $publishedAt,
                'published_by_user_id' => $actor->id,
                'published_by_role' => $role,
                'updated_by_user_id' => $actor->id,
                'lock_version' => $oldVersion + 1,
            ])->save();
            $this->audit($locked, $actor, self::ACTIONS['published'], [
                'acting_role' => $role, 'prior_lock_version' => $oldVersion, 'new_lock_version' => $oldVersion + 1,
                'replacement_source_id' => $locked->replaces_announcement_id,
            ]);

            return $locked->fresh(['club', 'replacedAnnouncement']);
        });
    }

    public function withdraw(Club $club, ClubAnnouncement $announcement, User $actor, int $lockVersion, string $reason): ClubAnnouncement
    {
        return $this->transactions->run($club, function () use ($club, $announcement, $actor, $lockVersion, $reason): ClubAnnouncement {
            $locked = $this->lockOne($club, (int) $announcement->getKey());
            [$role] = $this->lockAndAuthorize($club, $actor, 'announcements.withdraw');
            $this->assertVersion($locked, $lockVersion);
            $this->requireStatus($locked, [ClubAnnouncement::STATUS_PUBLISHED], 'Only Published announcements can be withdrawn.');
            $this->requireReason($reason, 'reason', 3, 1000);
            $oldVersion = (int) $locked->lock_version;
            $locked->fill([
                'status' => ClubAnnouncement::STATUS_WITHDRAWN,
                'withdrawn_at' => Carbon::now('UTC')->startOfSecond(),
                'withdrawal_reason' => $reason,
                'withdrawn_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
                'lock_version' => $oldVersion + 1,
            ])->save();
            $this->audit($locked, $actor, self::ACTIONS['withdrawn'], [
                'acting_role' => $role, 'prior_state' => 'published', 'new_state' => 'withdrawn',
                'prior_lock_version' => $oldVersion, 'new_lock_version' => $oldVersion + 1,
            ]);

            return $locked->fresh(['club']);
        });
    }

    public function archive(Club $club, ClubAnnouncement $announcement, User $actor, int $lockVersion): ClubAnnouncement
    {
        return $this->transactions->run($club, function () use ($club, $announcement, $actor, $lockVersion): ClubAnnouncement {
            $locked = $this->lockOne($club, (int) $announcement->getKey());
            [$role] = $this->lockAndAuthorize($club, $actor, 'announcements.archive');
            $this->assertVersion($locked, $lockVersion);
            $this->requireStatus($locked, [ClubAnnouncement::STATUS_DRAFT, ClubAnnouncement::STATUS_PUBLISHED, ClubAnnouncement::STATUS_WITHDRAWN], 'Only Draft, Published, or Withdrawn announcements can be archived.');
            $priorStored = $locked->status;
            $priorEffective = $locked->effectiveStatus(Carbon::now('UTC'));
            $oldVersion = (int) $locked->lock_version;
            $locked->fill([
                'status' => ClubAnnouncement::STATUS_ARCHIVED,
                'archived_at' => Carbon::now('UTC')->startOfSecond(),
                'archived_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
                'lock_version' => $oldVersion + 1,
            ])->save();
            $this->audit($locked, $actor, self::ACTIONS['archived'], [
                'acting_role' => $role, 'prior_stored_state' => $priorStored, 'prior_effective_state' => $priorEffective,
                'new_state' => 'archived', 'prior_lock_version' => $oldVersion, 'new_lock_version' => $oldVersion + 1,
            ]);

            return $locked->fresh(['club']);
        });
    }

    private function decide(Club $club, ClubAnnouncement $announcement, User $actor, int $lockVersion, string $decisionValue, ?string $reason): ClubAnnouncementPublicationDecision
    {
        return $this->transactions->run($club, function () use ($club, $announcement, $actor, $lockVersion, $decisionValue, $reason): ClubAnnouncementPublicationDecision {
            $locked = $this->lockOne($club, (int) $announcement->getKey());
            $this->assertVersion($locked, $lockVersion);
            $this->requireStatus($locked, [ClubAnnouncement::STATUS_DRAFT], 'Only Draft announcements can be adjudicated.');
            if (! $this->requiresChecker($locked)) {
                throw new ConflictHttpException('This routine announcement does not require checker adjudication.');
            }
            $makerId = (int) $locked->updated_by_user_id;
            if ($makerId === (int) $actor->id) {
                throw new ConflictHttpException('The maker and checker must be different users.');
            }
            [$memberships] = $this->lockDecisionMemberships($club, User::query()->findOrFail($makerId), $actor);
            $makerRole = $this->qualifyingRole($memberships[$makerId]);
            $checkerRole = $this->authorizeMembership($memberships[(int) $actor->id], 'announcements.approve');
            if ($decisionValue === ClubAnnouncementPublicationDecision::DECISION_APPROVED) {
                if ($reason !== null) {
                    $this->requireReason($reason, 'reason');
                }
            } else {
                $this->requireReason((string) $reason, 'reason');
            }
            $existing = $this->lockDecisions($locked);
            if ($existing->isNotEmpty()) {
                throw new ConflictHttpException('This announcement version has already been adjudicated.');
            }
            $fingerprint = $this->fingerprints->fingerprint($locked);
            $decision = ClubAnnouncementPublicationDecision::query()->create([
                'club_id' => $club->id,
                'club_announcement_id' => $locked->id,
                'announcement_lock_version' => $locked->lock_version,
                'content_fingerprint' => $fingerprint,
                'maker_user_id' => $makerId,
                'maker_role' => $makerRole,
                'decision' => $decisionValue,
                'decided_by_user_id' => $actor->id,
                'decided_by_role' => $checkerRole,
                'supersedes_decision_id' => null,
                'reason' => $reason,
                'decided_at' => Carbon::now('UTC')->startOfSecond(),
            ]);
            $action = $decisionValue === ClubAnnouncementPublicationDecision::DECISION_APPROVED ? self::ACTIONS['approved'] : self::ACTIONS['rejected'];
            $this->audit($locked, $actor, $action, ['acting_role' => $checkerRole, 'decision_id' => $decision->id]);

            return $decision->fresh();
        });
    }

    private function lockOne(Club $club, int $id): ClubAnnouncement
    {
        $record = ClubAnnouncement::query()->where('club_id', $club->id)->whereKey($id)->lockForUpdate()->first();
        if (! $record) {
            abort(404, 'Announcement not found for this club.');
        }

        return $record;
    }

    private function lockClubGraph(Club $club, int $targetId): Collection
    {
        $query = ClubAnnouncement::query()->where('club_id', $club->id);
        if (! (clone $query)->whereKey($targetId)->exists()) {
            abort(404, 'Announcement not found for this club.');
        }

        $rows = $query->orderBy('id')->lockForUpdate()->get();
        if (! $rows->contains('id', $targetId)) {
            abort(404, 'Announcement not found for this club.');
        }

        return $rows;
    }

    private function lockAndAuthorize(Club $club, User $actor, string $permission): array
    {
        $membership = ClubMembership::query()->with('role.permissions')->where('club_id', $club->id)
            ->where('user_id', $actor->id)->lockForUpdate()->first();
        if (! $membership) {
            abort(403, 'Active club authorization is required.');
        }

        return [$this->authorizeMembership($membership, $permission), $membership];
    }

    private function lockDecisionMemberships(Club $club, User $maker, User $checker): array
    {
        $memberships = ClubMembership::query()->with('role.permissions')->where('club_id', $club->id)
            ->whereIn('user_id', array_unique([(int) $maker->id, (int) $checker->id]))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('user_id');
        if (! $memberships->has((int) $maker->id) || ! $memberships->has((int) $checker->id)) {
            abort(403, 'Active maker and checker club authorization is required.');
        }

        return [$memberships];
    }

    private function authorizeMembership(ClubMembership $membership, string $permission): string
    {
        if ($membership->status !== 'active' || ! $membership->role || ! $membership->role->permissions->contains('slug', $permission)) {
            abort(403, 'Active club permission is required.');
        }

        return $this->qualifyingRole($membership);
    }

    private function qualifyingRole(ClubMembership $membership): string
    {
        if ($membership->status !== 'active' || ! $membership->role) {
            abort(403, 'An active qualifying club role is required.');
        }

        return match ($membership->role->slug) {
            'president-admin' => ClubAnnouncementPublicationDecision::ROLE_PRESIDENT,
            'secretary' => ClubAnnouncementPublicationDecision::ROLE_SECRETARY,
            default => abort(403, 'A qualifying announcement role is required.'),
        };
    }

    private function canonicalAttributes(array $attributes, bool $creating, array $base = []): array
    {
        $unknown = array_diff(array_keys($attributes), self::FIELDS);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['attributes' => 'Unsupported announcement fields: '.implode(', ', $unknown)]);
        }
        if (! $creating && $attributes === []) {
            throw ValidationException::withMessages(['attributes' => 'At least one announcement field is required.']);
        }
        foreach ($attributes as $field => $value) {
            if (in_array($field, ['scheduled_publish_at', 'expires_at'], true)) {
                if ($value !== null && ! is_string($value)) {
                    throw ValidationException::withMessages([$field => "The {$field} field must be an RFC 3339 timestamp or null."]);
                }
            } elseif (! is_string($value)) {
                throw ValidationException::withMessages([$field => "The {$field} field must be a string."]);
            }
        }
        if ($creating) {
            foreach (['title', 'body', 'priority', 'audience'] as $required) {
                if (! array_key_exists($required, $attributes)) {
                    throw ValidationException::withMessages([$required => "The {$required} field is required."]);
                }
            }
            $base = array_fill_keys(self::FIELDS, null);
        }
        $state = array_replace($base, $attributes);
        $state['title'] = $this->unicodeTrim((string) $state['title']);
        $state['body'] = str_replace(["\r\n", "\r"], "\n", (string) $state['body']);
        if ($state['title'] === '' || mb_strlen($state['title']) > 160) {
            throw ValidationException::withMessages(['title' => 'Title must contain 1 to 160 Unicode characters.']);
        }
        if ($this->unicodeTrim($state['body']) === '' || mb_strlen($state['body']) > 5000) {
            throw ValidationException::withMessages(['body' => 'Body must contain 1 to 5000 Unicode characters and cannot be blank.']);
        }
        if (! in_array($state['priority'], ClubAnnouncement::priorities(), true)) {
            throw ValidationException::withMessages(['priority' => 'Priority is invalid.']);
        }
        if (! in_array($state['audience'], ClubAnnouncement::audiences(), true)) {
            throw ValidationException::withMessages(['audience' => 'Audience is invalid.']);
        }
        foreach (['scheduled_publish_at', 'expires_at'] as $field) {
            if ($creating || array_key_exists($field, $attributes)) {
                $state[$field] = $this->timestamp($state[$field], $field);
            }
        }
        if ($state['scheduled_publish_at'] !== null && $state['expires_at'] !== null
            && ! Carbon::parse($state['expires_at'])->greaterThan(Carbon::parse($state['scheduled_publish_at']))) {
            throw ValidationException::withMessages(['expires_at' => 'Expiry must be strictly later than the scheduled publication metadata.']);
        }

        return $state;
    }

    private function timestamp(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d\TH:i:sP') !== $value) {
            throw ValidationException::withMessages([$field => "The {$field} field must be an exact RFC 3339 whole-second timestamp."]);
        }

        return Carbon::instance($parsed)->utc()->format('Y-m-d H:i:s');
    }

    private function state(ClubAnnouncement $announcement): array
    {
        return [
            'title' => $this->unicodeTrim((string) $announcement->title),
            'body' => str_replace(["\r\n", "\r"], "\n", (string) $announcement->body),
            'priority' => $announcement->priority,
            'audience' => $announcement->audience,
            'scheduled_publish_at' => $announcement->scheduled_publish_at?->utc()->format('Y-m-d H:i:s'),
            'expires_at' => $announcement->expires_at?->utc()->format('Y-m-d H:i:s'),
        ];
    }

    private function assertVersion(ClubAnnouncement $announcement, int $version): void
    {
        if ((int) $announcement->lock_version !== $version) {
            throw new ConflictHttpException('The announcement has changed. Refresh and try again.');
        }
    }

    private function requireStatus(ClubAnnouncement $announcement, array $statuses, string $message): void
    {
        if (! in_array($announcement->status, $statuses, true)) {
            throw new ConflictHttpException($message);
        }
    }

    private function requireReason(string $reason, string $field, int $min = 1, int $max = 1000): void
    {
        $length = mb_strlen($reason);
        if ($this->unicodeTrim($reason) === '' || $length < $min || $length > $max) {
            throw ValidationException::withMessages([$field => "The {$field} must be nonblank and contain {$min} to {$max} Unicode characters."]);
        }
    }

    private function requiresChecker(ClubAnnouncement $announcement): bool
    {
        return $announcement->priority === ClubAnnouncement::PRIORITY_URGENT
            || $announcement->audience === ClubAnnouncement::AUDIENCE_OFFICERS;
    }

    private function lockDecisions(ClubAnnouncement $announcement): Collection
    {
        return ClubAnnouncementPublicationDecision::query()->where('club_id', $announcement->club_id)
            ->where('club_announcement_id', $announcement->id)
            ->where('announcement_lock_version', $announcement->lock_version)->orderBy('id')->lockForUpdate()->get();
    }

    private function assertPublicationDecision(ClubAnnouncement $announcement): void
    {
        if (! $this->requiresChecker($announcement)) {
            return;
        }
        $fingerprint = $this->fingerprints->fingerprint($announcement);
        $decisions = ClubAnnouncementPublicationDecision::query()->where('club_id', $announcement->club_id)
            ->where('club_announcement_id', $announcement->id)->where('announcement_lock_version', $announcement->lock_version)
            ->orderBy('id')->get();
        $approval = $decisions->first(fn (ClubAnnouncementPublicationDecision $item): bool => $item->decision === ClubAnnouncementPublicationDecision::DECISION_APPROVED && $item->content_fingerprint === $fingerprint);
        if (! $approval || $decisions->contains(fn (ClubAnnouncementPublicationDecision $item): bool => $item->decision === ClubAnnouncementPublicationDecision::DECISION_REVOKED && (int) $item->supersedes_decision_id === (int) $approval->id)) {
            throw new ConflictHttpException('A current independent approval is required before publication.');
        }
    }

    private function assertReplacementPublishable(Club $club, ClubAnnouncement $replacement, Collection $rows): void
    {
        $this->assertValidGraph($rows);
        $source = $rows->firstWhere('id', (int) $replacement->replaces_announcement_id);
        if (! $source || (int) $source->club_id !== (int) $club->id
            || ! in_array($source->status, [ClubAnnouncement::STATUS_PUBLISHED, ClubAnnouncement::STATUS_WITHDRAWN], true)
            || $source->audience !== $replacement->audience) {
            throw new ConflictHttpException('The replacement source is no longer eligible.');
        }
        $competing = $rows->where('replaces_announcement_id', $source->id)
            ->contains(fn (ClubAnnouncement $item): bool => (int) $item->id !== (int) $replacement->id && $item->status === ClubAnnouncement::STATUS_PUBLISHED);
        if ($competing) {
            throw new ConflictHttpException('A competing Published replacement already exists.');
        }
    }

    private function assertValidGraph(Collection $rows): void
    {
        $byId = $rows->keyBy('id');
        foreach ($rows as $row) {
            if ($row->replaces_announcement_id === null) {
                continue;
            }
            if ((int) $row->replaces_announcement_id === (int) $row->id || ! $byId->has((int) $row->replaces_announcement_id)) {
                throw new ConflictHttpException('The replacement chain is invalid.');
            }
            $seen = [(int) $row->id => true];
            $cursor = $row;
            while ($cursor->replaces_announcement_id !== null) {
                $next = (int) $cursor->replaces_announcement_id;
                if (isset($seen[$next])) {
                    throw new ConflictHttpException('The replacement chain contains a cycle.');
                }
                $seen[$next] = true;
                $cursor = $byId->get($next);
                if (! $cursor) {
                    throw new ConflictHttpException('The replacement chain crosses the club boundary.');
                }
            }
        }
        foreach ($rows->groupBy('replaces_announcement_id') as $sourceId => $children) {
            if ($sourceId !== '' && $children->where('status', ClubAnnouncement::STATUS_PUBLISHED)->count() > 1) {
                throw new ConflictHttpException('The replacement chain branches into multiple Published successors.');
            }
        }
    }

    private function audit(ClubAnnouncement $announcement, User $actor, string $action, array $metadata): void
    {
        AuditLog::query()->create([
            'club_id' => $announcement->club_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => ClubAnnouncement::class,
            'auditable_id' => $announcement->id,
            'metadata' => $metadata,
        ]);
    }

    private function unicodeTrim(string $value): string
    {
        return preg_replace('/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u', '', $value) ?? $value;
    }
}
