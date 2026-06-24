<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PaymentReceipt extends Model {
    public const STATUSES=['issued','voided']; protected $guarded=[]; protected function casts():array{return ['receipt_date'=>'date','voided_at'=>'datetime'];}
    public function payment():BelongsTo{return $this->belongsTo(MemberPayment::class,'member_payment_id');}
}
