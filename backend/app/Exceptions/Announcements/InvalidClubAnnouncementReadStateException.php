<?php

namespace App\Exceptions\Announcements;

use App\Models\ClubAnnouncement;
use RuntimeException;

class InvalidClubAnnouncementReadStateException extends RuntimeException
{
    public function __construct(
        public readonly array $diagnostics,
        ?int $announcementId = null,
    ) {
        parent::__construct(sprintf(
            'Club announcement%s failed read-integrity validation: %s',
            $announcementId === null ? '' : ' '.$announcementId,
            implode('; ', $diagnostics),
        ));
    }

    public static function forAnnouncement(ClubAnnouncement $announcement, array $diagnostics): self
    {
        return new self($diagnostics, (int) $announcement->getKey());
    }
}
