<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubEvent extends Model
{
    use HasFactory;

    public const TYPE_EVENT = 'event';
    public const TYPE_MEETING = 'meeting';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const VISIBILITY_MEMBERS = 'members';
    public const VISIBILITY_MANAGEMENT = 'management';

    public const PERMISSION_VIEW = 'events.view';
    public const PERMISSION_MANAGE = 'events.manage';

    public const MANAGEMENT_ROLE_SLUGS = [
        'president-admin',
        'secretary',
    ];

    protected $fillable = [
        'club_id',
        'type',
        'title',
        'description',
        'is_all_day',
        'starts_at',
        'ends_at',
        'start_date',
        'end_date',
        'venue',
        'status',
        'visibility',
        'published_at',
        'cancelled_at',
        'cancellation_reason',
        'completed_at',
        'created_by_user_id',
        'updated_by_user_id',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'is_all_day' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public static function types(): array
    {
        return [self::TYPE_EVENT, self::TYPE_MEETING];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    public static function visibilities(): array
    {
        return [self::VISIBILITY_MEMBERS, self::VISIBILITY_MANAGEMENT];
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
}
