<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ProposedOpeningPrivacyAssertions
{
    public function __construct(
        public bool $oneClubScope,
        public bool $tenantIsolation,
        public bool $leastPrivilege,
        public bool $protectedIdentitiesExcluded,
        public bool $protectedEvidenceExcluded,
        public bool $memberSchedulesExcluded,
        public bool $bankDetailsExcluded,
        public bool $donorBeneficiaryIdentitiesExcluded,
        public bool $noOperationalDataAccess,
        public bool $noRestrictedEvidenceInGit,
    ) {}
}
