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
use App\Services\Finance\Exceptions\InvalidOpeningBalanceValidationRequestException;
use App\Services\Finance\InactiveLedgerAccountProvisioningPlanValidator;
use App\Services\Finance\OpeningBalanceValidationFoundation;
use App\Services\Finance\ProposedMemberReceivablesReconciliation;
use App\Services\Finance\ProposedOpeningJournalLine;
use App\Services\Finance\ProposedOpeningPackageException;
use App\Services\Finance\ProposedOpeningPosition;
use App\Services\Finance\ProposedOpeningPrivacyAssertions;
use App\Services\Finance\ProposedUnverifiedLedgerAccountPlan;
use App\Services\Finance\ProposedUnverifiedLedgerAccountPlanItem;
use App\Services\Finance\ProposedUnverifiedOpeningPackageRequest;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Milestone3iimOpeningBalanceValidationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_versions_source_binding_and_non_authoritative_status(): void
    {
        $report = $this->validate($this->request());

        $this->assertSame('CUTOVER-EVIDENCE-PACKAGE-2026-01', $report->packageStructureVersion);
        $this->assertSame('OPENING-BALANCE-VALIDATION-2026-01', $report->validationPolicyVersion);
        $this->assertSame('CLUB-COA-2026-01', $report->catalogueVersion);
        $this->assertSame('INACTIVE-PROVISIONING-PLAN-2026-01', $report->provisioningPlanPolicyVersion);
        $this->assertSame('PACKAGE-001', $report->packageId);
        $this->assertSame($report->packageId, $report->sourceId);
        $this->assertSame('PROPOSED-UNVERIFIED', $report->status);
        $this->assertSame('BLOCKED', $report->activationStatus);
        $this->assertFalse($report->authoritative);
        $this->assertFalse($report->evidenceVerified);
        $this->assertFalse($report->accountingReviewCompleted);
        $this->assertFalse($report->authorizedForPosting);
        $this->assertFalse($report->authorizedForActivation);
        $this->assertContains('OPERATIONAL-COLLISION-CHECK-NOT-PERFORMED', $this->codes($report));
        $this->assertContains('ACTIVATION-AUTHORIZATION-ABSENT', $this->codes($report));
    }

    public function test_malformed_versions_identities_dates_references_and_source_throw(): void
    {
        foreach ([
            ['packageStructureVersion' => 'WRONG'], ['validationPolicyVersion' => 'WRONG'],
            ['catalogueVersion' => 'WRONG'], ['provisioningPlanPolicyVersion' => 'WRONG'],
            ['clubId' => 0], ['tenantId' => 0], ['packageId' => 'PRIVATE/PATH'],
            ['sourceId' => 'OTHER-PACKAGE'], ['sourceType' => 'opening'], ['sourceEvent' => 'proposed'],
            ['idempotencyCandidate' => 'NOT-A-UUID'], ['lionsYear' => '2027-29'],
            ['cutoverDate' => '2026-07-01'], ['cutoverDate' => '2027-08-01'],
            ['cutoverDate' => '2028-07-01', 'futureCutoverDecisionReference' => null],
        ] as $overrides) {
            $this->assertMalformed($this->request($overrides));
        }
    }

    public function test_later_july_requires_reference_and_reordered_input_is_deterministic(): void
    {
        $cash = $this->position('CASH', 'CASH-ON-HAND', 100, 'EVIDENCE-CASH-001');
        $liability = $this->position('LIABILITY', 'LIABILITY-RESTRICTED-COLLECTION', 100, 'EVIDENCE-LIABILITY-001', 'LIABILITY');
        $debit = $this->line('CASH-ON-HAND', 100, null, 'EVIDENCE-CASH-001');
        $credit = $this->line('LIABILITY-RESTRICTED-COLLECTION', null, 100, 'EVIDENCE-LIABILITY-001');
        $base = [
            'cutoverDate' => '2028-07-01', 'lionsYear' => '2028-29',
            'futureCutoverDecisionReference' => 'CUTOVER-DECISION-2028',
        ];
        $first = $this->validate($this->request([...$base, 'positions' => [$cash, $liability], 'journalLines' => [$debit, $credit]]));
        $second = $this->validate($this->request([...$base, 'positions' => [$liability, $cash], 'journalLines' => [$credit, $debit]]));

        $this->assertEquals($first, $second);
        $this->assertSame($first->dryRunFingerprint, $second->dryRunFingerprint);
        $this->assertSame(['CASH-ON-HAND', 'LIABILITY-RESTRICTED-COLLECTION'], array_column($first->journalLines, 'account_code'));
        $this->assertSame([1, 2], array_column($first->journalLines, 'line_number'));

        $changed = $this->validate($this->request([...$base, 'packageVersion' => 'PACKAGE-VERSION-002']));
        $this->assertNotSame($first->dryRunFingerprint, $changed->dryRunFingerprint);
    }

    public function test_income_expense_and_equity_accounts_are_blocked_for_opening(): void
    {
        foreach ([
            ['INCOME-MEMBER-DUES', 'OPENING-INCOME-ACCOUNT-PROHIBITED', null, 100],
            ['EXPENSE-ADMINISTRATION', 'OPENING-EXPENSE-ACCOUNT-PROHIBITED', 100, null],
            ['OPENING-BALANCE-EQUITY', 'DR-033-EQUITY-SELECTION-PROHIBITED', null, 100],
            ['ACCUMULATED-SURPLUS', 'DR-033-EQUITY-SELECTION-PROHIBITED', null, 100],
        ] as [$code, $blocker, $debit, $credit]) {
            $report = $this->validate($this->request([
                'positions' => [new ProposedOpeningPosition('ZERO', $code, 0, 'EVIDENCE-ZERO-001')],
                'journalLines' => [$this->line($code, $debit, $credit, 'EVIDENCE-LINE-001')],
            ], [$code]));
            $this->assertContains($blocker, $this->codes($report));
            $this->assertSame('BLOCKED', $report->activationStatus);
        }
    }

    public function test_assets_must_be_debits_liabilities_credits_and_positions_must_match(): void
    {
        $assetCredit = $this->validate($this->request([
            'journalLines' => [$this->line('CASH-ON-HAND', null, 100, 'EVIDENCE-CASH-001')],
        ]));
        $this->assertContains('OPENING-NORMAL-BALANCE-DIRECTION-MISMATCH', $this->codes($assetCredit));

        $liabilityDebit = $this->validate($this->request([
            'journalLines' => [$this->line('LIABILITY-RESTRICTED-COLLECTION', 100, null, 'EVIDENCE-LIABILITY-001')],
        ]));
        $this->assertContains('OPENING-NORMAL-BALANCE-DIRECTION-MISMATCH', $this->codes($liabilityDebit));

        $mismatch = $this->validate($this->request([
            'journalLines' => [$this->line('CASH-ON-HAND', 99, null, 'EVIDENCE-CASH-001')],
        ]));
        $this->assertContains('OPENING-POSITION-JOURNAL-MISMATCH', $this->codes($mismatch));

        $this->assertMalformed($this->request([
            'positions' => [$this->position('CASH', 'CASH-ON-HAND', -1, 'EVIDENCE-CASH-001')],
        ]));
    }

    public function test_readable_imbalances_missing_evidence_privacy_and_open_exceptions_return_blockers(): void
    {
        $report = $this->validate($this->request([
            'positions' => [$this->position('CASH', 'CASH-ON-HAND', 100, null)],
            'journalLines' => [$this->line('CASH-ON-HAND', 100, null, null)],
            'memberReceivables' => new ProposedMemberReceivablesReconciliation(100, 99, null, null),
            'exceptions' => [new ProposedOpeningPackageException('EXCEPTION-001', 'EVIDENCE', 'OPEN')],
            'privacyAssertions' => $this->privacy(false),
        ]));
        $codes = $this->codes($report);

        foreach (['JOURNAL-IMBALANCED', 'MEMBER-RECEIVABLES-MISMATCH', 'MANDATORY-EVIDENCE-UNRESOLVED',
            'PRIVACY-ASSERTION-UNVERIFIED', 'UNRESOLVED-EXCEPTION'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertSame('PROPOSED-UNVERIFIED', $report->status);
        $this->assertSame('BLOCKED', $report->activationStatus);
    }

    public function test_missing_equity_returns_deterministic_blockers_without_invention(): void
    {
        $report = $this->validate($this->request());
        $codes = $this->codes($report);

        $this->assertContains('DR-033-EQUITY-DECISION-PENDING', $codes);
        $this->assertContains('DR-033-EQUITY-LINE-REQUIRED', $codes);
        $this->assertNotContains('OPENING-BALANCE-EQUITY', array_column($report->journalLines, 'account_code'));
        $this->assertNotContains('ACCUMULATED-SURPLUS', array_column($report->journalLines, 'account_code'));
    }

    public function test_zero_positions_have_no_lines_and_bad_line_shapes_throw(): void
    {
        $report = $this->validate($this->request([
            'positions' => [$this->position('ZERO', 'CASH-ON-HAND', 0, 'EVIDENCE-ZERO-001')],
            'journalLines' => [],
        ]));
        $this->assertSame([], $report->journalLines);

        foreach ([
            $this->line('CASH-ON-HAND', 0, null, 'EVIDENCE-CASH-001'),
            $this->line('CASH-ON-HAND', null, 0, 'EVIDENCE-CASH-001'),
            $this->line('CASH-ON-HAND', 1, 1, 'EVIDENCE-CASH-001'),
            $this->line('CASH-ON-HAND', null, null, 'EVIDENCE-CASH-001'),
        ] as $line) {
            $this->assertMalformed($this->request(['journalLines' => [$line]]));
        }
    }

    public function test_duplicate_and_unsupported_canonical_identities_throw(): void
    {
        $cash = $this->position('CASH', 'CASH-ON-HAND', 100, 'EVIDENCE-CASH-001');
        $this->assertMalformed($this->request(['positions' => [$cash, $cash]]));
        $line = $this->line('CASH-ON-HAND', 100, null, 'EVIDENCE-CASH-001');
        $this->assertMalformed($this->request(['journalLines' => [$line, $line]]));
        $this->assertMalformed($this->request([
            'positions' => [$this->position('ZERO', 'SUSPENSE', 0, 'EVIDENCE-ZERO-001')],
        ]));
    }

    public function test_validation_has_no_query_persistence_audit_or_runtime_exposure(): void
    {
        $queries = 0;
        DB::listen(function (QueryExecuted $event) use (&$queries): void {
            $queries++;
        });
        $this->validate($this->request());
        $this->assertSame(0, $queries);

        foreach ([Club::class, User::class, LedgerAccount::class, ClubAccountingPeriod::class,
            ClubJournalEntry::class, ClubJournalLine::class, AuditLog::class] as $model) {
            $this->assertSame(0, $model::count());
        }
        $uris = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri): bool => str_contains($uri, 'opening-balance-validation'));
        $this->assertSame([], $uris->values()->all());
        $commands = array_filter(array_keys(Artisan::all()), fn (string $name): bool => str_contains($name, 'opening-balance-validation'));
        $this->assertSame([], array_values($commands));
    }

    private function validate(ProposedUnverifiedOpeningPackageRequest $request): object
    {
        return app(OpeningBalanceValidationFoundation::class)->validateProposedUnverifiedPackage($request);
    }

    private function request(array $overrides = [], ?array $extraCodes = null): ProposedUnverifiedOpeningPackageRequest
    {
        $codes = $extraCodes ?? ['CASH-ON-HAND', 'LIABILITY-RESTRICTED-COLLECTION'];
        $positions = [
            $this->position('CASH', 'CASH-ON-HAND', 100, 'EVIDENCE-CASH-001'),
            $this->position('LIABILITY', 'LIABILITY-RESTRICTED-COLLECTION', 100, 'EVIDENCE-LIABILITY-001', 'LIABILITY'),
        ];
        $lines = [
            $this->line('CASH-ON-HAND', 100, null, 'EVIDENCE-CASH-001'),
            $this->line('LIABILITY-RESTRICTED-COLLECTION', null, 100, 'EVIDENCE-LIABILITY-001'),
        ];

        return new ProposedUnverifiedOpeningPackageRequest(
            packageStructureVersion: $overrides['packageStructureVersion'] ?? OpeningBalanceValidationFoundation::PACKAGE_STRUCTURE_VERSION,
            validationPolicyVersion: $overrides['validationPolicyVersion'] ?? OpeningBalanceValidationFoundation::VALIDATION_POLICY_VERSION,
            catalogueVersion: $overrides['catalogueVersion'] ?? ApprovedLedgerAccountTemplateCatalog::VERSION,
            provisioningPlanPolicyVersion: $overrides['provisioningPlanPolicyVersion'] ?? InactiveLedgerAccountProvisioningPlanValidator::POLICY_VERSION,
            clubId: $overrides['clubId'] ?? 101,
            tenantId: $overrides['tenantId'] ?? 501,
            packageId: $overrides['packageId'] ?? 'PACKAGE-001',
            packageVersion: $overrides['packageVersion'] ?? 'PACKAGE-VERSION-001',
            lionsYear: $overrides['lionsYear'] ?? '2027-28',
            cutoverDate: $overrides['cutoverDate'] ?? '2027-07-01',
            futureCutoverDecisionReference: array_key_exists('futureCutoverDecisionReference', $overrides) ? $overrides['futureCutoverDecisionReference'] : null,
            sourceType: $overrides['sourceType'] ?? 'accounting_opening_package',
            sourceId: $overrides['sourceId'] ?? 'PACKAGE-001',
            sourceEvent: $overrides['sourceEvent'] ?? 'activated',
            idempotencyCandidate: $overrides['idempotencyCandidate'] ?? '123e4567-e89b-42d3-a456-426614174000',
            accountPlan: $overrides['accountPlan'] ?? $this->plan($codes),
            positions: $overrides['positions'] ?? $positions,
            memberReceivables: array_key_exists('memberReceivables', $overrides) ? $overrides['memberReceivables'] : null,
            journalLines: $overrides['journalLines'] ?? $lines,
            exceptions: $overrides['exceptions'] ?? [],
            privacyAssertions: $overrides['privacyAssertions'] ?? $this->privacy(true),
        );
    }

    private function plan(array $codes): ProposedUnverifiedLedgerAccountPlan
    {
        $definitions = collect((new ApprovedLedgerAccountTemplateCatalog)->definitions())->keyBy('templateCode');
        $items = [];
        foreach ($codes as $code) {
            $definition = $definitions->get($code);
            $items[] = new ProposedUnverifiedLedgerAccountPlanItem(
                InactiveLedgerAccountProvisioningPlanValidator::STATUS,
                $code, $code, $definition->name, $definition->accountType, $definition->normalBalance,
                $definition->currency, 'APPROVED-FUTURE-WORKFLOW', ['REFERENCE-001'],
            );
        }

        return new ProposedUnverifiedLedgerAccountPlan(
            101, 202, ApprovedLedgerAccountTemplateCatalog::VERSION,
            InactiveLedgerAccountProvisioningPlanValidator::POLICY_VERSION,
            InactiveLedgerAccountProvisioningPlanValidator::STATUS, $items,
        );
    }

    private function position(string $section, string $code, int $amount, ?string $evidence, ?string $classification = null): ProposedOpeningPosition
    {
        return new ProposedOpeningPosition($section, $code, $amount, $evidence, $classification);
    }

    private function line(string $code, ?int $debit, ?int $credit, ?string $evidence): ProposedOpeningJournalLine
    {
        return new ProposedOpeningJournalLine($code, 'ACCOUNT-'.str_replace(['<', '>'], '', $code), $debit, $credit, $evidence);
    }

    private function privacy(bool $value): ProposedOpeningPrivacyAssertions
    {
        return new ProposedOpeningPrivacyAssertions($value, $value, $value, $value, $value, $value, $value, $value, $value, $value);
    }

    private function codes(object $report): array
    {
        return array_values(array_unique(array_column($report->blockers, 'code')));
    }

    private function assertMalformed(ProposedUnverifiedOpeningPackageRequest $request): void
    {
        try {
            $this->validate($request);
            $this->fail('The malformed request should throw.');
        } catch (InvalidOpeningBalanceValidationRequestException) {
            $this->addToAssertionCount(1);
        }
    }
}
