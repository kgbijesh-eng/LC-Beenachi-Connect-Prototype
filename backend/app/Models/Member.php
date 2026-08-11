<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'user_id',
        'member_code',
        'full_name',
        'salutation',
        'first_name',
        'middle_name',
        'last_name',
        'birth_date',
        'occupation',
        'spouse_name',
        'wedding_anniversary',
        'gender',
        'membership_type',
        'membership_category',
        'membership_program',
        'membership_sponsor_name',
        'phone',
        'home_phone',
        'alternate_phone',
        'address_line_1',
        'address_line_2',
        'city_locality',
        'district',
        'state',
        'postal_code',
        'country',
        'email',
        'status',
        'joined_at',
        'blood_group',
        'blood_donor_consent',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'wedding_anniversary' => 'date',
            'joined_at' => 'date',
            'blood_donor_consent' => 'boolean',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(MemberLedgerEntry::class);
    }}
