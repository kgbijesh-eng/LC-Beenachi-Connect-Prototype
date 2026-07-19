<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\ApprovedLedgerAccountTemplateCatalog;
use App\Services\Finance\Exceptions\InvalidInactiveLedgerAccountPlanException;
use App\Services\Finance\InactiveLedgerAccountProvisioningPlanRequest;
use App\Services\Finance\InactiveLedgerAccountProvisioningPlanSelection;
use App\Services\Finance\InactiveLedgerAccountProvisioningPlanValidator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Milestone3iilControlledInactiveAccountProvisioningPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_versions_catalogue_semantics_and_proposed_unverified_flags(): void
    {
        $plan = $this->validate([$this->selection('CASH-ON-HAND', 'VERIFIED-CASH-HOLDING', [' evidence-cash-001 '])]);
        $item = $plan->items[0];

        $this->assertSame('CLUB-COA-2026-01', $plan->catalogueVersion);
        $this->assertSame('INACTIVE-PROVISIONING-PLAN-2026-01', $plan->planningPolicyVersion);
        $this->assertSame('PROPOSED-UNVERIFIED', $plan->status);
        $this->assertSame('PROPOSED-UNVERIFIED', $item->status);
        $this->assertSame(['CASH-ON-HAND', 'Cash on Hand', 'asset', 'debit', 'INR'], [
            $item->generatedAccountCode, $item->name, $item->accountType, $item->normalBalance, $item->currency,
        ]);
        $this->assertSame(['EVIDENCE-CASH-001'], $item->referenceIdentifiers);
        foreach ([$plan, $item] as $subject) {
            $this->assertFalse($subject->isActive);
            $this->assertFalse($subject->provisioned);
            $this->assertFalse($subject->authorizedForCreation);
            $this->assertFalse($subject->authorizedForActivation);
        }
    }

    public function test_versions_scope_and_non_empty_plan_fail_closed(): void
    {
        foreach ([
            ['catalogueVersion' => ''], ['catalogueVersion' => 'CLUB-COA-2026-02'],
            ['planningPolicyVersion' => ''], ['planningPolicyVersion' => 'INACTIVE-PROVISIONING-PLAN-2026-02'],
            ['clubId' => 0], ['actorUserId' => 0], ['selections' => []],
        ] as $overrides) {
            $this->invalid($this->request([$this->cash()], $overrides));
        }
    }

    public function test_exact_classification_allowlist_duplicates_and_incompatibility(): void
    {
        $this->assertSame([
            'VERIFIED-CASH-HOLDING', 'VERIFIED-BANK-HOLDING', 'RECONCILED-MEMBER-RECEIVABLES',
            'APPROVED-SOURCE-MAPPING', 'APPROVED-FUTURE-WORKFLOW', 'APPROVED-OPENING-EQUITY-SELECTION',
        ], InactiveLedgerAccountProvisioningPlanValidator::ELIGIBILITY_CLASSIFICATIONS);

        foreach ([[], ['verified-cash-holding'], ['UNKNOWN'],
            ['VERIFIED-CASH-HOLDING', 'VERIFIED-CASH-HOLDING'],
            ['VERIFIED-CASH-HOLDING', 'APPROVED-SOURCE-MAPPING']] as $classifications) {
            $this->invalid($this->request([new InactiveLedgerAccountProvisioningPlanSelection(
                'CASH-ON-HAND', $classifications, ['EVIDENCE-CASH-001'],
            )]));
        }
        $this->invalid($this->request([
            $this->selection('CASH-ON-HAND', 'VERIFIED-BANK-HOLDING', ['EVIDENCE-CASH-001']),
        ]));
    }

    public function test_references_are_opaque_canonical_sorted_and_strict(): void
    {
        $plan = $this->validate([$this->selection('CASH-ON-HAND', 'VERIFIED-CASH-HOLDING', [
            ' evidence-cash-002 ', 'evidence-cash-001',
        ])]);
        $this->assertSame(['EVIDENCE-CASH-001', 'EVIDENCE-CASH-002'], $plan->items[0]->referenceIdentifiers);

        foreach ([[], ['A'], [str_repeat('A', 81)], ['FILE.PDF'], ['C:\\PRIVATE\\FILE'],
            ['HTTPS://EXAMPLE.TEST'], ['MEMBER NAME'], ['123456'], ['RÉFÉRENCE'],
            ['EVIDENCE-CASH-001', ' evidence-cash-001 ']] as $references) {
            $this->invalid($this->request([
                $this->selection('CASH-ON-HAND', 'VERIFIED-CASH-HOLDING', $references),
            ]));
        }
    }

    public function test_reordered_input_has_identical_deterministic_output(): void
    {
        $cash = $this->cash();
        $bank = $this->selection('BANK-OPERATING', 'VERIFIED-BANK-HOLDING', ['BANK-INVENTORY-001']);
        $receivable = $this->selection('MEMBER-RECEIVABLES', 'RECONCILED-MEMBER-RECEIVABLES', ['RECEIVABLES-RECON-001']);
        $first = $this->validate([$receivable, $cash, $bank]);
        $second = $this->validate([$bank, $receivable, $cash]);

        $this->assertEquals($first, $second);
        $this->assertSame(['BANK-OPERATING', 'CASH-ON-HAND', 'MEMBER-RECEIVABLES'],
            array_column($first->items, 'generatedAccountCode'));
    }

    public function test_duplicate_unknown_and_forbidden_accounts_fail(): void
    {
        $this->invalid($this->request([$this->cash(), $this->cash()]));
        $this->invalid($this->request([
            $this->selection(ApprovedLedgerAccountTemplateCatalog::ADDITIONAL_BANK_TEMPLATE,
                'VERIFIED-BANK-HOLDING', ['BANK-INVENTORY-001'], 'SERVICE'),
            $this->selection(ApprovedLedgerAccountTemplateCatalog::ADDITIONAL_BANK_TEMPLATE,
                'VERIFIED-BANK-HOLDING', ['BANK-INVENTORY-002'], ' service '),
        ]));
        foreach (['SUSPENSE', 'INCOME-OTHER-APPROVED', 'UNALLOCATED-RECEIPT', 'MANUAL-JOURNAL'] as $code) {
            $this->invalid($this->request([
                $this->selection($code, 'APPROVED-FUTURE-WORKFLOW', ['WORKFLOW-APPROVAL-001']),
            ]));
        }
    }

    public function test_bank_alias_reuses_catalogue_validation_and_fixed_bank_rejects_alias(): void
    {
        $item = $this->validate([$this->selection(
            ApprovedLedgerAccountTemplateCatalog::ADDITIONAL_BANK_TEMPLATE,
            'VERIFIED-BANK-HOLDING', ['BANK-INVENTORY-001'], ' service-activity ',
        )])->items[0];
        $this->assertSame('BANK-SERVICE-ACTIVITY', $item->generatedAccountCode);

        foreach ([null, 'BANK-SERVICE', 'ACCOUNT-ENDING', 'OPERATING', 'SERVICE--ACTIVITY'] as $alias) {
            $this->invalid($this->request([$this->selection(
                ApprovedLedgerAccountTemplateCatalog::ADDITIONAL_BANK_TEMPLATE,
                'VERIFIED-BANK-HOLDING', ['BANK-INVENTORY-001'], $alias,
            )]));
        }
        $this->invalid($this->request([$this->selection(
            'BANK-OPERATING', 'VERIFIED-BANK-HOLDING', ['BANK-INVENTORY-001'], 'SERVICE',
        )]));
    }

    public function test_account_specific_rules_and_pending_equity_stop(): void
    {
        foreach ([
            ['MEMBER-RECEIVABLES', 'RECONCILED-MEMBER-RECEIVABLES', 'RECEIVABLES-RECON-001'],
            ['INCOME-MEMBER-DUES', 'APPROVED-SOURCE-MAPPING', 'DUE-MAPPING-2027-01'],
            ['EXPENSE-ADMINISTRATION', 'APPROVED-FUTURE-WORKFLOW', 'WORKFLOW-APPROVAL-001'],
        ] as [$code, $classification, $reference]) {
            $this->assertSame($classification, $this->validate([
                $this->selection($code, $classification, [$reference]),
            ])->items[0]->eligibilityClassification);
        }
        foreach (['OPENING-BALANCE-EQUITY', 'ACCUMULATED-SURPLUS'] as $code) {
            $this->invalid($this->request([$this->selection(
                $code, 'APPROVED-OPENING-EQUITY-SELECTION', ['EQUITY-REVIEW-001'],
            )]));
        }
    }

    public function test_validation_executes_no_query_and_creates_no_record_audit_or_exposure(): void
    {
        $queries = 0;
        DB::listen(function (QueryExecuted $event) use (&$queries): void {
            $queries++;
        });
        $this->validate([$this->cash()]);
        $this->assertSame(0, $queries);

        foreach ([Club::class, User::class, LedgerAccount::class, ClubAccountingPeriod::class,
            ClubJournalEntry::class, ClubJournalLine::class, AuditLog::class] as $model) {
            $this->assertSame(0, $model::count());
        }
        $uris = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri): bool => str_contains($uri, 'provisioning-plan'));
        $this->assertSame([], $uris->values()->all());
        $commands = array_filter(array_keys(Artisan::all()), fn (string $name): bool => str_contains($name, 'provisioning-plan'));
        $this->assertSame([], array_values($commands));
    }

    private function validate(array $selections): object
    {
        return app(InactiveLedgerAccountProvisioningPlanValidator::class)->validateProposedUnverifiedPlan(
            $this->request($selections),
        );
    }

    private function request(array $selections, array $overrides = []): InactiveLedgerAccountProvisioningPlanRequest
    {
        return new InactiveLedgerAccountProvisioningPlanRequest(
            clubId: $overrides['clubId'] ?? 101,
            actorUserId: $overrides['actorUserId'] ?? 202,
            catalogueVersion: $overrides['catalogueVersion'] ?? ApprovedLedgerAccountTemplateCatalog::VERSION,
            planningPolicyVersion: $overrides['planningPolicyVersion'] ?? InactiveLedgerAccountProvisioningPlanValidator::POLICY_VERSION,
            selections: $overrides['selections'] ?? $selections,
        );
    }

    private function cash(): InactiveLedgerAccountProvisioningPlanSelection
    {
        return $this->selection('CASH-ON-HAND', 'VERIFIED-CASH-HOLDING', ['EVIDENCE-CASH-001']);
    }

    private function selection(string $code, string $classification, array $references, ?string $alias = null): InactiveLedgerAccountProvisioningPlanSelection
    {
        return new InactiveLedgerAccountProvisioningPlanSelection($code, [$classification], $references, $alias);
    }

    private function invalid(InactiveLedgerAccountProvisioningPlanRequest $request): void
    {
        try {
            app(InactiveLedgerAccountProvisioningPlanValidator::class)->validateProposedUnverifiedPlan($request);
            $this->fail('Invalid proposed plan should fail closed.');
        } catch (InvalidInactiveLedgerAccountPlanException) {
            $this->addToAssertionCount(1);
        }
    }
}
