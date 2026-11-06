<?php

namespace App\Services\Access;

use App\Models\Club;

final class InstanceOrganisation
{
    public function resolve(): Club
    {
        $code = config('instance.organisation_code');
        abort_unless(is_string($code) && $code !== '' && trim($code) === $code, 503, 'Organisation configuration is unavailable.');

        $clubs = Club::query()->where('code', $code)->get();
        abort_unless($clubs->count() === 1, 503, 'Organisation configuration is unavailable.');

        return $clubs->sole();
    }

    public function assertClub(Club $club): void
    {
        abort_unless($this->resolve()->is($club), 404, 'Not found.');
    }
}
