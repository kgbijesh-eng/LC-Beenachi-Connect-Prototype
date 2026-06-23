<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubProject extends Model
{
    use HasFactory;

    public const TYPE_PROJECT = 'project';
    public const TYPE_ACTIVITY = 'activity';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const VISIBILITY_MEMBERS = 'members';
    public const VISIBILITY_MANAGEMENT = 'management';

    public const PERMISSION_VIEW = 'projects.view';
    public const PERMISSION_MANAGE = 'projects.manage';

    public const MANAGEMENT_ROLE_SLUGS = [
        'president-admin',
        'secretary',
    ];

    protected $fillable = [
        'club_id',
        'record_type',
        'title',
        'description',
        'objective',
        'service_category',
        'visibility',
        'status',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'location',
        'lead_member_id',
        'expected_beneficiary_count',
        'actual_beneficiary_count',
        'impact_summary',
        'cancellation_reason',
        'planned_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'created_by_user_id',
        'updated_by_user_id',
        'planned_by_user_id',
        'started_by_user_id',
        'completed_by_user_id',
        'cancelled_by_user_id',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date:Y-m-d',
            'planned_end_date' => 'date:Y-m-d',
            'actual_start_date' => 'date:Y-m-d',
            'actual_end_date' => 'date:Y-m-d',
            'expected_beneficiary_count' => 'integer',
            'actual_beneficiary_count' => 'integer',
            'planned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public static function recordTypes(): array
    {
        return [self::TYPE_PROJECT, self::TYPE_ACTIVITY];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PLANNED,
            self::STATUS_ACTIVE,
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

    public function leadMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'lead_member_id');
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