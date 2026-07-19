<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ApprovedLedgerAccountTemplateCatalog;
use App\Services\Finance\Exceptions\InvalidLedgerAccountCodeException;
use App\Services\Finance\LedgerAccountTemplateDefinition;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use LogicException;
use Tests\TestCase;

class Milestone3iikApprovedChartTemplateFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_version_count_codes_names_types_and_normal_balances_are_exact(): void
    {
        $catalog = new ApprovedLedgerAccountTemplateCatalog;
        $definitions = $catalog->definitions();

        $this->assertSame('CLUB-COA-2026-01', ApprovedLedgerAccountTemplateCatalog::VERSION);
        $this->assertCount(24, $definitions);
        $this->assertCount(24, array_unique(array_column($definitions, 'templateCode')));
        $this->assertCount(23, $catalog->fixedDefinitions());

        $expected = [
            'CASH-ON-HAND' => ['Cash on Hand', 'asset', 'debit'],
            'BANK-OPERATING' => ['Operating Bank Account', 'asset', 'debit'],
            'BANK-<APPROVED-SHORT-CODE>' => ['Additional Bank Account', 'asset', 'debit'],
            'MEMBER-RECEIVABLES' => ['Member Receivables Control', 'asset', 'debit'],
            'OTHER-RECEIVABLES' => ['Other Receivables', 'asset', 'debit'],
            'ADVANCES-PAID' => ['Advances Paid', 'asset', 'debit'],
            'LIABILITY-RESTRICTED-COLLECTION' => ['Restricted Collection Liability', 'liability', 'credit'],
            'LIABILITY-RESTRICTED-DONATION' => ['Restricted Donation Liability', 'liability', 'credit'],
            'ACCOUNTS-PAYABLE' => ['Accounts Payable', 'liability', 'credit'],
            'OPENING-BALANCE-EQUITY' => ['Opening Balance Equity', 'equity', 'credit'],
            'ACCUMULATED-SURPLUS' => ['Accumulated Surplus or Deficit', 'equity', 'credit'],
            'INCOME-MEMBER-DUES' => ['Membership Dues Income', 'income', 'credit'],
            'INCOME-SPECIAL-COLLECTION' => ['Special Collection Income', 'income', 'credit'],
            'INCOME-VOLUNTARY-COLLECTION' => ['Voluntary Collection Income', 'income', 'credit'],
            'INCOME-DONATION' => ['Unrestricted Donation Income', 'income', 'credit'],
            'INCOME-FELLOWSHIP' => ['Fellowship and Member Activity Income', 'income', 'credit'],
            'EXPENSE-ADMINISTRATION' => ['Administration Expense', 'expense', 'debit'],
            'EXPENSE-SERVICE-ACTIVITY' => ['Service Activity Expense', 'expense', 'debit'],
            'EXPENSE-FELLOWSHIP' => ['Fellowship and Member Activity Expense', 'expense', 'debit'],
            'EXPENSE-DISTRICT-OBLIGATION' => ['District and International Obligations', 'expense', 'debit'],
            'EXPENSE-MEETINGS-EVENTS' => ['Meetings and Events Expense', 'expense', 'debit'],
            'EXPENSE-BANK-CHARGES' => ['Bank Charges Expense', 'expense', 'debit'],
            'EXPENSE-FUNDRAISING' => ['Fundraising Expense', 'expense', 'debit'],
            'EXPENSE-CLUB-PROPERTY-FACILITIES' => ['Club Property, Hall and Facilities Expense', 'expense', 'debit'],
        ];

        $actual = [];
        foreach ($definitions as $definition) {
            $actual[$definition->templateCode] = [
                $definition->name,
                $definition->accountType,
                $definition->normalBalance,
            ];
            $this->assertSame(LedgerAccount::CURRENCY_INR, $definition->currency);
            $this->assertFalse($definition->activeByDefault);
            $this->assertSame('unprovisioned', $definition->provisioningStatus);
            $this->assertFalse($definition->manualPostingAllowed);
            $this->assertFalse($definition->selectedByTemplate);
        }

        $this->assertSame($expected, $actual);
        $this->assertSame('pending', ApprovedLedgerAccountTemplateCatalog::DR_033_STATUS);
    }

    public function test_fixed_codes_are_dr_025_canonical_and_parameterized_bank_marker_is_not_concrete(): void
    {
        $catalog = new ApprovedLedgerAccountTemplateCatalog;
        $fixedCodes = array_column($catalog->fixedDefinitions(), 'templateCode');

        foreach ($fixedCodes as $code) {
            $this->assertMatchesRegularExpression('/\A[A-Z][A-Z0-9]*(?:-[A-Z0-9]+)*\z/', $code);
            $this->assertGreaterThanOrEqual(2, strlen($code));
            $this->assertLessThanOrEqual(40, strlen($code));
            $this->assertSame(strtoupper(trim($code)), $code);
        }

        $this->assertNotContains(ApprovedLedgerAccountTemplateCatalog::ADDITIONAL_BANK_TEMPLATE, $fixedCodes);
        $parameterized = array_values(array_filter(
            $catalog->definitions(),
            fn (LedgerAccountTemplateDefinition $definition): bool => $definition->parameterized,
        ));
        $this->assertCount(1, $parameterized);
        $this->assertSame(ApprovedLedgerAccountTemplateCatalog::ADDITIONAL_BANK_TEMPLATE, $parameterized[0]->templateCode);
    }

    public function test_removed_generic_suspense_manual_and_unallocated_accounts_are_absent(): void
    {
        $codes = array_column((new ApprovedLedgerAccountTemplateCatalog)->definitions(), 'templateCode');

        $this->assertNotContains('INCOME-OTHER-APPROVED', $codes);
        $this->assertNotContains('EXPENSE-OTHER-APPROVED', $codes);
        $this->assertContains('EXPENSE-FUNDRAISING', $codes);
        $this->assertContains('EXPENSE-CLUB-PROPERTY-FACILITIES', $codes);
        foreach ($codes as $code) {
            $this->assertStringNotContainsString('SUSPENSE', $code);
            $this->assertStringNotContainsString('MANUAL', $code);
            $this->assertStringNotContainsString('UNALLOCATED', $code);
        }
    }

    public function test_definition_is_readonly_and_duplicate_template_codes_fail_closed(): void
    {
        $catalog = new ApprovedLedgerAccountTemplateCatalog;
        $definition = $catalog->definitions()[0];

        try {
            $definition->name = 'Changed';
            $this->fail('A template definition must be readonly.');
        } catch (Error) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(LogicException::class);
        $catalog->assertValidDefinitions([$definition, $definition]);
    }

    public function test_bank_short_aliases_are_deterministic_canonical_and_non_sensitive(): void
    {
        $catalog = new ApprovedLedgerAccountTemplateCatalog;

        foreach ([
            'SERVICE' => 'BANK-SERVICE',
            'FELLOWSHIP' => 'BANK-FELLOWSHIP',
            'PROJECT' => 'BANK-PROJECT',
            'FUNDRAISING' => 'BANK-FUNDRAISING',
            'SERVICE-ACTIVITY' => 'BANK-SERVICE-ACTIVITY',
            ' service ' => 'BANK-SERVICE',
            'AA' => 'BANK-AA',
            str_repeat('A', 35) => 'BANK-'.str_repeat('A', 35),
        ] as $alias => $expected) {
            $this->assertSame($expected, $catalog->canonicalAdditionalBankCode($alias));
        }
    }

    public function test_invalid_or_sensitive_bank_short_aliases_fail_closed(): void
    {
        $catalog = new ApprovedLedgerAccountTemplateCatalog;
        $invalid = [
            '', 'A', str_repeat('A', 36), 'BANK-SERVICE', '123456', 'SBI-1234',
            'ACCOUNT-ENDING', 'IFSC', 'SERVICE--ACTIVITY', 'SERVICE_ACCOUNT', 'OPERATING',
            'ACCOUNT', 'ACCT', 'A-C', 'SWIFT', 'IBAN', 'UPI', 'CARD', 'PIN', 'OTP',
            'CUSTOMER-ID', 'MY-ACCOUNT', 'SERVICE IFSC', 'SERVICE/ACTIVITY', 'SERVICE.ACTIVITY',
            '-SERVICE', 'SERVICE-', 'SÉRVICE',
        ];

        foreach ($invalid as $alias) {
            try {
                $catalog->canonicalAdditionalBankCode($alias);
                $this->fail("Invalid bank alias should fail: {$alias}");
            } catch (InvalidLedgerAccountCodeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_catalogue_is_pure_tenant_neutral_and_creates_no_records_or_runtime_surface(): void
    {
        $catalog = app(ApprovedLedgerAccountTemplateCatalog::class);
        $definitions = $catalog->definitions();

        $this->assertCount(24, $definitions);
        foreach ($definitions as $definition) {
            $properties = array_keys(get_object_vars($definition));
            $this->assertNotContains('clubId', $properties);
            $this->assertNotContains('tenantId', $properties);
            $this->assertNotContains('actorId', $properties);
            $this->assertNotContains('openingAmount', $properties);
        }

        $this->assertSame(0, Club::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, LedgerAccount::count());
        $this->assertSame(0, ClubAccountingPeriod::count());
        $this->assertSame(0, ClubJournalEntry::count());
        $this->assertSame(0, ClubJournalLine::count());
        $this->assertSame(0, AuditLog::count());
        $this->assertSame(0, Permission::count());
        $this->assertSame(0, Role::count());

        $runtimeUris = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri): bool => str_contains($uri, 'chart-template') || str_contains($uri, 'ledger-account'));
        $this->assertSame([], $runtimeUris->values()->all());

        $runtimeCommands = array_keys(Artisan::all());
        $catalogCommands = array_values(array_filter(
            $runtimeCommands,
            fn (string $command): bool => str_contains($command, 'chart-template') || str_contains($command, 'ledger-account'),
        ));
        $this->assertSame([], $catalogCommands);
        $this->assertNotContains('provision', get_class_methods($catalog));
    }
}
