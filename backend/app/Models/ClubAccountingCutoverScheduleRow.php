<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClubAccountingCutoverScheduleRow extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'schedule_type', 'row_key', 'non_sensitive_label', 'ledger_account_id', 'presentation_group', 'direction', 'amount_paise'];

    protected static function booted(): void
    {
        static::creating(fn (self $row) => $row->public_id ??= (string) Str::orderedUuid());
    }

    protected function casts(): array
    {
        return ['amount_paise' => 'integer'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ClubAccountingCutoverPackage::class, 'cutover_package_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }
}
