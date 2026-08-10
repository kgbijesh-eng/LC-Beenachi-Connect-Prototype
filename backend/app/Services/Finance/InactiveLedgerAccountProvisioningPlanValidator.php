<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Services\Finance\Exceptions\InvalidInactiveLedgerAccountPlanException;
use App\Services\Finance\Exceptions\InvalidLedgerAccountCodeException;

final class InactiveLedgerAccountProvisioningPlanValidator
{
    public const POLICY_VERSION = 'INACTIVE-PROVISIONING-PLAN-2026-01';

    public const STATUS = 'PROPOSED-UNVERIFIED';

    /** @var list<string> */
    public const ELIGIBILITY_CLASSIFICATIONS = [
        'VERIFIED-CASH-HOLDING',
        'VERIFIED-BANK-HOLDING',
        'RECONCILED-MEMBER-RECEIVABLES',
        'APPROVED-SOURCE-MAPPING',
        'APPROVED-FUTURE-WORKFLOW',
        'APPROVED-OPENING-EQUITY-SELECTION',
    ];

    private const REFERENCE_PATTERN = '/\A[A-Z][A-Z0-9]*(?:-[A-Z0-9]+)*\z/';

    public function __construct(private readonly ApprovedLedgerAccountTemplateCatalog $catalogue) {}

    public function validateProposedUnverifiedPlan(
        InactiveLedgerAccountProvisioningPlanRequest $request,
    ): ProposedUnverifiedLedgerAccountPlan {
        if ($request->clubId < 1 || $request->actorUserId < 1) {
            throw new InvalidInactiveLedgerAccountPlanException('The planning scope identities are invalid.');
        }
        if ($request->catalogueVersion !== ApprovedLedgerAccountTemplateCatalog::VERSION
            || $request->planningPolicyVersion !== self::POLICY_VERSION) {
            throw new InvalidInactiveLedgerAccountPlanException('The planning versions are invalid.');
        }
        if ($request->selections === []) {
            throw new InvalidInactiveLedgerAccountPlanException('A proposed plan must contain at least one selection.');
        }

        $definitions = [];
        foreach ($this->catalogue->definitions() as $definition) {
            $definitions[$definition->templateCode] = $definition;
        }

        $seenTemplates = [];
        $seenCodes = [];
        $items = [];
        foreach ($request->selections as $selection) {
            if (! $selection instanceof InactiveLedgerAccountProvisioningPlanSelection) {
                throw new InvalidInactiveLedgerAccountPlanException('A proposed selection is invalid.');
            }

            $templateCode = trim($selection->templateCode);
            $definition = $definitions[$templateCode] ?? null;
            if (! $definition) {
                throw new InvalidInactiveLedgerAccountPlanException('A selected template is unknown.');
            }

            $classification = $this->classification($selection->eligibilityClassifications);
            if ($classification !== $this->requiredClassification($templateCode)) {
                throw new InvalidInactiveLedgerAccountPlanException('The eligibility classification is incompatible.');
            }
            $references = $this->references($selection->referenceIdentifiers);

            if ($definition->parameterized) {
                if ($selection->bankShortAlias === null) {
                    throw new InvalidInactiveLedgerAccountPlanException('An additional-bank alias is required.');
                }
                try {
                    $generatedCode = $this->catalogue->canonicalAdditionalBankCode($selection->bankShortAlias);
                } catch (InvalidLedgerAccountCodeException $exception) {
                    throw new InvalidInactiveLedgerAccountPlanException('The additional-bank alias is invalid.', 0, $exception);
                }
            } else {
                if ($selection->bankShortAlias !== null) {
                    throw new InvalidInactiveLedgerAccountPlanException('A fixed template must not contain a bank alias.');
                }
                $generatedCode = $definition->templateCode;
            }

            $selectionIdentity = $definition->parameterized
                ? $templateCode.'|'.$selection->bankShortAlias
                : $templateCode;
            if (isset($seenTemplates[$selectionIdentity])) {
                throw new InvalidInactiveLedgerAccountPlanException('A template selection is duplicated.');
            }
            $seenTemplates[$selectionIdentity] = true;

            if (isset($seenCodes[$generatedCode])) {
                throw new InvalidInactiveLedgerAccountPlanException('A generated account code is duplicated.');
            }
            $seenCodes[$generatedCode] = true;

            $items[] = new ProposedUnverifiedLedgerAccountPlanItem(
                status: self::STATUS,
                templateCode: $definition->templateCode,
                generatedAccountCode: $generatedCode,
                name: $definition->name,
                accountType: $definition->accountType,
                normalBalance: $definition->normalBalance,
                currency: $definition->currency,
                eligibilityClassification: $classification,
                referenceIdentifiers: $references,
            );
        }

        usort($items, fn (ProposedUnverifiedLedgerAccountPlanItem $left, ProposedUnverifiedLedgerAccountPlanItem $right): int => [$left->generatedAccountCode, $left->templateCode] <=> [$right->generatedAccountCode, $right->templateCode]);

        return new ProposedUnverifiedLedgerAccountPlan(
            clubId: $request->clubId,
            actorUserId: $request->actorUserId,
            catalogueVersion: $request->catalogueVersion,
            planningPolicyVersion: $request->planningPolicyVersion,
            status: self::STATUS,
            items: $items,
        );
    }

    /** @param list<string> $classifications */
    private function classification(array $classifications): string
    {
        if (count($classifications) !== 1 || count(array_unique($classifications)) !== 1) {
            throw new InvalidInactiveLedgerAccountPlanException('Exactly one eligibility classification is required.');
        }
        $classification = $classifications[0] ?? '';
        if (! in_array($classification, self::ELIGIBILITY_CLASSIFICATIONS, true)) {
            throw new InvalidInactiveLedgerAccountPlanException('The eligibility classification is unknown.');
        }

        return $classification;
    }

    /** @param list<string> $references
     * @return list<string>
     */
    private function references(array $references): array
    {
        if ($references === []) {
            throw new InvalidInactiveLedgerAccountPlanException('A non-sensitive planning reference is required.');
        }

        $canonical = [];
        foreach ($references as $reference) {
            $value = strtoupper(trim($reference));
            if (strlen($value) < 2 || strlen($value) > 80 || ! preg_match(self::REFERENCE_PATTERN, $value)) {
                throw new InvalidInactiveLedgerAccountPlanException('A planning reference is invalid.');
            }
            if (isset($canonical[$value])) {
                throw new InvalidInactiveLedgerAccountPlanException('A planning reference is duplicated.');
            }
            $canonical[$value] = true;
        }

        $values = array_keys($canonical);
        sort($values, SORT_STRING);

        return $values;
    }

    private function requiredClassification(string $templateCode): string
    {
        return match ($templateCode) {
            'CASH-ON-HAND' => 'VERIFIED-CASH-HOLDING',
            'BANK-OPERATING', ApprovedLedgerAccountTemplateCatalog::ADDITIONAL_BANK_TEMPLATE => 'VERIFIED-BANK-HOLDING',
            'MEMBER-RECEIVABLES' => 'RECONCILED-MEMBER-RECEIVABLES',
            'LIABILITY-RESTRICTED-COLLECTION', 'INCOME-MEMBER-DUES' => 'APPROVED-SOURCE-MAPPING',
            default => 'APPROVED-FUTURE-WORKFLOW',
        };
    }
}
