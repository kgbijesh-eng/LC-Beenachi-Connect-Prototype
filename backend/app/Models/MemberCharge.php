<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class MemberCharge extends Model {
    protected $guarded=[]; protected function casts():array{return ['original_amount_paise'=>'integer','lock_version'=>'integer'];}
    public function charge():BelongsTo{return $this->belongsTo(ClubCharge::class,'club_charge_id');} public function member():BelongsTo{return $this->belongsTo(Member::class);}
    public function allocations():HasMany{return $this->hasMany(PaymentAllocation::class);}
    public function allocatedPaise():int{return (int)$this->allocations()->whereHas('payment',fn($q)=>$q->where('status','recorded'))->sum('amount_paise');}
    public function outstandingPaise():int{return max(0,$this->original_amount_paise-$this->allocatedPaise());}
    public function paymentState():string{$paid=$this->allocatedPaise();return $paid===0?'unpaid':($paid>=$this->original_amount_paise?'paid':'partially_paid');}
}
