<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverDecision extends Model
{
    public const STAGE_DRY_RUN = 'dry_run';

    public const STAGE_ACTIVATION = 'activation';

    public const ACTOR_PRESIDENT = 'president';

    public const ACTOR_INDEPENDENT_CA = 'independent_ca';

    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'decision_stage', 'actor_authority', 'decision_result', 'decision_reference', 'package_version', 'canonical_checksum', 'chart_version', 'lions_year', 'cutover_date', 'dr033_classification', 'accounting_review_id', 'president_approval_id', 'authority_appointment_id', 'permission_assignment_id', 'recorded_by_user_id', 'reason', 'recorded_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Cutover decisions are immutable.'));
        static::deleting(fn () => throw new LogicException('Cutover decisions cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['package_version' => 'integer', 'cutover_date' => 'date:Y-m-d', 'recorded_at' => 'datetime'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverDecisionEvent::class, 'cutover_decision_id');
    }
}
