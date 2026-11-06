<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Club;
use LogicException;

final readonly class OrganisationTransactionContext
{
    /** Construction alone grants no authority: the issuer must recognise this exact object. */
    public function __construct(
        public int $organisationId,
        private OrganisationTransactionGuard $issuer,
    ) {}

    public function assertUsable(int|Club|null $organisation = null): void
    {
        $this->issuer->assertContext($this, $organisation);
    }

    public function __serialize(): array
    {
        throw new LogicException('Organisation transaction contexts cannot be transported.');
    }

    public function __unserialize(array $data): void
    {
        throw new LogicException('Organisation transaction contexts cannot be transported.');
    }
}
