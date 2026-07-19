<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\LedgerAccount;
use App\Services\Finance\Exceptions\InvalidOpeningBalanceValidationRequestException;
use DateTimeImmutable;

final class OpeningBalanceValidationFoundation
{
    public const PACKAGE_STRUCTURE_VERSION = 'CUTOVER-EVIDENCE-PACKAGE-2026-01';

    public const VALIDATION_POLICY_VERSION = 'OPENING-BALANCE-VALIDATION-2026-01';

    public const STATUS = 'PROPOSED-UNVERIFIED';

    public const ACTIVATION_STATUS = 'BLOCKED';

    private const REFERENCE_PATTERN = '/\A[A-Z][A-Z0-9]*(?:-[A-Z0-9]+)*\z/';

    private const UUID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i';

    private const POSITION_SECTIONS = ['CASH', 'BANK', 'LIABILITY', 'ZERO'];

    private const LIABILITY_CLASSIFICATIONS = ['LIABILITY', 'EXTERNAL-RESTRICTION', 'BENEFICIARY-CUSTODY'];

    private const EXCEPTION_CATEGORIES = [
        'AMOUNT', 'IDENTITY', 'EVIDENCE', 'MAPPING', 'DUPLICATION', 'PRIVACY',
        'AUTHORIZATION', 'REVERSAL-READINESS', 'DR-033', 'OTHER-STRUCTURAL',
    ];

    private const EQUITY_CODES = ['OPENING-BALANCE-EQUITY', 'ACCUMULATED-SURPLUS'];

    public function __construct(private readonly ApprovedLedgerAccountTemplateCatalog $catalogue) {}

    public function validateProposedUnverifiedPackage(
        ProposedUnverifiedOpeningPackageRequest $request,
    ): ProposedUnverifiedOpeningPackageReport {
        $this->validateHeader($request);
        $definitions = $this->definitions();
        $planItems = $this->validateAccountPlan($request);

        /** @var array<string, OpeningPackageActivationBlocker> $blockers */
        $blockers = [];
        $this->block($blockers, 'OPERATIONAL-COLLISION-CHECK-NOT-PERFORMED', 'sourceIdentity');
        $this->block($blockers, 'ACTIVATION-AUTHORIZATION-ABSENT', 'activationAuthorization');
        $this->block($blockers, 'DR-033-EQUITY-DECISION-PENDING', 'openingEquity');

        $positions = $this->positions($request, $definitions, $planItems, $blockers);
        $lines = $this->journalLines($request, $definitions, $planItems, $positions, $blockers);
        $this->memberReceivables($request, $positions, $blockers);
        $exceptions = $this->exceptions($request, $blockers);
        $privacy = $this->privacy($request, $blockers);

        if (! array_filter($lines, fn (array $line): bool => in_array($line['account_code'], self::EQUITY_CODES, true))) {
            $this->block($blockers, 'DR-033-EQUITY-LINE-REQUIRED', 'journalLines');
        }

        ksort($blockers, SORT_STRING);
        $blockerValues = array_values($blockers);
        $canonical = [
            'versions' => [
                $request->packageStructureVersion, $request->validationPolicyVersion,
                $request->catalogueVersion, $request->provisioningPlanPolicyVersion,
            ],
            'identity' => [
                $request->clubId, $request->tenantId, $this->reference($request->packageId),
                $this->reference($request->packageVersion), $request->lionsYear, $request->cutoverDate,
                $this->optionalReference($request->futureCutoverDecisionReference),
                $request->sourceType, $this->reference($request->sourceId), $request->sourceEvent,
                strtolower($request->idempotencyCandidate),
            ],
            'account_plan' => $this->canonicalAccountPlan($planItems),
            'positions' => $positions,
            'journal_lines' => $lines,
            'member_receivables' => $request->memberReceivables ? [
                $request->memberReceivables->controlTotalPaise,
                $request->memberReceivables->privateSubledgerTotalPaise,
                $this->optionalReference($request->memberReceivables->reconciliationReference),
                $this->optionalReference($request->memberReceivables->mappingReference),
            ] : null,
            'exceptions' => $exceptions,
            'privacy' => $privacy,
            'blockers' => array_map(fn (OpeningPackageActivationBlocker $blocker): array => [$blocker->code, $blocker->field], $blockerValues),
        ];
        $serialized = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return new ProposedUnverifiedOpeningPackageReport(
            packageStructureVersion: $request->packageStructureVersion,
            validationPolicyVersion: $request->validationPolicyVersion,
            catalogueVersion: $request->catalogueVersion,
            provisioningPlanPolicyVersion: $request->provisioningPlanPolicyVersion,
            packageId: $this->reference($request->packageId),
            sourceId: $this->reference($request->sourceId),
            status: self::STATUS,
            activationStatus: self::ACTIVATION_STATUS,
            positions: $positions,
            journalLines: $lines,
            blockers: $blockerValues,
            dryRunFingerprint: hash('sha256', $serialized),
        );
    }

    private function validateHeader(ProposedUnverifiedOpeningPackageRequest $request): void
    {
        if ($request->packageStructureVersion !== self::PACKAGE_STRUCTURE_VERSION
            || $request->validationPolicyVersion !== self::VALIDATION_POLICY_VERSION
            || $request->catalogueVersion !== ApprovedLedgerAccountTemplateCatalog::VERSION
            || $request->provisioningPlanPolicyVersion !== InactiveLedgerAccountProvisioningPlanValidator::POLICY_VERSION) {
            $this->invalid('The immutable package versions are invalid.');
        }
        if ($request->clubId < 1 || $request->tenantId < 1) {
            $this->invalid('The package scope identities are invalid.');
        }
        $packageId = $this->reference($request->packageId);
        $this->reference($request->packageVersion);
        if ($this->reference($request->sourceId) !== $packageId
            || $request->sourceType !== 'accounting_opening_package'
            || $request->sourceEvent !== 'activated'
            || ! preg_match(self::UUID_PATTERN, $request->idempotencyCandidate)) {
            $this->invalid('The proposed source identity is invalid.');
        }
        if (! preg_match('/\A\d{4}-\d{2}\z/', $request->lionsYear)) {
            $this->invalid('The Lions Year is invalid.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $request->cutoverDate);
        if (! $date || $date->format('Y-m-d') !== $request->cutoverDate
            || $date->format('m-d') !== '07-01' || $request->cutoverDate < '2027-07-01') {
            $this->invalid('The cutover date is invalid.');
        }
        $expectedYear = $date->format('Y').'-'.(clone $date)->modify('+1 year -1 day')->format('y');
        if ($request->lionsYear !== $expectedYear) {
            $this->invalid('The cutover date and Lions Year do not agree.');
        }
        if ($request->cutoverDate > '2027-07-01') {
            $this->reference($request->futureCutoverDecisionReference ?? '');
        } elseif ($request->futureCutoverDecisionReference !== null) {
            $this->reference($request->futureCutoverDecisionReference);
        }
    }

    /** @return array<string, LedgerAccountTemplateDefinition> */
    private function definitions(): array
    {
        $definitions = [];
        foreach ($this->catalogue->definitions() as $definition) {
            $definitions[$definition->templateCode] = $definition;
        }

        return $definitions;
    }

    /** @return array<string, ProposedUnverifiedLedgerAccountPlanItem> */
    private function validateAccountPlan(ProposedUnverifiedOpeningPackageRequest $request): array
    {
        $plan = $request->accountPlan;
        $definitions = $this->definitions();
        if ($plan->clubId !== $request->clubId
            || $plan->catalogueVersion !== $request->catalogueVersion
            || $plan->planningPolicyVersion !== $request->provisioningPlanPolicyVersion
            || $plan->status !== InactiveLedgerAccountProvisioningPlanValidator::STATUS
            || $plan->isActive || $plan->provisioned || $plan->authorizedForCreation || $plan->authorizedForActivation) {
            $this->invalid('The proposed account plan boundary is invalid.');
        }
        $items = [];
        foreach ($plan->items as $item) {
            $definition = $definitions[$item->templateCode] ?? null;
            if ($item->status !== InactiveLedgerAccountProvisioningPlanValidator::STATUS
                || $item->isActive || $item->provisioned || $item->authorizedForCreation || $item->authorizedForActivation
                || isset($items[$item->generatedAccountCode]) || ! $definition
                || $item->name !== $definition->name
                || $item->accountType !== $definition->accountType
                || $item->normalBalance !== $definition->normalBalance
                || $item->currency !== $definition->currency
                || ! in_array($item->eligibilityClassification, InactiveLedgerAccountProvisioningPlanValidator::ELIGIBILITY_CLASSIFICATIONS, true)
                || (! $definition->parameterized && $item->generatedAccountCode !== $definition->templateCode)) {
                $this->invalid('A proposed account-plan item is invalid.');
            }
            foreach ($item->referenceIdentifiers as $reference) {
                $this->reference($reference);
            }
            $items[$item->generatedAccountCode] = $item;
        }

        return $items;
    }

    private function canonicalAccountPlan(array $planItems): array
    {
        ksort($planItems, SORT_STRING);

        return array_map(function (ProposedUnverifiedLedgerAccountPlanItem $item): array {
            $references = array_map(fn (string $reference): string => $this->reference($reference), $item->referenceIdentifiers);
            sort($references, SORT_STRING);

            return [
                $item->templateCode, $item->generatedAccountCode, $item->name, $item->accountType,
                $item->normalBalance, $item->currency, $item->eligibilityClassification, $references,
            ];
        }, array_values($planItems));
    }

    private function positions(
        ProposedUnverifiedOpeningPackageRequest $request,
        array $definitions,
        array $planItems,
        array &$blockers,
    ): array {
        $positions = [];
        foreach ($request->positions as $position) {
            if (! $position instanceof ProposedOpeningPosition
                || ! in_array($position->section, self::POSITION_SECTIONS, true)
                || $position->amountPaise < 0) {
                $this->invalid('An opening position is malformed.');
            }
            $code = $this->accountCode($position->accountCode, $definitions, $planItems);
            if (isset($positions[$code])) {
                $this->invalid('An opening account position is duplicated.');
            }
            $definition = $definitions[$planItems[$code]->templateCode];
            $this->validateSectionSemantics($position, $definition);
            if ($position->section === 'ZERO' ? $position->amountPaise !== 0 : $position->amountPaise < 1) {
                $this->invalid('An opening position amount is invalid.');
            }
            if ($definition->accountType === LedgerAccount::TYPE_INCOME) {
                $this->block($blockers, 'OPENING-INCOME-ACCOUNT-PROHIBITED', $code);
            } elseif ($definition->accountType === LedgerAccount::TYPE_EXPENSE) {
                $this->block($blockers, 'OPENING-EXPENSE-ACCOUNT-PROHIBITED', $code);
            } elseif ($definition->accountType === LedgerAccount::TYPE_EQUITY) {
                $this->block($blockers, 'DR-033-EQUITY-SELECTION-PROHIBITED', $code);
            } elseif (! in_array($definition->accountType, [LedgerAccount::TYPE_ASSET, LedgerAccount::TYPE_LIABILITY], true)) {
                $this->block($blockers, 'OPENING-ACCOUNT-TYPE-PROHIBITED', $code);
            }
            $evidence = $this->optionalReference($position->evidenceReference);
            if ($evidence === null) {
                $this->block($blockers, 'MANDATORY-EVIDENCE-UNRESOLVED', $code);
            }
            $positions[$code] = [
                'section' => $position->section,
                'account_code' => $code,
                'amount_paise' => $position->amountPaise,
                'evidence_reference' => $evidence,
                'classification' => $position->classification,
                'account_type' => $definition->accountType,
            ];
        }
        ksort($positions, SORT_STRING);

        return array_values($positions);
    }

    private function validateSectionSemantics(ProposedOpeningPosition $position, LedgerAccountTemplateDefinition $definition): void
    {
        if ($position->section === 'CASH' && $position->accountCode !== 'CASH-ON-HAND') {
            $this->invalid('A cash position has an invalid account.');
        }
        if ($position->section === 'BANK' && ! str_starts_with($position->accountCode, 'BANK-')) {
            $this->invalid('A bank position has an invalid account.');
        }
        if ($position->section === 'LIABILITY') {
            if ($definition->accountType !== LedgerAccount::TYPE_LIABILITY
                || ! in_array($position->classification, self::LIABILITY_CLASSIFICATIONS, true)) {
                $this->invalid('A liability position classification is invalid.');
            }
        } elseif ($position->classification !== null) {
            $this->invalid('An opening classification is not permitted for this section.');
        }
    }

    private function journalLines(
        ProposedUnverifiedOpeningPackageRequest $request,
        array $definitions,
        array $planItems,
        array $positions,
        array &$blockers,
    ): array {
        $positionMap = [];
        foreach ($positions as $position) {
            $positionMap[$position['account_code']] = $position;
        }
        $lines = [];
        $stableReferences = [];
        $debits = 0;
        $credits = 0;
        foreach ($request->journalLines as $line) {
            if (! $line instanceof ProposedOpeningJournalLine) {
                $this->invalid('A proposed journal line is malformed.');
            }
            $code = $this->accountCode($line->accountCode, $definitions, $planItems);
            $stable = $this->reference($line->stableAccountReference);
            if (isset($lines[$code]) || isset($stableReferences[$stable])) {
                $this->invalid('A proposed journal identity is duplicated.');
            }
            $stableReferences[$stable] = true;
            $hasDebit = $line->debitPaise !== null;
            $hasCredit = $line->creditPaise !== null;
            if ($hasDebit === $hasCredit
                || ($hasDebit && $line->debitPaise < 1)
                || ($hasCredit && $line->creditPaise < 1)) {
                $this->invalid('A proposed journal line must be exactly one-sided and positive.');
            }
            $definition = $definitions[$planItems[$code]->templateCode];
            $direction = $hasDebit ? 'debit' : 'credit';
            $amount = $hasDebit ? $line->debitPaise : $line->creditPaise;
            if ($definition->accountType === LedgerAccount::TYPE_INCOME) {
                $this->block($blockers, 'OPENING-INCOME-ACCOUNT-PROHIBITED', $code);
            } elseif ($definition->accountType === LedgerAccount::TYPE_EXPENSE) {
                $this->block($blockers, 'OPENING-EXPENSE-ACCOUNT-PROHIBITED', $code);
            } elseif ($definition->accountType === LedgerAccount::TYPE_EQUITY) {
                $this->block($blockers, 'DR-033-EQUITY-SELECTION-PROHIBITED', $code);
            } elseif (! in_array($definition->accountType, [LedgerAccount::TYPE_ASSET, LedgerAccount::TYPE_LIABILITY], true)) {
                $this->block($blockers, 'OPENING-ACCOUNT-TYPE-PROHIBITED', $code);
            }
            $expectedDirection = $definition->accountType === LedgerAccount::TYPE_ASSET ? 'debit' : 'credit';
            if ($direction !== $expectedDirection) {
                $this->block($blockers, 'OPENING-NORMAL-BALANCE-DIRECTION-MISMATCH', $code);
            }
            if (! isset($positionMap[$code]) || $positionMap[$code]['section'] === 'ZERO'
                || $positionMap[$code]['amount_paise'] !== $amount) {
                $this->block($blockers, 'OPENING-POSITION-JOURNAL-MISMATCH', $code);
            }
            if ($this->optionalReference($line->evidenceReference) === null) {
                $this->block($blockers, 'MANDATORY-EVIDENCE-UNRESOLVED', $code);
            }
            if ($direction === 'debit') {
                $debits = $this->checkedAdd($debits, $amount);
            } else {
                $credits = $this->checkedAdd($credits, $amount);
            }
            $lines[$code] = [
                'account_code' => $code,
                'stable_account_reference' => $stable,
                'direction' => $direction,
                'amount_paise' => $amount,
                'evidence_reference' => $this->optionalReference($line->evidenceReference),
            ];
        }
        if (count($lines) < 2) {
            $this->block($blockers, 'JOURNAL-MINIMUM-LINES-NOT-MET', 'journalLines');
        }
        if ($debits === 0 || $credits === 0 || $debits !== $credits) {
            $this->block($blockers, 'JOURNAL-IMBALANCED', 'journalLines');
        }
        ksort($lines, SORT_STRING);
        $values = array_values($lines);
        foreach ($values as $index => &$line) {
            $line = ['line_number' => $index + 1, ...$line];
        }

        return $values;
    }

    private function memberReceivables(ProposedUnverifiedOpeningPackageRequest $request, array $positions, array &$blockers): void
    {
        if (! $request->memberReceivables) {
            $this->block($blockers, 'MANDATORY-EVIDENCE-UNRESOLVED', 'memberReceivables');

            return;
        }
        $member = $request->memberReceivables;
        if ($member->controlTotalPaise < 0 || $member->privateSubledgerTotalPaise < 0) {
            $this->invalid('A member-receivables total is invalid.');
        }
        if ($member->controlTotalPaise !== $member->privateSubledgerTotalPaise) {
            $this->block($blockers, 'MEMBER-RECEIVABLES-MISMATCH', 'memberReceivables');
        }
        if ($this->optionalReference($member->reconciliationReference) === null
            || $this->optionalReference($member->mappingReference) === null) {
            $this->block($blockers, 'MANDATORY-EVIDENCE-UNRESOLVED', 'memberReceivables');
        }
        $position = array_values(array_filter($positions, fn (array $value): bool => $value['account_code'] === 'MEMBER-RECEIVABLES'))[0] ?? null;
        if (! $position || $position['amount_paise'] !== $member->controlTotalPaise) {
            $this->block($blockers, 'MEMBER-RECEIVABLES-MISMATCH', 'MEMBER-RECEIVABLES');
        }
    }

    private function exceptions(ProposedUnverifiedOpeningPackageRequest $request, array &$blockers): array
    {
        $values = [];
        foreach ($request->exceptions as $exception) {
            if (! $exception instanceof ProposedOpeningPackageException
                || ! in_array($exception->category, self::EXCEPTION_CATEGORIES, true)
                || ! in_array($exception->status, ['OPEN', 'RESOLVED'], true)
                || ($exception->amountPaise !== null && $exception->amountPaise < 0)) {
                $this->invalid('A proposed exception is malformed.');
            }
            $id = $this->reference($exception->exceptionId);
            if (isset($values[$id])) {
                $this->invalid('A proposed exception identity is duplicated.');
            }
            if ($exception->affectedAccountCode !== null) {
                $this->accountCode($exception->affectedAccountCode, $this->definitions(), $this->validateAccountPlan($request));
            }
            $resolution = $this->optionalReference($exception->resolutionReference);
            if ($exception->status === 'OPEN') {
                $this->block($blockers, 'UNRESOLVED-EXCEPTION', $id);
            } elseif ($resolution === null) {
                $this->block($blockers, 'MANDATORY-EVIDENCE-UNRESOLVED', $id);
            }
            $values[$id] = [$id, $exception->category, $exception->status, $exception->affectedAccountCode, $exception->amountPaise, $resolution];
        }
        ksort($values, SORT_STRING);

        return array_values($values);
    }

    private function privacy(ProposedUnverifiedOpeningPackageRequest $request, array &$blockers): array
    {
        $values = get_object_vars($request->privacyAssertions);
        ksort($values, SORT_STRING);
        foreach ($values as $field => $asserted) {
            if (! $asserted) {
                $this->block($blockers, 'PRIVACY-ASSERTION-UNVERIFIED', $field);
            }
        }

        return $values;
    }

    private function accountCode(string $code, array $definitions, array $planItems): string
    {
        $canonical = strtoupper(trim($code));
        if ($canonical === ApprovedLedgerAccountTemplateCatalog::ADDITIONAL_BANK_TEMPLATE
            || ! isset($planItems[$canonical])) {
            $this->invalid('An opening account code is unsupported.');
        }
        $templateCode = $planItems[$canonical]->templateCode;
        if (! isset($definitions[$templateCode])) {
            $this->invalid('An opening account semantic is unsupported.');
        }

        return $canonical;
    }

    private function reference(string $reference): string
    {
        $canonical = strtoupper(trim($reference));
        if (strlen($canonical) < 2 || strlen($canonical) > 80 || ! preg_match(self::REFERENCE_PATTERN, $canonical)) {
            $this->invalid('An opaque reference is invalid.');
        }

        return $canonical;
    }

    private function optionalReference(?string $reference): ?string
    {
        return $reference === null ? null : $this->reference($reference);
    }

    private function checkedAdd(int $total, int $amount): int
    {
        if ($amount > PHP_INT_MAX - $total) {
            $this->invalid('An integer-paise total overflows.');
        }

        return $total + $amount;
    }

    private function block(array &$blockers, string $code, ?string $field): void
    {
        $blockers[$code.'|'.$field] = new OpeningPackageActivationBlocker($code, $field);
    }

    private function invalid(string $message): never
    {
        throw new InvalidOpeningBalanceValidationRequestException($message);
    }
}
