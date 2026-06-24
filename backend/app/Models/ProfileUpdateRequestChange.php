<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProfileUpdateRequestChange extends Model{public const ALLOWED_FIELDS=['full_name','salutation','first_name','middle_name','last_name','gender','birth_date','occupation','spouse_name','wedding_anniversary','phone','home_phone','alternate_phone','address_line_1','address_line_2','city_locality','district','state','postal_code','country','blood_group','blood_donor_consent'];protected $guarded=[];protected function casts():array{return ['original_value'=>'json','proposed_value'=>'json'];}public function request():BelongsTo{return $this->belongsTo(ProfileUpdateRequest::class,'profile_update_request_id');}}
