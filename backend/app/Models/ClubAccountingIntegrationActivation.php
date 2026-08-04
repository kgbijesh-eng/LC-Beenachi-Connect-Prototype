<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingIntegrationActivation extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Integration activations are immutable.'));
        static::deleting(fn () => throw new LogicException('Integration activations cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['coverage_start_date' => 'date:Y-m-d', 'activated_at' => 'datetime'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClubAccountingIntegrationActivationEvent::class, 'integration_activation_id');
    }
}
