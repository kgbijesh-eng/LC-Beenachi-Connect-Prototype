<?php

namespace App\Models;

use App\Exceptions\Announcements\InvalidClubAnnouncementReadStateException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ClubAnnouncement extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_ARCHIVED = 'archived';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const AUDIENCE_CLUB_MEMBERS = 'club-members';

    public const AUDIENCE_OFFICERS = 'officers';

    public const PUBLISHER_ROLE_PRESIDENT = 'president';

    public const PUBLISHER_ROLE_SECRETARY = 'secretary';

    public const PERMISSION_VIEW = 'announcements.view';

    public const PERMISSION_MANAGEMENT_VIEW = 'announcements.management.view';

    protected $fillable = [
        'club_id', 'title', 'body', 'priority', 'audience', 'status',
        'scheduled_publish_at', 'published_at', 'expires_at', 'withdrawn_at',
        'withdrawal_reason', 'archived_at', 'replaces_announcement_id',
        'published_by_user_id', 'published_by_role', 'created_by_user_id',
        'updated_by_user_id', 'withdrawn_by_user_id', 'archived_by_user_id',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_publish_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'archived_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public static function statuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_WITHDRAWN, self::STATUS_ARCHIVED];
    }

    public static function priorities(): array
    {
        return [self::PRIORITY_NORMAL, self::PRIORITY_HIGH, self::PRIORITY_URGENT];
    }

    public static function audiences(): array
    {
        return [self::AUDIENCE_CLUB_MEMBERS, self::AUDIENCE_OFFICERS];
    }

    public static function publisherRoles(): array
    {
        return [self::PUBLISHER_ROLE_PRESIDENT, self::PUBLISHER_ROLE_SECRETARY];
    }

    public function isEffectivelyExpired(?Carbon $now = null): bool
    {
        $now ??= Carbon::now('UTC');

        return $this->status === self::STATUS_PUBLISHED
            && $this->expires_at !== null
            && $now->greaterThanOrEqualTo($this->expires_at);
    }

    public function effectiveStatus(?Carbon $now = null): string
    {
        return $this->isEffectivelyExpired($now) ? 'expired' : $this->status;
    }

    public function assertReadableIntegrity(Club $routeClub, ?Carbon $now = null): void
    {
        $now ??= Carbon::now('UTC');
        $problems = [];

        if ((int) $this->club_id !== (int) $routeClub->id || ! $this->club?->is($routeClub)) {
            $problems[] = 'route club does not match the owning club';
        }
        if (! in_array($this->status, self::statuses(), true)) {
            $problems[] = 'unknown status';
        }
        if (! in_array($this->priority, self::priorities(), true)) {
            $problems[] = 'unknown priority';
        }
        if (! in_array($this->audience, self::audiences(), true)) {
            $problems[] = 'unknown audience';
        }
        if ($this->published_by_role !== null && ! in_array($this->published_by_role, self::publisherRoles(), true)) {
            $problems[] = 'unknown publisher role';
        }

        $title = trim((string) $this->title);
        $body = trim((string) $this->body);
        if ($title === '' || mb_strlen($title) > 160) {
            $problems[] = 'title is outside the approved Unicode limits';
        }
        if ($body === '' || mb_strlen($body) > 5000) {
            $problems[] = 'body is outside the approved Unicode limits';
        }

        $this->validateActor('creator', $this->created_by_user_id, false, $problems);
        $this->validateActor('updater', $this->updated_by_user_id, true, $problems);
        $this->validateActor('publisher', $this->published_by_user_id, true, $problems);
        $this->validateActor('withdrawer', $this->withdrawn_by_user_id, true, $problems);
        $this->validateActor('archiver', $this->archived_by_user_id, true, $problems);

        if ($this->replaces_announcement_id !== null) {
            if ((int) $this->replaces_announcement_id === (int) $this->id) {
                $problems[] = 'replacement is self-referential';
            }
            if ($this->replacedAnnouncement === null) {
                $problems[] = 'replacement relationship cannot be resolved';
            } elseif ((int) $this->replacedAnnouncement->club_id !== (int) $this->club_id) {
                $problems[] = 'replacement relationship crosses clubs';
            }
        }

        if ($this->status === self::STATUS_DRAFT) {
            foreach (['published_at', 'published_by_user_id', 'published_by_role', 'withdrawn_at', 'withdrawal_reason', 'withdrawn_by_user_id', 'archived_at', 'archived_by_user_id'] as $field) {
                if ($this->{$field} !== null) {
                    $problems[] = "Draft has forbidden {$field}";
                }
            }
        }

        if ($this->status === self::STATUS_PUBLISHED) {
            $this->validatePublicationFields($problems);
            foreach (['withdrawn_at', 'withdrawal_reason', 'withdrawn_by_user_id', 'archived_at', 'archived_by_user_id'] as $field) {
                if ($this->{$field} !== null) {
                    $problems[] = "Published has forbidden {$field}";
                }
            }
        }

        if ($this->status === self::STATUS_WITHDRAWN) {
            $this->validatePublicationFields($problems);
            if ($this->withdrawn_at === null) {
                $problems[] = 'Withdrawn requires withdrawn_at';
            }
            if (trim((string) $this->withdrawal_reason) === '') {
                $problems[] = 'Withdrawn requires a non-blank withdrawal_reason';
            }
            if ($this->withdrawn_by_user_id === null || $this->withdrawer === null) {
                $problems[] = 'Withdrawn requires a resolvable withdrawer';
            }
            if ($this->archived_at !== null || $this->archived_by_user_id !== null) {
                $problems[] = 'Withdrawn has archive fields';
            }
        }

        if ($this->status === self::STATUS_ARCHIVED) {
            if ($this->archived_at === null) {
                $problems[] = 'Archived requires archived_at';
            }
            if ($this->archived_by_user_id === null || $this->archiver === null) {
                $problems[] = 'Archived requires a resolvable archiver';
            }
            $hasAnyPublication = $this->published_at !== null || $this->published_by_user_id !== null || $this->published_by_role !== null;
            if ($hasAnyPublication) {
                $this->validatePublicationFields($problems);
            }
            $hasAnyWithdrawal = $this->withdrawn_at !== null || $this->withdrawal_reason !== null || $this->withdrawn_by_user_id !== null;
            if ($hasAnyWithdrawal && ($this->withdrawn_at === null || trim((string) $this->withdrawal_reason) === '' || $this->withdrawn_by_user_id === null || $this->withdrawer === null)) {
                $problems[] = 'Archived has an incomplete retained withdrawal history';
            }
        }

        if ($this->expires_at !== null && $this->published_at !== null && ! $this->expires_at->greaterThan($this->published_at)) {
            $problems[] = 'expires_at must be strictly later than published_at';
        }

        if ($problems !== []) {
            throw InvalidClubAnnouncementReadStateException::forAnnouncement($this, array_values(array_unique($problems)));
        }
    }

    private function validatePublicationFields(array &$problems): void
    {
        if ($this->published_at === null) {
            $problems[] = "{$this->status} requires published_at";
        }
        if ($this->published_by_user_id === null || $this->publisher === null) {
            $problems[] = "{$this->status} requires a resolvable publisher";
        }
        if (! in_array($this->published_by_role, self::publisherRoles(), true)) {
            $problems[] = "{$this->status} requires a valid publisher role";
        }
    }

    private function validateActor(string $relationship, mixed $foreignKey, bool $nullable, array &$problems): void
    {
        if ($foreignKey === null) {
            if (! $nullable) {
                $problems[] = "{$relationship} is required";
            }

            return;
        }
        if ($this->{$relationship} === null) {
            $problems[] = "{$relationship} relationship cannot be resolved";
        }
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function withdrawer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by_user_id');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    public function replacedAnnouncement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_announcement_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_announcement_id');
    }

    public function publicationDecisions(): HasMany
    {
        return $this->hasMany(ClubAnnouncementPublicationDecision::class, 'club_announcement_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ClubAnnouncementRead::class, 'club_announcement_id');
    }
}
