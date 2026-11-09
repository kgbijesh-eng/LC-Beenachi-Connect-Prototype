<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class TransactionActorAuthorisation
{
    public function __construct(private readonly ClubPermissionService $permissions) {}

    public function requirePermission(OrganisationTransactionContext $context, int $clubId, User $actor, string $permission): void
    {
        $context->assertUsable($clubId);
        if (! $this->permissions->userHasPermission($actor, $permission, $clubId)) {
            throw new AuthorizationException();
        }
    }
}
