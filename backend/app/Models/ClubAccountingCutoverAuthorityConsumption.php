<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverAuthorityConsumption extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'package_version', 'canonical_checksum', 'lions_year', 'cutover_date', 'opening_source_type', 'president_activation_decision_id', 'ca_activation_decision_id', 'execution_success_id', 'journal_entry_id', 'consumed_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Authority consumption is immutable.'));
        static::deleting(fn () => throw new LogicException('Authority consumption cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['package_version' => 'integer', 'cutover_date' => 'date:Y-m-d', 'consumed_at' => 'datetime'];
    }
}
