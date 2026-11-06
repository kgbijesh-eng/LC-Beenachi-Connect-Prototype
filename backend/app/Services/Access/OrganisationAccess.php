<?php

namespace App\Services\Access;

use App\Models\Club;

final class OrganisationAccess
{
    public function __construct(private readonly InstanceOrganisation $instance) {}

    /**
     * Resolve from the database on every call; never trust a cached request/model
     * activity flag. This is an HTTP admission check, not a transaction lock.
     */
    public function resolve(?string $clubId = null): Club
    {
        $organisation = $this->instance->resolve();

        // Keep P0.1's foreign-target response ahead of suspension disclosure.
        if ($clubId !== null) {
            abort_unless($clubId === (string) $organisation->getKey(), 404, 'Not found.');
        }

        if (! $organisation->is_active) {
            abort(response()->json([
                'message' => 'Organisation access is suspended.',
                'code' => 'ORGANISATION_SUSPENDED',
            ], 403));
        }

        return $organisation;
    }
}
