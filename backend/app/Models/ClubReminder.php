<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ClubReminder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';
    public const VISIBILITY_MEMBERS = 'members';
    public const VISIBILITY_MANAGEMENT = 'management';
    public const PERMISSION_VIEW = 'reminders.view';
    public const PERMISSION_MANAGE = 'reminders.manage';
    public const MANAGEMENT_ROLE_SLUGS = ['president-admin', 'secretary'];

    protected $fillable = [
        'club_id', 'title', 'description', 'priority', 'visibility', 'status',
        'is_all_day', 'due_date', 'due_at', 'activated_at', 'completed_at',
        'cancelled_at', 'cancellation_reason', 'created_by_user_id',
        'updated_by_user_id', 'activated_by_user_id', 'completed_by_user_id',
        'cancelled_by_user_id', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'is_all_day' => 'boolean', 'due_date' => 'date:Y-m-d', 'due_at' => 'datetime',
            'activated_at' => 'datetime', 'completed_at' => 'datetime',
            'cancelled_at' => 'datetime', 'lock_version' => 'integer',
        ];
    }

    public static function statuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_CANCELLED];
    }

    public static function priorities(): array
    {
        return [self::PRIORITY_NORMAL, self::PRIORITY_HIGH, self::PRIORITY_URGENT];
    }

    public static function visibilities(): array
    {
        return [self::VISIBILITY_MEMBERS, self::VISIBILITY_MANAGEMENT];
    }

    public function isOverdue(?Carbon $now = null): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) return false;
        $now ??= Carbon::now('UTC');
        if (! $this->is_all_day) return $this->due_at !== null && $now->greaterThan($this->due_at);
        if ($this->due_date === null) return false;
        $timezone = $this->club?->timezone ?? 'UTC';
        return $now->greaterThan($this->due_date->copy()->endOfDay($timezone)->utc());
    }

    public function club(): BelongsTo { return $this->belongsTo(Club::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by_user_id'); }
}
