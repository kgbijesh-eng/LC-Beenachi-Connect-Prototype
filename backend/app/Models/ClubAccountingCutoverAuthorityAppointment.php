<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverAuthorityAppointment extends Model
{
    public const TYPE_INDEPENDENT_CA = 'independent_ca';

    public const TYPE_TECHNICAL_OPERATOR = 'technical_operator';

    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'appointed_user_id', 'authority_type', 'appointment_reference', 'effective_at', 'expires_at', 'appointed_by_user_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Cutover authority appointments are immutable.'));
        static::deleting(fn () => throw new LogicException('Cutover authority appointments cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['effective_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ClubAccountingCutoverPackage::class, 'cutover_package_id');
    }

    public function appointedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appointed_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverAuthorityEvent::class, 'authority_appointment_id');
    }
}
