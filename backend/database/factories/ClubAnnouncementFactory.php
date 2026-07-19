<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClubAnnouncementFactory extends Factory
{
    protected $model = ClubAnnouncement::class;

    public function definition(): array
    {
        return [
            'club_id' => fn (): int => Club::query()->create([
                'name' => 'Synthetic Announcement Club',
                'code' => 'ANN-'.strtoupper(fake()->unique()->bothify('????####')),
                'timezone' => 'UTC',
                'is_active' => true,
            ])->id,
            'title' => 'Synthetic announcement '.fake()->unique()->numerify('####'),
            'body' => 'Synthetic plain Unicode announcement body.',
            'priority' => ClubAnnouncement::PRIORITY_NORMAL,
            'audience' => ClubAnnouncement::AUDIENCE_CLUB_MEMBERS,
            'status' => ClubAnnouncement::STATUS_DRAFT,
            'scheduled_publish_at' => null,
            'published_at' => null,
            'expires_at' => null,
            'withdrawn_at' => null,
            'withdrawal_reason' => null,
            'archived_at' => null,
            'replaces_announcement_id' => null,
            'published_by_user_id' => null,
            'published_by_role' => null,
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => null,
            'withdrawn_by_user_id' => null,
            'archived_by_user_id' => null,
            'lock_version' => 1,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ClubAnnouncement::STATUS_DRAFT]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ClubAnnouncement::STATUS_PUBLISHED,
            'published_at' => now()->subHour(),
            'published_by_user_id' => User::factory(),
            'published_by_role' => ClubAnnouncement::PUBLISHER_ROLE_PRESIDENT,
        ]);
    }

    public function expired(): static
    {
        return $this->published()->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function withdrawn(): static
    {
        return $this->published()->state(fn () => [
            'status' => ClubAnnouncement::STATUS_WITHDRAWN,
            'withdrawn_at' => now(),
            'withdrawal_reason' => 'Synthetic withdrawal reason.',
            'withdrawn_by_user_id' => User::factory(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ClubAnnouncement::STATUS_ARCHIVED,
            'archived_at' => now(),
            'archived_by_user_id' => User::factory(),
        ]);
    }

    public function officers(): static
    {
        return $this->state(fn () => ['audience' => ClubAnnouncement::AUDIENCE_OFFICERS]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['scheduled_publish_at' => now()->addDay()]);
    }
}
