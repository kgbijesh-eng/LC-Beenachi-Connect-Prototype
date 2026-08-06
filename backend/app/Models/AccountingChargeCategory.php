<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AccountingChargeCategory extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_accounting_enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $category): void {
            if ($category->isDirty('code')) {
                throw new LogicException('Accounting category codes are immutable.');
            }
            if ($category->isDirty('fund_code') && ClubCharge::query()->where('category', $category->getOriginal('code'))->whereNotNull('fund_code')->exists()) {
                throw new LogicException('A used accounting category fund is immutable.');
            }
        });
    }
}
