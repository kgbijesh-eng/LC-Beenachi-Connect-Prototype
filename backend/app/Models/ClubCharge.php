<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class ClubCharge extends Model {
    public const CATEGORIES=['membership_dues','installation_contribution','event_contribution','service_project_contribution','other_contribution'];
    public const STATUSES=['draft','issued','closed','cancelled'];
    protected $guarded=[]; protected function casts():array{return ['amount_paise'=>'integer','due_date'=>'date','assignment_member_ids'=>'array','issued_at'=>'datetime','closed_at'=>'datetime','cancelled_at'=>'datetime','lock_version'=>'integer'];}
    public function club():BelongsTo{return $this->belongsTo(Club::class);} public function memberCharges():HasMany{return $this->hasMany(MemberCharge::class);}
}
