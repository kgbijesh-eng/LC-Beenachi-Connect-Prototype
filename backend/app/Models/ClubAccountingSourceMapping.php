<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingSourceMapping extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Accounting source mappings are immutable.'));
        static::deleting(fn () => throw new LogicException('Accounting source mappings cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['effective_date' => 'date:Y-m-d', 'expires_at' => 'date:Y-m-d', 'approved_at' => 'datetime'];
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClubAccountingSourceMappingEvent::class, 'source_mapping_id');
    }
}
