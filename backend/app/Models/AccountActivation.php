<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountActivation extends Model
{
    protected $fillable = [
        'user_id', 'club_id', 'intended_email', 'token_hash',
        'issuing_actor_user_id', 'issuance_method', 'issued_at',
        'expires_at', 'consumed_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function issuingActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuing_actor_user_id');
    }
}
