<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverExecutionSuccess extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'package_version', 'canonical_checksum', 'lions_year', 'cutover_date', 'opening_source_type', 'execution_reference', 'idempotency_key', 'president_activation_decision_id', 'ca_activation_decision_id', 'journal_entry_id', 'executed_by_user_id', 'technical_appointment_id', 'permission_assignment_id', 'committed_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Committed cutover executions are immutable.'));
        static::deleting(fn () => throw new LogicException('Committed cutover executions cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['package_version' => 'integer', 'cutover_date' => 'date:Y-m-d', 'committed_at' => 'datetime'];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(ClubJournalEntry::class);
    }
}
