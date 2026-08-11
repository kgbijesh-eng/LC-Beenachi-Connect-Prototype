<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingSourceIntegration extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Source integrations are immutable.'));
        static::deleting(fn () => throw new LogicException('Source integrations cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['source_version' => 'integer', 'source_amount_paise' => 'integer', 'source_binding' => 'array', 'accounting_date' => 'date:Y-m-d', 'committed_at' => 'datetime'];
    }

    public function activation(): BelongsTo
    {
        return $this->belongsTo(ClubAccountingIntegrationActivation::class, 'integration_activation_id');
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ClubAccountingSourceMapping::class, 'source_mapping_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(ClubJournalEntry::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClubAccountingSourceIntegrationEvent::class, 'source_integration_id');
    }
}
