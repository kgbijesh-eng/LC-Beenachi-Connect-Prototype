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
        'phone',
        'email',
        'status',
        'joined_at',
        'blood_group',
        'blood_donor_consent',
    ];

    protected function casts(): array
    {
        return [
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
