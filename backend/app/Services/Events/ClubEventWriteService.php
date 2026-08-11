<?php

namespace App\Services\Events;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubEvent;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ClubEventWriteService
{
    private const WRITABLE_FIELDS = [
        'title',
        'description',
        'venue',
        'visibility',
    ];

    public function create(Club $club, User $actor, array $attributes): ClubEvent
    {
        return DB::transaction(function () use ($club, $actor, $attributes): ClubEvent {
            $event = ClubEvent::query()->create(array_merge(
                $this->payload($club, $attributes),
                [
                    'club_id' => $club->id,
                    'status' => ClubEvent::STATUS_DRAFT,
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                    'lock_version' => 1,
                ]
            ));

            $this->audit($event, $actor, 'events.created', null, $event->fresh()->toArray());

            return $event->fresh()->load('club');
        });
    }

    public function update(Club $club, ClubEvent $event, User $actor, array $attributes): ClubEvent
    {
        return DB::transaction(function () use ($club, $event, $actor, $attributes): ClubEvent {
            $locked = $this->lockedEvent($club, $event);
            $this->assertLockVersion($locked, (int) $attributes['lock_version']);

            if (in_array($locked->status, [ClubEvent::STATUS_COMPLETED, ClubEvent::STATUS_CANCELLED], true)) {
                throw new ConflictHttpException('Terminal Events and Meetings records cannot be updated.');
            }

            $before = $locked->toArray();
            $locked->fill(array_merge(
                Arr::only($attributes, self::WRITABLE_FIELDS),
                $this->schedulePayload($club, $attributes, $locked),
                [
                    'updated_by_user_id' => $actor->id,
                    'lock_version' => $locked->lock_version + 1,
                ]
            ));
            $locked->save();

            $this->audit($locked, $actor, 'events.updated', $before, $locked->fresh()->toArray());

            return $locked->fresh()->load('club');
        });
    }

    public function publish(Club $club, ClubEvent $event, User $actor, int $lockVersion): ClubEvent
    {
        return $this->transition($club, $event, $actor, $lockVersion, 'events.published', function (ClubEvent $locked): array {
            if ($locked->status !== ClubEvent::STATUS_DRAFT) {
                throw new ConflictHttpException('Only Draft Events and Meetings records can be published.');
            }

            return [
                'status' => ClubEvent::STATUS_PUBLISHED,
                'published_at' => now(),
            ];
        });
    }

    public function cancel(Club $club, ClubEvent $event, User $actor, int $lockVersion, ?string $reason): ClubEvent
    {
        if ($reason === null || trim($reason) === '') {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'The cancellation reason field is required.',
            ]);
        }

        return $this->transition($club, $event, $actor, $lockVersion, 'events.cancelled', function (ClubEvent $locked) use ($reason): array {
            if (! in_array($locked->status, [ClubEvent::STATUS_DRAFT, ClubEvent::STATUS_PUBLISHED], true)) {
                throw new ConflictHttpException('Only Draft or Published Events and Meetings records can be cancelled.');
            }

            return [
                'status' => ClubEvent::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => trim($reason),
            ];
        });
    }

    public function complete(Club $club, ClubEvent $event, User $actor, int $lockVersion): ClubEvent
    {
        return $this->transition($club, $event, $actor, $lockVersion, 'events.completed', function (ClubEvent $locked) use ($club): array {
            if ($locked->status !== ClubEvent::STATUS_PUBLISHED) {
                throw new ConflictHttpException('Only Published Events and Meetings records can be completed.');
            }

            $eligibleAt = $this->completionEligibleAt($club, $locked);
            if (now()->lt($eligibleAt)) {
                throw new ConflictHttpException('This Events and Meetings record cannot be completed before it ends.');
            }

            return [
                'status' => ClubEvent::STATUS_COMPLETED,
                'completed_at' => now(),
            ];
        });
    }

    private function transition(Club $club, ClubEvent $event, User $actor, int $lockVersion, string $action, callable $changes): ClubEvent
    {
        return DB::transaction(function () use ($club, $event, $actor, $lockVersion, $action, $changes): ClubEvent {
            $locked = $this->lockedEvent($club, $event);
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

            return $locked->fresh()->load('club');
        });
    }

    private function lockedEvent(Club $club, ClubEvent $event): ClubEvent
    {
        $locked = ClubEvent::query()
            ->where('club_id', $club->id)
            ->whereKey($event->id)
            ->lockForUpdate()
            ->first();

        if (! $locked) {
            abort(404, 'Event not found for this club.');
        }

        return $locked;
    }

    private function assertLockVersion(ClubEvent $event, int $lockVersion): void
    {
        if ((int) $event->lock_version !== $lockVersion) {
            throw new ConflictHttpException('The Events and Meetings record has changed. Refresh and try again.');
        }
    }

    private function payload(Club $club, array $attributes): array
    {
        return array_merge(
            Arr::only($attributes, ['type', ...self::WRITABLE_FIELDS]),
            $this->schedulePayload($club, $attributes)
        );
    }

    private function schedulePayload(Club $club, array $attributes, ?ClubEvent $existing = null): array
    {
        if (! array_key_exists('is_all_day', $attributes)) {
            return [];
        }

        if ((bool) $attributes['is_all_day']) {
            $startDate = Carbon::createFromFormat('Y-m-d', $attributes['start_date'], $club->timezone)->startOfDay();
            $endDate = Carbon::createFromFormat('Y-m-d', $attributes['end_date'] ?? $attributes['start_date'], $club->timezone)->endOfDay();

            return [
                'is_all_day' => true,
                'starts_at' => $startDate->utc(),
                'ends_at' => $endDate->utc(),
                'start_date' => $attributes['start_date'],
                'end_date' => $attributes['end_date'] ?? $attributes['start_date'],
            ];
        }

        $startsAt = Carbon::parse($attributes['start_at'])->utc();
        $endsAt = isset($attributes['end_at']) ? Carbon::parse($attributes['end_at'])->utc() : null;

        if ($endsAt !== null && $endsAt->lt($startsAt)) {
            throw ValidationException::withMessages([
                'end_at' => 'The end at must be a date after or equal to start at.',
            ]);
        }

        return [
            'is_all_day' => false,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'start_date' => null,
            'end_date' => null,
        ];
    }

    private function completionEligibleAt(Club $club, ClubEvent $event): Carbon
    {
        if ($event->is_all_day) {
            $endDate = $event->end_date ?? $event->start_date;

            return Carbon::parse($endDate->format('Y-m-d'), $club->timezone)->endOfDay()->utc();
        }

        return ($event->ends_at ?? $event->starts_at)->copy()->utc();
    }

    private function audit(ClubEvent $event, User $actor, string $action, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'club_id' => $event->club_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => ClubEvent::class,
            'auditable_id' => $event->id,
            'metadata' => [
                'before' => $before,
                'after' => $after,
            ],
        ]);
    }
}
