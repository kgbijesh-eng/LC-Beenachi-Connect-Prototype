<?php

namespace App\Services\Projects;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubProject;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ClubProjectWriteService
{
    private const DRAFT_FIELDS = [
        'title', 'description', 'objective', 'service_category', 'visibility',
        'planned_start_date', 'planned_end_date', 'location', 'lead_member_id',
        'expected_beneficiary_count',
    ];

    private const PLANNED_FIELDS = [
        'title', 'description', 'objective', 'service_category', 'visibility',
        'planned_start_date', 'planned_end_date', 'location', 'lead_member_id',
        'expected_beneficiary_count',
    ];

    private const ACTIVE_FIELDS = [
        'description', 'location', 'actual_start_date', 'actual_end_date',
        'actual_beneficiary_count', 'impact_summary',
    ];

    public function create(Club $club, User $actor, array $attributes): ClubProject
    {
        $this->assertLeadMemberBelongsToClub($club, $attributes['lead_member_id'] ?? null);
        $this->assertPlannedDateOrder($attributes);

        return DB::transaction(function () use ($club, $actor, $attributes): ClubProject {
            $project = ClubProject::query()->create(array_merge(
                Arr::only($attributes, ['record_type', ...self::DRAFT_FIELDS]),
                [
                    'club_id' => $club->id,
                    'status' => ClubProject::STATUS_DRAFT,
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                    'lock_version' => 1,
                ]
            ));

            $this->audit($project, $actor, 'projects.created', null, $project->fresh()->toArray());

            return $project->fresh()->load(['club', 'leadMember']);
        });
    }

    public function update(Club $club, ClubProject $project, User $actor, array $attributes): ClubProject
    {
        $this->assertLeadMemberBelongsToClub($club, $attributes['lead_member_id'] ?? null);
        $this->assertPlannedDateOrder($attributes);
        $this->assertActualDateOrder($attributes);

        return DB::transaction(function () use ($club, $project, $actor, $attributes): ClubProject {
            $locked = $this->lockedProject($club, $project);
            $this->assertLockVersion($locked, (int) $attributes['lock_version']);

            $fields = match ($locked->status) {
                ClubProject::STATUS_DRAFT => self::DRAFT_FIELDS,
                ClubProject::STATUS_PLANNED => self::PLANNED_FIELDS,
                ClubProject::STATUS_ACTIVE => self::ACTIVE_FIELDS,
                default => throw new ConflictHttpException('Terminal Projects and Activities records cannot be updated.'),
            };

            $before = $locked->toArray();
            $locked->fill(array_merge(
                Arr::only($attributes, $fields),
                [
                    'updated_by_user_id' => $actor->id,
                    'lock_version' => $locked->lock_version + 1,
                ]
            ));
            $locked->save();

            $this->audit($locked, $actor, 'projects.updated', $before, $locked->fresh()->toArray());

            return $locked->fresh()->load(['club', 'leadMember']);
        });
    }

    public function plan(Club $club, ClubProject $project, User $actor, int $lockVersion): ClubProject
    {
        return $this->transition($club, $project, $actor, $lockVersion, 'projects.planned', function (ClubProject $locked) use ($actor): array {
            if ($locked->status !== ClubProject::STATUS_DRAFT) {
                throw new ConflictHttpException('Only Draft Projects and Activities records can be planned.');
            }

            if ($locked->planned_start_date === null) {
                throw ValidationException::withMessages([
                    'planned_start_date' => 'The planned start date field is required before planning.',
                ]);
            }

            return [
                'status' => ClubProject::STATUS_PLANNED,
                'planned_at' => now(),
                'planned_by_user_id' => $actor->id,
            ];
        });
    }

    public function start(Club $club, ClubProject $project, User $actor, int $lockVersion, ?string $actualStartDate): ClubProject
    {
        return $this->transition($club, $project, $actor, $lockVersion, 'projects.started', function (ClubProject $locked) use ($actor, $actualStartDate): array {
            if ($locked->status !== ClubProject::STATUS_PLANNED) {
                throw new ConflictHttpException('Only Planned Projects and Activities records can be started.');
            }

            return [
                'status' => ClubProject::STATUS_ACTIVE,
                'started_at' => now(),
                'started_by_user_id' => $actor->id,
                'actual_start_date' => $actualStartDate ?? now()->toDateString(),
            ];
        });
    }

    public function complete(Club $club, ClubProject $project, User $actor, int $lockVersion, array $attributes): ClubProject
    {
        $this->assertActualDateOrder($attributes);

        return $this->transition($club, $project, $actor, $lockVersion, 'projects.completed', function (ClubProject $locked) use ($actor, $attributes): array {
            if (! in_array($locked->status, [ClubProject::STATUS_PLANNED, ClubProject::STATUS_ACTIVE], true)) {
                throw new ConflictHttpException('Only Planned or Active Projects and Activities records can be completed.');
            }

            $actualEndDate = $attributes['actual_end_date'] ?? now()->toDateString();
            $this->assertNotFutureCompletion($actualEndDate);

            return array_merge(
                Arr::only($attributes, ['actual_start_date', 'actual_end_date', 'actual_beneficiary_count', 'impact_summary']),
                [
                    'status' => ClubProject::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'completed_by_user_id' => $actor->id,
                    'actual_end_date' => $actualEndDate,
                ]
            );
        });
    }

    public function cancel(Club $club, ClubProject $project, User $actor, int $lockVersion, ?string $reason): ClubProject
    {
        if ($reason === null || trim($reason) === '') {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'The cancellation reason field is required.',
            ]);
        }

        return $this->transition($club, $project, $actor, $lockVersion, 'projects.cancelled', function (ClubProject $locked) use ($actor, $reason): array {
            if (! in_array($locked->status, [ClubProject::STATUS_DRAFT, ClubProject::STATUS_PLANNED, ClubProject::STATUS_ACTIVE], true)) {
                throw new ConflictHttpException('Only Draft, Planned or Active Projects and Activities records can be cancelled.');
            }

            return [
                'status' => ClubProject::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
                'cancellation_reason' => trim($reason),
            ];
        });
    }

    private function transition(Club $club, ClubProject $project, User $actor, int $lockVersion, string $action, callable $changes): ClubProject
    {
        return DB::transaction(function () use ($club, $project, $actor, $lockVersion, $action, $changes): ClubProject {
            $locked = $this->lockedProject($club, $project);
            $this->assertLockVersion($locked, $lockVersion);
            $before = $locked->toArray();

            $locked->fill(array_merge(
                $changes($locked),
                [
                    'updated_by_user_id' => $actor->id,
                    'lock_version' => $locked->lock_version + 1,
                ]
            ));
            $locked->save();

            $this->audit($locked, $actor, $action, $before, $locked->fresh()->toArray());

            return $locked->fresh()->load(['club', 'leadMember']);
        });
    }

    private function lockedProject(Club $club, ClubProject $project): ClubProject
    {
        $locked = ClubProject::query()
            ->where('club_id', $club->id)
            ->whereKey($project->id)
            ->lockForUpdate()
            ->first();

        if (! $locked) {
            abort(404, 'Project or Activity not found for this club.');
        }

        return $locked;
    }

    private function assertLockVersion(ClubProject $project, int $lockVersion): void
    {
        if ((int) $project->lock_version !== $lockVersion) {
            throw new ConflictHttpException('The Projects and Activities record has changed. Refresh and try again.');
        }
    }

    private function assertLeadMemberBelongsToClub(Club $club, mixed $leadMemberId): void
    {
        if ($leadMemberId === null) {
            return;
        }

        $exists = Member::query()
            ->where('club_id', $club->id)
            ->whereKey($leadMemberId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'lead_member_id' => 'The selected lead member does not belong to this club.',
            ]);
        }
    }

    private function assertPlannedDateOrder(array $attributes): void
    {
        if (($attributes['planned_start_date'] ?? null) && ($attributes['planned_end_date'] ?? null)) {
            if (Carbon::parse($attributes['planned_end_date'])->lt(Carbon::parse($attributes['planned_start_date']))) {
                throw ValidationException::withMessages([
                    'planned_end_date' => 'The planned end date must be after or equal to the planned start date.',
                ]);
            }
        }
    }

    private function assertActualDateOrder(array $attributes): void
    {
        if (($attributes['actual_start_date'] ?? null) && ($attributes['actual_end_date'] ?? null)) {
            if (Carbon::parse($attributes['actual_end_date'])->lt(Carbon::parse($attributes['actual_start_date']))) {
                throw ValidationException::withMessages([
                    'actual_end_date' => 'The actual end date must be after or equal to the actual start date.',
                ]);
            }
        }
    }

    private function assertNotFutureCompletion(string $actualEndDate): void
    {
        if (Carbon::parse($actualEndDate)->gt(now()->startOfDay())) {
            throw new ConflictHttpException('This Projects and Activities record cannot be completed before it ends.');
        }
    }

    private function audit(ClubProject $project, User $actor, string $action, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'club_id' => $project->club_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => ClubProject::class,
            'auditable_id' => $project->id,
            'metadata' => [
                'before' => $before,
                'after' => $after,
            ],
        ]);
    }
}