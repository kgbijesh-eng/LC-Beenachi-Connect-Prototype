<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class ClubAnnouncementPublicationDecision extends Model
{
    public const UPDATED_AT = null;

    public const DECISION_APPROVED = 'approved';

    public const DECISION_REJECTED = 'rejected';

    public const DECISION_REVOKED = 'revoked';

    public const ROLE_PRESIDENT = 'president';

    public const ROLE_SECRETARY = 'secretary';

    protected $fillable = [
        'club_id', 'club_announcement_id', 'announcement_lock_version',
        'content_fingerprint', 'maker_user_id', 'maker_role', 'decision',
        'decided_by_user_id', 'decided_by_role', 'supersedes_decision_id',
        'reason', 'decided_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (! in_array($record->decision, self::decisions(), true)) {
                throw new LogicException('Unknown announcement publication decision.');
            }
            if (! in_array($record->maker_role, self::roles(), true)
                || ! in_array($record->decided_by_role, self::roles(), true)) {
                throw new LogicException('Unknown announcement publication role snapshot.');
            }
            if (preg_match('/\A[0-9a-f]{64}\z/D', (string) $record->content_fingerprint) !== 1) {
                throw new LogicException('Announcement content fingerprint must be 64 lowercase hexadecimal characters.');
            }

            $reasonIsBlank = $record->reason !== null && self::unicodeTrim((string) $record->reason) === '';
            if ($reasonIsBlank) {
                throw new LogicException('Announcement publication decision reason cannot be blank.');
            }
            if (in_array($record->decision, [self::DECISION_REJECTED, self::DECISION_REVOKED], true)
                && $record->reason === null) {
                throw new LogicException('Rejected and revoked decisions require a reason.');
            }
            if ($record->decision === self::DECISION_REVOKED && $record->supersedes_decision_id === null) {
                throw new LogicException('A revoked decision must supersede an approval.');
            }
            if ($record->decision !== self::DECISION_REVOKED && $record->supersedes_decision_id !== null) {
                throw new LogicException('Only a revoked decision may supersede another decision.');
            }
        });

        static::updating(fn () => throw new LogicException('Announcement publication decisions are append-only.'));
        static::deleting(fn () => throw new LogicException('Announcement publication decisions cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'announcement_lock_version' => 'integer',
            'decided_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public static function decisions(): array
    {
        return [self::DECISION_APPROVED, self::DECISION_REJECTED, self::DECISION_REVOKED];
    }

    public static function roles(): array
    {
        return [self::ROLE_PRESIDENT, self::ROLE_SECRETARY];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(ClubAnnouncement::class, 'club_announcement_id');
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maker_user_id');
    }

    public function decisionActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function supersededDecision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_decision_id');
    }

    public function revocation(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_decision_id');
    }

    private static function unicodeTrim(string $value): string
    {
        return preg_replace('/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u', '', $value) ?? $value;
    }
}
