<?php

namespace App\Support\Announcements;

use App\Models\ClubAnnouncement;
use Carbon\CarbonInterface;

final class ClubAnnouncementFingerprint
{
    public const VERSION = 'ANNOUNCEMENT-FINGERPRINT-2026-01';

    public function fingerprint(ClubAnnouncement $announcement): string
    {
        return hash('sha256', $this->canonicalJson($announcement));
    }

    public function canonicalJson(ClubAnnouncement $announcement): string
    {
        return json_encode([
            'fingerprint_contract_version' => self::VERSION,
            'announcement_id' => (int) $announcement->id,
            'club_id' => (int) $announcement->club_id,
            'lock_version' => (int) $announcement->lock_version,
            'title' => $this->unicodeTrim((string) $announcement->title),
            'body' => str_replace(["\r\n", "\r"], "\n", (string) $announcement->body),
            'priority' => (string) $announcement->priority,
            'audience' => (string) $announcement->audience,
            'scheduled_publish_at' => $this->timestamp($announcement->scheduled_publish_at),
            'expires_at' => $this->timestamp($announcement->expires_at),
            'replaces_announcement_id' => $announcement->replaces_announcement_id === null
                ? null
                : (int) $announcement->replaces_announcement_id,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function timestamp(?CarbonInterface $value): ?string
    {
        return $value?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function unicodeTrim(string $value): string
    {
        return preg_replace('/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u', '', $value) ?? $value;
    }
}
