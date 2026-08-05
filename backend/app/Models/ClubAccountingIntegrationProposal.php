<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingIntegrationProposal extends Model
{
    public const STATUS_PENDING = 'pending';
    public const TYPES = ['mapping_create', 'mapping_replace', 'charge_activation', 'payment_activation', 'mapping_revocation', 'activation_revocation', 'reactivation', 'coverage_start_date_change', 'mapping_version_change'];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $model): void {
            $terminal = ['approved', 'rejected', 'withdrawn'];
            if (array_diff(array_keys($model->getDirty()), ['status', 'lock_version', 'decided_at', 'updated_at']) !== []
                || $model->getOriginal('status') !== self::STATUS_PENDING
                || ! in_array($model->status, $terminal, true)
                || $model->lock_version !== ((int) $model->getOriginal('lock_version')) + 1
                || ! $model->decided_at) {
                throw new LogicException('Accounting integration proposals are immutable except for one terminal transition.');
            }
        });
        static::deleting(fn () => throw new LogicException('Accounting integration proposals cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['effective_date' => 'date:Y-m-d', 'expires_at' => 'date:Y-m-d', 'coverage_start_date' => 'date:Y-m-d', 'target_snapshot' => 'array', 'decided_at' => 'datetime'];
    }

    public function proposer(): BelongsTo { return $this->belongsTo(User::class, 'proposer_user_id'); }
    public function ledgerAccount(): BelongsTo { return $this->belongsTo(LedgerAccount::class); }
    public function activation(): BelongsTo { return $this->belongsTo(ClubAccountingIntegrationActivation::class); }
    public function mapping(): BelongsTo { return $this->belongsTo(ClubAccountingSourceMapping::class); }
    public function decision(): HasOne { return $this->hasOne(ClubAccountingIntegrationProposalDecision::class, 'proposal_id'); }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->where($field ?? 'public_id', $value)->first();
    }
}
