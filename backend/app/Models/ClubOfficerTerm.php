<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubOfficerTerm extends Model
{
    public const LEGACY_ROLES = [
        'president' => 'president-admin',
        'secretary' => 'secretary',
        'treasurer' => 'treasurer',
    ];

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
