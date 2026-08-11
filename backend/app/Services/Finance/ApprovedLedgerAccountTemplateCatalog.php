<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\LedgerAccount;
use App\Services\Finance\Exceptions\InvalidLedgerAccountCodeException;
use LogicException;

final class ApprovedLedgerAccountTemplateCatalog
{
    public const VERSION = 'CLUB-COA-2026-01';

    public const DR_033_STATUS = 'pending';

    public const ADDITIONAL_BANK_TEMPLATE = 'BANK-<APPROVED-SHORT-CODE>';

    private const PROVISIONING_UNPROVISIONED = 'unprovisioned';

    private const FIXED_CODE_PATTERN = '/\A[A-Z][A-Z0-9]*(?:-[A-Z0-9]+)*\z/';

    private const BANK_ALIAS_PATTERN = '/\A[A-Z]+(?:-[A-Z]+)*\z/';

    /** @var list<string> */
    private const SENSITIVE_BANK_ALIAS_TOKENS = [
        'ACCOUNT', 'ACCT', 'A-C', 'IFSC', 'SWIFT', 'IBAN', 'UPI', 'CARD', 'PIN', 'OTP', 'CUSTOMER-ID',
    ];

    /** @return list<LedgerAccountTemplateDefinition> */
    public function definitions(): array
    {
        $definitions = [
            $this->fixed('CASH-ON-HAND', 'Cash on Hand', LedgerAccount::TYPE_ASSET, LedgerAccount::NORMAL_DEBIT,
                'One physically controlled cash holding for approved receipts, expenses, transfers, and opening.',
                'required-if-verified-cash-exists', 'Activate only when verified physical cash exists.'),
            $this->fixed('BANK-OPERATING', 'Operating Bank Account', LedgerAccount::TYPE_ASSET, LedgerAccount::NORMAL_DEBIT,
                'One non-sensitive alias for one verified real bank holding.',
                'required-if-verified-bank-exists', 'Exact instance, purpose, and opening evidence require the private bank inventory.'),
            $this->parameterizedBank(),
            $this->fixed('MEMBER-RECEIVABLES', 'Member Receivables Control', LedgerAccount::TYPE_ASSET, LedgerAccount::NORMAL_DEBIT,
                'Control account for issued member charges, allocated-payment settlement, and a supported opening total.',
                'required-for-approved-charge-integration', 'Requires exact private member-and-obligation reconciliation.'),
            $this->fixed('OTHER-RECEIVABLES', 'Other Receivables', LedgerAccount::TYPE_ASSET, LedgerAccount::NORMAL_DEBIT,
                'Future approved non-member receivable workflow.', 'no', 'Remain inactive until a separate workflow and mapping are approved.'),
            $this->fixed('ADVANCES-PAID', 'Advances Paid', LedgerAccount::TYPE_ASSET, LedgerAccount::NORMAL_DEBIT,
                'Future authorized advance-payment and settlement workflow.', 'no', 'Remain inactive until a separate workflow and mapping are approved.'),
            $this->fixed('LIABILITY-RESTRICTED-COLLECTION', 'Restricted Collection Liability', LedgerAccount::TYPE_LIABILITY, LedgerAccount::NORMAL_CREDIT,
                'Collections constrained to a separately evidenced purpose.', 'conditional', 'Requires purpose evidence and accounting-validated mapping.'),
            $this->fixed('LIABILITY-RESTRICTED-DONATION', 'Restricted Donation Liability', LedgerAccount::TYPE_LIABILITY, LedgerAccount::NORMAL_CREDIT,
                'Donation receipts constrained by donor or external terms.', 'no', 'Remain inactive until an authoritative donation workflow and mapping are approved.'),
            $this->fixed('ACCOUNTS-PAYABLE', 'Accounts Payable', LedgerAccount::TYPE_LIABILITY, LedgerAccount::NORMAL_CREDIT,
                'Future approved supplier payable or accrual workflow.', 'no', 'Initial accrual accounting is unsupported.'),
            $this->fixed('OPENING-BALANCE-EQUITY', 'Opening Balance Equity', LedgerAccount::TYPE_EQUITY, LedgerAccount::NORMAL_CREDIT,
                'Candidate one-time opening offset that must never be a balancing plug.', 'conditional', 'Tenant-selectable under DR-033; inactive and unprovisioned until explicitly selected.'),
            $this->fixed('ACCUMULATED-SURPLUS', 'Accumulated Surplus or Deficit', LedgerAccount::TYPE_EQUITY, LedgerAccount::NORMAL_CREDIT,
                'Candidate cumulative historical result after complete reconciliation.', 'conditional', 'Tenant-selectable under DR-033; inactive and unprovisioned until explicitly selected.'),
            $this->fixed('INCOME-MEMBER-DUES', 'Membership Dues Income', LedgerAccount::TYPE_INCOME, LedgerAccount::NORMAL_CREDIT,
                'Issued ordinary membership dues mapped by an approved category.', 'conditional', 'Activate only for an approved and mapped launch charge category.'),
            $this->fixed('INCOME-SPECIAL-COLLECTION', 'Special Collection Income', LedgerAccount::TYPE_INCOME, LedgerAccount::NORMAL_CREDIT,
                'Special Collection only where purpose-specific accounting validation approves income treatment.', 'no', 'No universal Special Collection income treatment is approved.'),
            $this->fixed('INCOME-VOLUNTARY-COLLECTION', 'Voluntary Collection Income', LedgerAccount::TYPE_INCOME, LedgerAccount::NORMAL_CREDIT,
                'Voluntary amounts recognized only when actually received.', 'no', 'Remain inactive until an authoritative receipt workflow and mapping are approved.'),
            $this->fixed('INCOME-DONATION', 'Unrestricted Donation Income', LedgerAccount::TYPE_INCOME, LedgerAccount::NORMAL_CREDIT,
                'Unrestricted donations recognized only when actually received.', 'no', 'Remain inactive until an authoritative donation workflow and mapping are approved.'),
            $this->fixed('INCOME-FELLOWSHIP', 'Fellowship and Member Activity Income', LedgerAccount::TYPE_INCOME, LedgerAccount::NORMAL_CREDIT,
                'Fellowship contributions only after lifecycle and classification approval.', 'no', 'Remain inactive until an authoritative Fellowship workflow and mapping are approved.'),
            $this->fixed('EXPENSE-ADMINISTRATION', 'Administration Expense', LedgerAccount::TYPE_EXPENSE, LedgerAccount::NORMAL_DEBIT,
                'Administration and routine operational expenditure.', 'no', 'Requires an approved expense-authorization and payment workflow.'),
            $this->fixed('EXPENSE-SERVICE-ACTIVITY', 'Service Activity Expense', LedgerAccount::TYPE_EXPENSE, LedgerAccount::NORMAL_DEBIT,
                'Approved service-project and beneficiary-assistance expenditure.', 'no', 'Requires an approved expense-authorization and payment workflow.'),
            $this->fixed('EXPENSE-FELLOWSHIP', 'Fellowship and Member Activity Expense', LedgerAccount::TYPE_EXPENSE, LedgerAccount::NORMAL_DEBIT,
                'Approved Fellowship and member-activity expenditure.', 'no', 'Requires an approved expense-authorization and payment workflow.'),
            $this->fixed('EXPENSE-DISTRICT-OBLIGATION', 'District and International Obligations', LedgerAccount::TYPE_EXPENSE, LedgerAccount::NORMAL_DEBIT,
                'District, multiple-district, and Lions International dues, fees, or assessments.', 'no', 'Requires an approved expense-authorization and payment workflow.'),
            $this->fixed('EXPENSE-MEETINGS-EVENTS', 'Meetings and Events Expense', LedgerAccount::TYPE_EXPENSE, LedgerAccount::NORMAL_DEBIT,
                'Approved meetings, programmes, celebrations, and other qualifying club events.', 'no', 'Requires an approved expense-authorization and payment workflow.'),
            $this->fixed('EXPENSE-BANK-CHARGES', 'Bank Charges Expense', LedgerAccount::TYPE_EXPENSE, LedgerAccount::NORMAL_DEBIT,
                'Bank fees, payment-gateway charges, and similar financial-service costs.', 'no', 'Requires independent bank evidence and an approved expense workflow.'),
            $this->fixed('EXPENSE-FUNDRAISING', 'Fundraising Expense', LedgerAccount::TYPE_EXPENSE, LedgerAccount::NORMAL_DEBIT,
                'Approved costs of fundraising activities.', 'no', 'Requires an approved fundraising expense-payment workflow and mapping.'),
            $this->fixed('EXPENSE-CLUB-PROPERTY-FACILITIES', 'Club Property, Hall and Facilities Expense', LedgerAccount::TYPE_EXPENSE, LedgerAccount::NORMAL_DEBIT,
                'Utilities, routine maintenance, and ordinary repairs.', 'no', 'Property acquisition, equipment, construction, and major improvements require separate asset or capital assessment.'),
        ];

        $this->assertValidDefinitions($definitions);

        return $definitions;
    }

    /** @return list<LedgerAccountTemplateDefinition> */
    public function fixedDefinitions(): array
    {
        return array_values(array_filter(
            $this->definitions(),
            fn (LedgerAccountTemplateDefinition $definition): bool => ! $definition->parameterized,
        ));
    }

    public function canonicalAdditionalBankCode(string $shortAlias): string
    {
        $alias = strtoupper(trim($shortAlias));

        if (str_starts_with($alias, 'BANK-')
            || strlen($alias) < 2
            || strlen($alias) > 35
            || ! preg_match(self::BANK_ALIAS_PATTERN, $alias)) {
            throw new InvalidLedgerAccountCodeException('The bank short alias is invalid.');
        }

        foreach (self::SENSITIVE_BANK_ALIAS_TOKENS as $token) {
            if (str_contains($alias, $token)) {
                throw new InvalidLedgerAccountCodeException('The bank short alias implies sensitive banking details.');
            }
        }

        $code = 'BANK-'.$alias;
        if (strlen($code) > 40 || ! preg_match(self::FIXED_CODE_PATTERN, $code)) {
            throw new InvalidLedgerAccountCodeException('The generated bank account code is invalid.');
        }

        foreach ($this->fixedDefinitions() as $definition) {
            if ($definition->templateCode === $code) {
                throw new InvalidLedgerAccountCodeException('The generated bank account code conflicts with a fixed template.');
            }
        }

        return $code;
    }

    /** @param list<LedgerAccountTemplateDefinition> $definitions */
    public function assertValidDefinitions(array $definitions): void
    {
        $templateCodes = [];
        foreach ($definitions as $definition) {
            if (isset($templateCodes[$definition->templateCode])) {
                throw new LogicException('The ledger account template code is duplicated.');
            }
            $templateCodes[$definition->templateCode] = true;

            if ($definition->parameterized) {
                if ($definition->templateCode !== self::ADDITIONAL_BANK_TEMPLATE) {
                    throw new LogicException('The parameterized ledger account template is invalid.');
                }
            } elseif (strlen($definition->templateCode) < 2
                || strlen($definition->templateCode) > 40
                || ! preg_match(self::FIXED_CODE_PATTERN, $definition->templateCode)) {
                throw new LogicException('A fixed ledger account template code is not canonical.');
            }

            $expectedNormal = match ($definition->accountType) {
                LedgerAccount::TYPE_ASSET, LedgerAccount::TYPE_EXPENSE => LedgerAccount::NORMAL_DEBIT,
                LedgerAccount::TYPE_LIABILITY, LedgerAccount::TYPE_EQUITY, LedgerAccount::TYPE_INCOME => LedgerAccount::NORMAL_CREDIT,
                default => throw new LogicException('A ledger account template type is invalid.'),
            };
            if ($definition->normalBalance !== $expectedNormal
                || $definition->currency !== LedgerAccount::CURRENCY_INR
                || $definition->activeByDefault
                || $definition->provisioningStatus !== self::PROVISIONING_UNPROVISIONED
                || $definition->manualPostingAllowed
                || $definition->selectedByTemplate) {
                throw new LogicException('A ledger account template violates approved inactive semantics.');
            }
        }
    }

    private function fixed(
        string $code,
        string $name,
        string $type,
        string $normalBalance,
        string $purpose,
        string $initialActivation,
        string $qualification,
    ): LedgerAccountTemplateDefinition {
        return new LedgerAccountTemplateDefinition(
            templateCode: $code,
            name: $name,
            accountType: $type,
            normalBalance: $normalBalance,
            statementClassification: $this->metadataFor($code)[0],
            fundUsageRule: $this->metadataFor($code)[1],
            currency: LedgerAccount::CURRENCY_INR,
            purpose: $purpose,
            initialActivation: $initialActivation,
            parameterized: false,
            activeByDefault: false,
            provisioningStatus: self::PROVISIONING_UNPROVISIONED,
            manualPostingAllowed: false,
            selectedByTemplate: false,
            qualification: $qualification,
        );
    }

    private function parameterizedBank(): LedgerAccountTemplateDefinition
    {
        return new LedgerAccountTemplateDefinition(
            templateCode: self::ADDITIONAL_BANK_TEMPLATE,
            name: 'Additional Bank Account',
            accountType: LedgerAccount::TYPE_ASSET,
            normalBalance: LedgerAccount::NORMAL_DEBIT,
            statementClassification: StatementClassification::CASH_AND_BANK->value,
            fundUsageRule: FundUsageRule::ANY_APPROVED_FUND->value,
            currency: LedgerAccount::CURRENCY_INR,
            purpose: 'One separate non-sensitive alias for each additional verified real bank holding.',
            initialActivation: 'conditional-on-private-bank-inventory',
            parameterized: true,
            activeByDefault: false,
            provisioningStatus: self::PROVISIONING_UNPROVISIONED,
            manualPostingAllowed: false,
            selectedByTemplate: false,
            qualification: 'A future private inventory determines the instance, alias, purpose, and opening evidence.',
        );
    }

    /** @return array{string,string} */
    private function metadataFor(string $code): array
    {
        $classification = match ($code) {
            'CASH-ON-HAND', 'BANK-OPERATING' => StatementClassification::CASH_AND_BANK,
            'MEMBER-RECEIVABLES', 'OTHER-RECEIVABLES' => StatementClassification::RECEIVABLES,
            'ADVANCES-PAID' => StatementClassification::OTHER_CURRENT_ASSETS,
            'LIABILITY-RESTRICTED-COLLECTION', 'LIABILITY-RESTRICTED-DONATION', 'ACCOUNTS-PAYABLE' => StatementClassification::LIABILITIES,
            'OPENING-BALANCE-EQUITY', 'ACCUMULATED-SURPLUS' => StatementClassification::FUND_BALANCE_EQUITY,
            'INCOME-MEMBER-DUES', 'INCOME-SPECIAL-COLLECTION', 'INCOME-VOLUNTARY-COLLECTION', 'INCOME-DONATION', 'INCOME-FELLOWSHIP' => StatementClassification::INCOME,
            'EXPENSE-ADMINISTRATION', 'EXPENSE-SERVICE-ACTIVITY', 'EXPENSE-FELLOWSHIP', 'EXPENSE-DISTRICT-OBLIGATION', 'EXPENSE-MEETINGS-EVENTS', 'EXPENSE-BANK-CHARGES', 'EXPENSE-FUNDRAISING', 'EXPENSE-CLUB-PROPERTY-FACILITIES' => StatementClassification::EXPENSES,
            default => throw new LogicException('A fixed template lacks canonical statement metadata.'),
        };
        $fundRule = match ($code) {
            'INCOME-FELLOWSHIP', 'EXPENSE-FELLOWSHIP' => FundUsageRule::FELLOWSHIP_MEMBER_ONLY,
            'EXPENSE-SERVICE-ACTIVITY' => FundUsageRule::SERVICE_ONLY,
            default => FundUsageRule::ANY_APPROVED_FUND,
        };

        return [$classification->value, $fundRule->value];
    }
}
