<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany,HasOne};
class MemberPayment extends Model {
    public const MODES=['cash','upi','bank_transfer','cheque','other']; public const STATUSES=['recorded','voided'];
    protected $guarded=[]; protected function casts():array{return ['amount_paise'=>'integer','payment_date'=>'date','voided_at'=>'datetime','lock_version'=>'integer'];}
    public function member():BelongsTo{return $this->belongsTo(Member::class);} public function allocations():HasMany{return $this->hasMany(PaymentAllocation::class);}
    public function receipt():HasOne{return $this->hasOne(PaymentReceipt::class);} public function replacement():BelongsTo{return $this->belongsTo(self::class,'replacement_payment_id');}
}
