<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubAccountingSourceIntegration;
use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubCharge;
use App\Models\ClubJournalEntry;
use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\MemberCharge;
use App\Models\MemberPayment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReceipt;
use App\Models\Permission;
use App\Models\User;
use App\Services\Finance\AccountingIntegrationConfigurationService;
use App\Services\Finance\AccountingIntegrationReconciliationService;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\Exceptions\AccountingIntegrationConflictException;
use App\Services\Finance\Exceptions\AccountingIntegrationException;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use App\Services\Finance\SourceAccountingIntegrationService;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\FinanceCollections\FinanceWriteService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Support\FinancialPostgresTestCase;

class Milestone3iizhChargePaymentAccountingIntegrationTest extends FinancialPostgresTestCase
{

    public function test_integrations_are_disabled_by_default_and_posted_opening_alone_changes_no_source_behaviour(): void
    {
        $f = $this->fixture();
        $charge = $this->draftCharge($f);
        app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $charge, $charge->lock_version);
        $memberCharge = MemberCharge::firstOrFail();
        app(FinanceWriteService::class)->recordPayment($f['actor'], $f['club'], $f['member'], $this->paymentPayload($memberCharge));

        $this->assertSame(0, ClubAccountingSourceIntegration::count());
        $this->assertSame(1, ClubJournalEntry::count(), 'Only the opening journal may exist while source integration is disabled.');
    }

    public function test_enabled_charge_and_payment_post_exact_balanced_entries_and_replay_without_duplicates(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $charge = $this->draftCharge($f);
        $issued = $write->issue($f['actor'], $f['club'], $charge, $charge->lock_version);
        $chargeReplay = $write->issue($f['actor'], $f['club'], $charge, $charge->lock_version);
        $this->assertSame($issued->id, $chargeReplay->id);
        $chargeLink = ClubAccountingSourceIntegration::where('source_type', 'member_charge')->firstOrFail();
        $this->assertJournal($chargeLink->journalEntry, $f['receivable']->id, $f['income']->id, 50000);
        $memberCharge = MemberCharge::firstOrFail();
        $payload = $this->paymentPayload($memberCharge);
        $first = $write->recordPayment($f['actor'], $f['club'], $f['member'], $payload);
        $second = $write->recordPayment($f['actor'], $f['club'], $f['member'], $payload);
        $paymentLink = ClubAccountingSourceIntegration::where('source_type', 'member_payment')->firstOrFail();
        $this->assertFalse($first['replayed']);
        $this->assertTrue($second['replayed']);
        $this->assertSame($first['payment']->id, $second['payment']->id);
        $this->assertJournal($paymentLink->journalEntry, $f['bank']->id, $f['receivable']->id, 50000);
        $this->assertSame(1, MemberPayment::count());
        $this->assertSame(1, PaymentAllocation::count());
        $this->assertSame(1, PaymentReceipt::count());
        $this->assertSame([], app(AccountingIntegrationReconciliationService::class)->defects($f['club']->id));
        $this->assertSame('issued', $issued->status);
    }

    public function test_failure_after_journal_creation_rolls_back_payment_allocation_receipt_journal_link_and_event(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $memberCharge = MemberCharge::firstOrFail();
        DB::listen(function ($query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert into') && str_contains($query->sql, 'club_accounting_source_integration_events')) {
                throw new \RuntimeException('Synthetic linkage failure.');
            }
        });
        try {
            $write->recordPayment($f['actor'], $f['club'], $f['member'], $this->paymentPayload($memberCharge));
            $this->fail('The synthetic linkage failure must escape.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic linkage failure.', $exception->getMessage());
        }
        $this->assertSame(0, MemberPayment::count());
        $this->assertSame(0, PaymentAllocation::count());
        $this->assertSame(0, PaymentReceipt::count());
        $this->assertSame(2, ClubJournalEntry::count(), 'Only opening and committed charge journals may remain.');
        $this->assertSame(1, ClubAccountingSourceIntegration::count());
        $this->assertSame(1, DB::table('club_accounting_source_integration_events')->count());
    }

    public function test_journal_or_linkage_failure_rolls_back_charge_source_mutation(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $charge = $this->draftCharge($f);
        DB::listen(function ($query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert into') && str_contains($query->sql, 'club_journal_entries')) {
                throw new \RuntimeException('Synthetic journal failure.');
            }
        });
        try {
            app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $charge, 1);
            $this->fail('Injected journal failure must escape.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic journal failure.', $e->getMessage());
        }
        $this->assertSame('draft', $charge->fresh()->status);
        $this->assertSame(0, MemberCharge::where('club_charge_id', $charge->id)->count());
        $this->assertSame(1, ClubJournalEntry::where('club_id', $f['club']->id)->count());
        $this->assertSame(0, ClubAccountingSourceIntegration::where('club_id', $f['club']->id)->count());
    }

    public function test_linkage_failure_rolls_back_charge_source_and_journal(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $charge = $this->draftCharge($f);
        DB::listen(function ($query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert into') && str_contains($query->sql, 'club_accounting_source_integrations')) {
                throw new \RuntimeException('Synthetic linkage failure.');
            }
        });
        try {
            app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $charge, 1);
            $this->fail('Injected linkage failure must escape.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic linkage failure.', $e->getMessage());
        }
        $this->assertSame('draft', $charge->fresh()->status);
        $this->assertSame(0, MemberCharge::where('club_charge_id', $charge->id)->count());
        $this->assertSame(1, ClubJournalEntry::where('club_id', $f['club']->id)->count());
        $this->assertSame(0, ClubAccountingSourceIntegration::where('club_id', $f['club']->id)->count());
    }

    public function test_audit_failure_rolls_back_source_journal_linkage_and_success_event(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $charge = $this->draftCharge($f);
        DB::listen(function ($query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert into') && str_contains($query->sql, 'audit_logs')) {
                throw new \RuntimeException('Synthetic audit failure.');
            }
        });
        try {
            app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $charge, 1);
            $this->fail('Audit failure must escape.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic audit failure.', $e->getMessage());
        }
        $this->assertSame('draft', $charge->fresh()->status);
        $this->assertSame(1, ClubJournalEntry::where('club_id', $f['club']->id)->count());
        $this->assertSame(0, ClubAccountingSourceIntegration::where('club_id', $f['club']->id)->count());
        $this->assertSame(0, DB::table('club_accounting_source_integration_events')->where('club_id', $f['club']->id)->count());
    }

    public function test_missing_ambiguous_invalid_cross_club_precoverage_and_closed_period_fail_atomically(): void
    {
        foreach (['missing', 'ambiguous', 'closed'] as $case) {
            $f = $this->fixture();
            $config = app(AccountingIntegrationConfigurationService::class);
            $coverage = '2027-07-01';
            $config->activate($f['club']->id, 'member_charge', $coverage, 'MAP-V1', "ACT-{$case}", $f['actor']);
            if ($case !== 'missing') {
                $config->map($f['club']->id, 'member_charge', 'membership_dues', $f['income'], 'MAP-V1', '2027-07-01', "MAP-{$case}-1", $f['actor']);
            }
            if ($case === 'ambiguous') {
                ClubAccountingSourceMapping::create(['club_id' => $f['club']->id, 'source_type' => 'member_charge', 'canonical_source_key' => 'membership_dues', 'ledger_account_id' => $f['income']->id, 'mapping_version' => 'MAP-V1', 'effective_date' => '2027-07-01', 'approval_reference' => 'MAP-ambiguous-2', 'created_by_user_id' => $f['actor']->id, 'approved_at' => now()]);
            }
            if ($case === 'closed') {
                $f['period']->update(['status' => 'closed', 'closed_at' => now(), 'closed_by_user_id' => $f['actor']->id]);
            }
            $charge = $this->draftCharge($f);
            try {
                app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $charge, $charge->lock_version);
                $this->fail("{$case} must fail closed.");
            } catch (AccountingIntegrationException) {
                $this->addToAssertionCount(1);
            }
            $this->assertSame('draft', $charge->fresh()->status);
            $this->assertSame(0, MemberCharge::where('club_id', $f['club']->id)->count());
            $this->assertSame(1, ClubJournalEntry::where('club_id', $f['club']->id)->count());
        }
    }

    public function test_mapping_creation_rejects_wrong_type_and_cross_club_accounts(): void
    {
        $f = $this->fixture();
        $other = Club::create(['name' => 'Other', 'code' => 'OTH', 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        $foreign = LedgerAccount::create(['club_id' => $other->id, 'code' => 'INCOME-DUES', 'name' => 'Foreign', 'account_type' => 'income', 'normal_balance' => 'credit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $f['actor']->id]);
        foreach ([$f['bank'], $foreign] as $account) {
            try {
                app(AccountingIntegrationConfigurationService::class)->map($f['club']->id, 'member_charge', 'membership_dues', $account, 'MAP-V1', '2027-07-01', 'INVALID-'.$account->id, $f['actor']);
                $this->fail('Invalid mapping must fail.');
            } catch (AccountingIntegrationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_configuration_inputs_are_normalized_and_rejected_before_persistence(): void
    {
        $f = $this->fixture();
        $config = app(AccountingIntegrationConfigurationService::class);
        foreach ([['2027-02-30', 'MAP-V1', 'ACT-X'], ['2027-7-01', 'MAP-V1', 'ACT-Y'], ['2027-07-01', '   ', 'ACT-Z'], ['2027-07-01', 'MAP-V1', '   ']] as [$date, $version, $reference]) {
            try {
                $config->activate($f['club']->id, 'member_charge', $date, $version, $reference, $f['actor']);
                $this->fail('Invalid activation input must fail before insertion.');
            } catch (AccountingIntegrationException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach ([['invalid', '2027-07-01', null, 'MAP-V1', 'MAP-X'], ['membership_dues', '2027-02-30', null, 'MAP-V1', 'MAP-Y'], ['membership_dues', '2027-07-02', '2027-07-01', 'MAP-V1', 'MAP-Z'], ['membership_dues', '2027-07-01', null, str_repeat('x', 41), 'MAP-Q'], ['membership_dues', '2027-07-01', null, 'MAP-V1', '   ']] as [$key, $effective, $expiry, $version, $reference]) {
            try {
                $config->map($f['club']->id, 'member_charge', $key, $f['income'], $version, $effective, $reference, $f['actor'], $expiry);
                $this->fail('Invalid mapping input must fail before insertion.');
            } catch (AccountingIntegrationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('club_accounting_integration_activations')->where('club_id', $f['club']->id)->count());
        $this->assertSame(0, DB::table('club_accounting_source_mappings')->where('club_id', $f['club']->id)->count());
    }

    public function test_enabled_reversal_uses_linked_journal_reversal(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $original = ClubAccountingSourceIntegration::where('source_type', 'member_charge')->firstOrFail()->journalEntry;
        $write->cancel($f['actor'], $f['club'], $charge, $charge->lock_version, 'Correction');
        $this->assertSame('reversed', $original->fresh()->status);
        $this->assertDatabaseHas('club_journal_entries', ['reversal_of_entry_id' => $original->id, 'source_event' => 'reversed']);
        $this->assertDatabaseHas('club_accounting_source_integration_events', ['source_integration_id' => ClubAccountingSourceIntegration::where('source_type', 'member_charge')->value('id'), 'event_type' => 'integration_reversal']);
    }

    public function test_exact_reversal_event_replay_is_idempotent_and_changed_replay_conflicts(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $write->cancel($f['actor'], $f['club'], $charge, 2, 'Correction');
        $service = app(SourceAccountingIntegrationService::class);
        app(OrganisationTransactionGuard::class)->run($f['club'], fn ($context) => $service->reverseWithin($context, $f['club']->id, 'member_charge', $charge->public_id, $charge->issued_at->toDateString(), $charge->issued_at->toISOString(), now()->toDateString(), $f['actor'], 'Correction'));
        $this->assertSame(1, DB::table('club_accounting_source_integration_events')->where('event_type', 'integration_reversal')->count());
        try {
            app(OrganisationTransactionGuard::class)->run($f['club'], fn ($context) => $service->reverseWithin($context, $f['club']->id, 'member_charge', $charge->public_id, $charge->issued_at->toDateString(), $charge->issued_at->toISOString(), now()->toDateString(), $f['actor'], 'Changed'));
            $this->fail('Changed reversal replay must conflict.');
        } catch (AccountingIntegrationConflictException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_corrupt_reversal_event_cannot_short_circuit_authoritative_replay(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Controlled corrupt-fixture test uses a disposable SQLite trigger override.');
        }
        $f = $this->fixture();
        $this->enable($f);
        $charge = app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        app(FinanceWriteService::class)->cancel($f['actor'], $f['club'], $charge, 2, 'Correction');
        $integration = ClubAccountingSourceIntegration::where('source_identifier', $charge->public_id)->firstOrFail();
        DB::unprepared('DROP TRIGGER club_accounting_source_integration_events_immutable_update');
        try {
            DB::table('club_accounting_source_integration_events')->where('source_integration_id', $integration->id)->where('event_type', 'integration_reversal')->update(['reversal_journal_entry_id' => ClubJournalEntry::where('club_id', $f['club']->id)->whereNull('reversal_of_entry_id')->oldest('id')->value('id')]);
            try {
                app(OrganisationTransactionGuard::class)->run($f['club'], fn ($context) => app(SourceAccountingIntegrationService::class)->reverseWithin($context, $f['club']->id, 'member_charge', $charge->public_id, $charge->issued_at->toDateString(), $charge->issued_at->toISOString(), now()->toDateString(), $f['actor'], 'Correction'));
                $this->fail('A corrupt reversal event must conflict.');
            } catch (AccountingIntegrationConflictException) {
                $this->addToAssertionCount(1);
            }
        } finally {
            DB::unprepared("CREATE TRIGGER club_accounting_source_integration_events_immutable_update BEFORE UPDATE ON club_accounting_source_integration_events BEGIN SELECT RAISE(ABORT, 'club_accounting_source_integration_events_immutable'); END");
        }
    }

    public function test_activation_rejects_existing_unjournalled_covered_charge_and_payment(): void
    {
        foreach (['member_charge', 'member_payment'] as $source) {
            $f = $this->fixture();
            $write = app(FinanceWriteService::class);
            $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
            if ($source === 'member_payment') {
                $write->recordPayment($f['actor'], $f['club'], $f['member'], $this->paymentPayload(MemberCharge::where('club_charge_id', $charge->id)->firstOrFail()));
            }
            try {
                app(AccountingIntegrationConfigurationService::class)->activate($f['club']->id, $source, '2027-07-01', 'MAP-V1', 'BACKDATED-'.$source, $f['actor']);
                $this->fail('Backdated activation must reject a covered unjournalled source.');
            } catch (AccountingIntegrationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_same_clock_revocation_and_source_commit_follow_lock_ordered_coverage_decisions(): void
    {
        $before = $this->fixture();
        $this->enable($before);
        $activation = ClubAccountingIntegrationActivation::where('club_id', $before['club']->id)->where('source_type', 'member_charge')->firstOrFail();
        app(AccountingIntegrationConfigurationService::class)->revokeActivation($activation, $before['actor'], 'SAME-CLOCK-BEFORE', 'Stopped');
        $outside = app(FinanceWriteService::class)->issue($before['actor'], $before['club'], $this->draftCharge($before), 1);
        $this->assertDatabaseHas('club_accounting_source_coverage_decisions', ['club_id' => $before['club']->id, 'source_identifier' => $outside->public_id, 'integration_required' => false, 'decision_reason' => 'after_terminal_event', 'terminal_activation_event_id' => $activation->events()->value('id')]);
        $this->assertDatabaseMissing('club_accounting_source_integrations', ['club_id' => $before['club']->id, 'source_identifier' => $outside->public_id]);

        $after = $this->fixture();
        $this->enable($after);
        $covered = app(FinanceWriteService::class)->issue($after['actor'], $after['club'], $this->draftCharge($after), 1);
        $activation = ClubAccountingIntegrationActivation::where('club_id', $after['club']->id)->where('source_type', 'member_charge')->firstOrFail();
        app(AccountingIntegrationConfigurationService::class)->revokeActivation($activation, $after['actor'], 'SAME-CLOCK-AFTER', 'Stopped');
        $this->assertDatabaseHas('club_accounting_source_coverage_decisions', ['club_id' => $after['club']->id, 'source_identifier' => $covered->public_id, 'integration_required' => true, 'decision_reason' => 'integration_required', 'integration_activation_id' => $activation->id]);
        $this->assertDatabaseHas('club_accounting_source_integrations', ['club_id' => $after['club']->id, 'source_identifier' => $covered->public_id]);
    }

    public function test_same_clock_later_activation_and_mapping_revocations_do_not_invalidate_history(): void
    {
        foreach ([['member_charge', 'activation'], ['member_payment', 'activation'], ['member_charge', 'mapping'], ['member_payment', 'mapping']] as $index => [$sourceType, $kind]) {
            $f = $this->fixture();
            $this->enable($f);
            $write = app(FinanceWriteService::class);
            $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
            if ($sourceType === 'member_payment') {
                $write->recordPayment($f['actor'], $f['club'], $f['member'], $this->paymentPayload(MemberCharge::where('club_charge_id', $charge->id)->firstOrFail()));
            }
            $config = app(AccountingIntegrationConfigurationService::class);
            if ($kind === 'activation') {
                $row = ClubAccountingIntegrationActivation::where('club_id', $f['club']->id)->where('source_type', $sourceType)->firstOrFail();
                $config->revokeActivation($row, $f['actor'], 'SAME-HISTORY-ACT-'.$index, 'Later terminal event');
            } else {
                $row = ClubAccountingSourceMapping::where('club_id', $f['club']->id)->where('source_type', $sourceType)->firstOrFail();
                $config->revokeMapping($row, $f['actor'], 'SAME-HISTORY-MAP-'.$index, 'Later terminal event');
            }
            $this->assertSame([], app(AccountingIntegrationReconciliationService::class)->defects($f['club']->id));
        }
    }

    public function test_integrated_charge_and_payment_reverse_after_activation_revocation(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $payment = $write->recordPayment($f['actor'], $f['club'], $f['member'], $this->paymentPayload(MemberCharge::firstOrFail()))['payment'];
        CarbonImmutable::setTestNow('2027-07-06 10:00:00');
        $config = app(AccountingIntegrationConfigurationService::class);
        foreach (DB::table('club_accounting_integration_activations')->where('club_id', $f['club']->id)->get() as $activation) {
            $config->revokeActivation(ClubAccountingIntegrationActivation::findOrFail($activation->id), $f['actor'], 'REVOKE-'.$activation->id, 'Controlled stop');
        }
        foreach (ClubAccountingSourceMapping::where('club_id', $f['club']->id)->get() as $mapping) {
            $config->revokeMapping($mapping, $f['actor'], 'REVOKE-MAP-'.$mapping->id, 'Controlled stop');
        }
        $write->void($f['actor'], $f['club'], $payment, $payment->lock_version, 'Correction');
        $write->cancel($f['actor'], $f['club'], $charge, $charge->lock_version, 'Correction');
        $this->assertSame(2, DB::table('club_accounting_source_integration_events')->where('event_type', 'integration_reversal')->count());
        $this->assertSame([], app(AccountingIntegrationReconciliationService::class)->defects($f['club']->id));
    }

    public function test_legacy_precoverage_and_post_revocation_unintegrated_sources_may_be_corrected(): void
    {
        $f = $this->fixture();
        $write = app(FinanceWriteService::class);
        $legacy = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $config = app(AccountingIntegrationConfigurationService::class);
        $config->map($f['club']->id, 'member_charge', 'membership_dues', $f['income'], 'MAP-V1', '2027-07-01', 'LEGACY-MAP', $f['actor']);
        $activation = $config->activate($f['club']->id, 'member_charge', '2027-07-10', 'MAP-V1', 'LEGACY-ACT', $f['actor']);
        $write->cancel($f['actor'], $f['club'], $legacy, $legacy->lock_version, 'Legacy correction');
        $config->revokeActivation($activation, $f['actor'], 'LEGACY-REVOKE', 'Stopped');
        $postRevocation = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $write->cancel($f['actor'], $f['club'], $postRevocation, $postRevocation->lock_version, 'Post-revocation correction');
        $this->assertSame(0, ClubAccountingSourceIntegration::where('club_id', $f['club']->id)->count());
    }

    public function test_legacy_precoverage_and_post_revocation_unintegrated_payments_may_be_voided(): void
    {
        $f = $this->fixture();
        $write = app(FinanceWriteService::class);
        $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $memberCharge = MemberCharge::where('club_charge_id', $charge->id)->firstOrFail();
        $legacy = $write->recordPayment($f['actor'], $f['club'], $f['member'], $this->paymentPayload($memberCharge))['payment'];
        $config = app(AccountingIntegrationConfigurationService::class);
        $config->map($f['club']->id, 'member_payment', 'bank_transfer', $f['bank'], 'MAP-V1', '2027-07-01', 'LEGACY-PAY-MAP', $f['actor']);
        $activation = $config->activate($f['club']->id, 'member_payment', '2027-07-10', 'MAP-V1', 'LEGACY-PAY-ACT', $f['actor']);
        $write->void($f['actor'], $f['club'], $legacy, 1, 'Legacy correction');
        CarbonImmutable::setTestNow('2027-07-11 10:00:00');
        $config->revokeActivation($activation, $f['actor'], 'LEGACY-PAY-REVOKE', 'Stopped');
        $payload = $this->paymentPayload($memberCharge);
        $payload['payment_date'] = '2027-07-11';
        $payload['idempotency_key'] = '22222222-2222-4222-8222-222222222222';
        $postRevocation = $write->recordPayment($f['actor'], $f['club'], $f['member'], $payload)['payment'];
        $write->void($f['actor'], $f['club'], $postRevocation, 1, 'Post-revocation correction');
        $this->assertSame(0, ClubAccountingSourceIntegration::where('club_id', $f['club']->id)->count());
    }

    public function test_missing_in_coverage_linkage_fails_closed_and_reversal_event_failure_rolls_back(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $original = ClubAccountingSourceIntegration::where('source_type', 'member_charge')->firstOrFail()->journalEntry;
        DB::listen(function ($query): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert into') && str_contains($query->sql, 'club_accounting_source_integration_events') && str_contains(json_encode($query->bindings), 'integration_reversal')) {
                throw new \RuntimeException('Synthetic reversal-event failure.');
            }
        });
        try {
            $write->cancel($f['actor'], $f['club'], $charge, $charge->lock_version, 'Correction');
            $this->fail('Reversal event failure must escape.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic reversal-event failure.', $e->getMessage());
        }
        $this->assertSame('issued', $charge->fresh()->status);
        $this->assertSame('posted', $original->fresh()->status);
        $this->assertDatabaseMissing('club_journal_entries', ['reversal_of_entry_id' => $original->id]);

        $missing = $this->draftCharge($f);
        $missing->update(['status' => 'issued', 'issued_at' => now(), 'issued_by_user_id' => $f['actor']->id, 'lock_version' => 2]);
        try {
            $write->cancel($f['actor'], $f['club'], $missing->fresh(), 2, 'Must fail');
            $this->fail('A covered source without linkage must fail closed.');
        } catch (AccountingIntegrationException) {
            $this->assertSame('issued', $missing->fresh()->status);
        }
    }

    public function test_database_rejects_cross_club_events_and_invalid_values(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        config(['instance.organisation_code' => $f['club']->code]);
        $config = app(AccountingIntegrationConfigurationService::class);
        $mapping = $config->map($f['club']->id, 'member_charge', 'membership_dues', $f['income'], 'MAP-V1', '2027-07-01', 'CROSS-MAP', $f['actor']);
        $activation = $config->activate($f['club']->id, 'member_charge', '2027-07-01', 'MAP-V1', 'CROSS-ACT', $f['actor']);
        foreach ([['club_accounting_integration_activation_events', 'integration_activation_id', $activation->id], ['club_accounting_source_mapping_events', 'source_mapping_id', $mapping->id]] as [$table, $column, $id]) {
            try {
                DB::transaction(fn () => DB::table($table)->insert(['public_id' => (string) Str::uuid(), 'club_id' => $other['club']->id, $column => $id, 'event_type' => 'revoked', 'event_reference' => 'CROSS-'.$table, 'reason' => 'x', 'recorded_by_user_id' => $f['actor']->id, 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]));
                $this->fail('Cross-club event must be rejected.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $charge = app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $integration = ClubAccountingSourceIntegration::where('source_identifier', $charge->public_id)->firstOrFail();
        try {
            DB::transaction(fn () => DB::table('club_accounting_source_integration_events')->insert(['public_id' => (string) Str::uuid(), 'club_id' => $other['club']->id, 'source_integration_id' => $integration->id, 'event_type' => 'integration_success', 'payload' => '{}', 'recorded_by_user_id' => $f['actor']->id, 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]));
            $this->fail('Cross-club integration event must be rejected.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_database_rejects_invalid_coverage_shapes_and_cross_source_bindings(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $chargeActivation = ClubAccountingIntegrationActivation::where('club_id', $f['club']->id)->where('source_type', 'member_charge')->firstOrFail();
        $paymentActivation = ClubAccountingIntegrationActivation::where('club_id', $f['club']->id)->where('source_type', 'member_payment')->firstOrFail();
        $terminal = app(AccountingIntegrationConfigurationService::class)->revokeActivation($chargeActivation, $f['actor'], 'SHAPE-TERMINAL', 'Stopped');
        $base = ['public_id' => (string) Str::uuid(), 'club_id' => $f['club']->id, 'source_type' => 'member_charge', 'source_identifier' => (string) Str::uuid(), 'accounting_date' => '2027-07-05', 'integration_required' => false, 'integration_activation_id' => null, 'terminal_activation_event_id' => null, 'decision_reason' => 'not_activated', 'decided_by_user_id' => $f['actor']->id, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        $cases = [
            array_replace($base, ['integration_activation_id' => $chargeActivation->id]),
            array_replace($base, ['decision_reason' => 'before_coverage']),
            array_replace($base, ['decision_reason' => 'integration_required', 'integration_required' => false, 'integration_activation_id' => $chargeActivation->id]),
            array_replace($base, ['decision_reason' => 'after_terminal_event', 'integration_activation_id' => $chargeActivation->id]),
            array_replace($base, ['source_type' => 'member_payment', 'integration_activation_id' => $chargeActivation->id, 'decision_reason' => 'before_coverage']),
            array_replace($base, ['source_type' => 'member_payment', 'integration_activation_id' => $paymentActivation->id, 'terminal_activation_event_id' => $terminal->id, 'decision_reason' => 'after_terminal_event']),
        ];
        foreach ($cases as $index => $row) {
            $row['public_id'] = (string) Str::uuid();
            $row['source_identifier'] = (string) Str::uuid();
            try {
                DB::transaction(fn () => DB::table('club_accounting_source_coverage_decisions')->insert($row));
                $this->fail("Invalid coverage decision {$index} must fail.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_scope_adds_no_delivery_surface_or_default_role_authority(): void
    {
        $this->assertCount(0, collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'accounting-integration')));
        $this->assertSame(0, Permission::where('slug', 'like', 'finance.accounting-integration.%')->count());
        foreach (['app/Http/Controllers', 'app/Console/Commands', 'app/Jobs'] as $path) {
            $files = glob(base_path($path.'/*')) ?: [];
            $this->assertFalse(collect($files)->contains(fn ($file) => str_contains(strtolower(basename($file)), 'accountingintegration')));
        }
    }

    public function test_sqlite_public_source_identity_guards_are_canonical_null_aware_and_immutable(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-specific trigger verification.');
        }
        $f = $this->fixture();
        $chargeBase = ['club_id' => $f['club']->id, 'title' => 'Direct', 'category' => 'membership_dues', 'amount_paise' => 1, 'due_date' => '2027-07-31', 'financial_year' => '2027-28', 'status' => 'draft', 'assignment_member_ids' => '[]', 'created_by_user_id' => $f['actor']->id, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()];
        $paymentBase = ['club_id' => $f['club']->id, 'member_id' => $f['member']->id, 'amount_paise' => 1, 'payment_date' => '2027-07-05', 'payment_mode' => 'cash', 'request_hash' => str_repeat('a', 64), 'status' => 'recorded', 'recorded_by_user_id' => $f['actor']->id, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()];
        $invalid = [null, '-2345678-1234-1234-1234-123456789012', '1234567--1234-1234-1234-123456789012', '12345678-1234-1234-1234-12345678901-', '12345678-1234-1234-1234-12345678901G', '12345678-1234-1234-1234-12345678901A'];
        foreach (['club_charges' => $chargeBase, 'member_payments' => $paymentBase] as $table => $base) {
            foreach ($invalid as $publicId) {
                try {
                    $row = $base + ['public_id' => $publicId];
                    if ($table === 'member_payments') {
                        $row['idempotency_key'] = (string) Str::uuid();
                    }
                    DB::transaction(fn () => DB::table($table)->insert($row));
                    $this->fail('Invalid public identity insert must be rejected.');
                } catch (QueryException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
        $charge = $this->draftCharge($f);
        $payment = MemberPayment::create($paymentBase + ['idempotency_key' => (string) Str::uuid()]);
        foreach ([['club_charges', $charge->id], ['member_payments', $payment->id]] as [$table, $id]) {
            foreach ([(string) Str::uuid(), null] as $replacement) {
                try {
                    DB::transaction(fn () => DB::table($table)->where('id', $id)->update(['public_id' => $replacement]));
                    $this->fail('Public identity mutation must be rejected.');
                } catch (QueryException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
        $payment->delete();
        $this->enable($f);
        app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $charge, 1);
        $copy = (array) DB::table('club_accounting_source_integrations')->first();
        unset($copy['id']);
        $copy['public_id'] = (string) Str::uuid();
        $copy['source_identifier'] = (string) Str::uuid();
        $copy['journal_idempotency_key'] = (string) Str::uuid();
        $copy['integration_checksum'] = str_repeat('A', 64);
        try {
            DB::transaction(fn () => DB::table('club_accounting_source_integrations')->insert($copy));
            $this->fail('Uppercase checksum content must be rejected.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_payment_replay_uses_historical_coverage_after_revocation(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $payload = $this->paymentPayload(MemberCharge::where('club_charge_id', $charge->id)->firstOrFail());
        $payment = $write->recordPayment($f['actor'], $f['club'], $f['member'], $payload)['payment'];
        CarbonImmutable::setTestNow('2027-07-06 12:00:00');
        $activation = ClubAccountingIntegrationActivation::where('club_id', $f['club']->id)->where('source_type', 'member_payment')->firstOrFail();
        app(AccountingIntegrationConfigurationService::class)->revokeActivation($activation, $f['actor'], 'PAY-REPLAY-REVOKE', 'Stopped');
        $this->assertTrue($write->recordPayment($f['actor'], $f['club'], $f['member'], $payload)['replayed']);
        $this->assertSame($payment->id, MemberPayment::where('idempotency_key', $payload['idempotency_key'])->value('id'));
    }

    public function test_exact_payment_request_replays_after_void_and_later_configuration_revocation(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $payload = $this->paymentPayload(MemberCharge::where('club_charge_id', $charge->id)->firstOrFail());
        $payment = $write->recordPayment($f['actor'], $f['club'], $f['member'], $payload)['payment'];
        $voided = $write->void($f['actor'], $f['club'], $payment, $payment->lock_version, 'Correction');
        $config = app(AccountingIntegrationConfigurationService::class);
        $activation = ClubAccountingIntegrationActivation::where('club_id', $f['club']->id)->where('source_type', 'member_payment')->firstOrFail();
        $mapping = ClubAccountingSourceMapping::where('club_id', $f['club']->id)->where('source_type', 'member_payment')->firstOrFail();
        $config->revokeActivation($activation, $f['actor'], 'TERMINAL-REPLAY-ACT', 'Stopped later');
        $config->revokeMapping($mapping, $f['actor'], 'TERMINAL-REPLAY-MAP', 'Stopped later');
        $counts = [
            'payments' => MemberPayment::count(),
            'receipts' => PaymentReceipt::count(),
            'allocations' => PaymentAllocation::count(),
            'journals' => ClubJournalEntry::count(),
            'integrations' => ClubAccountingSourceIntegration::count(),
            'events' => DB::table('club_accounting_source_integration_events')->count(),
        ];

        $replay = $write->recordPayment($f['actor'], $f['club'], $f['member'], $payload);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($voided->id, $replay['payment']->id);
        $this->assertSame('voided', $replay['payment']->status);
        $this->assertSame($counts, [
            'payments' => MemberPayment::count(),
            'receipts' => PaymentReceipt::count(),
            'allocations' => PaymentAllocation::count(),
            'journals' => ClubJournalEntry::count(),
            'integrations' => ClubAccountingSourceIntegration::count(),
            'events' => DB::table('club_accounting_source_integration_events')->count(),
        ]);

        $changed = $payload;
        $changed['external_reference'] = 'CHANGED';
        try {
            $write->recordPayment($f['actor'], $f['club'], $f['member'], $changed);
            $this->fail('Changed terminal payment replay must conflict.');
        } catch (ConflictHttpException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame($counts, [
            'payments' => MemberPayment::count(),
            'receipts' => PaymentReceipt::count(),
            'allocations' => PaymentAllocation::count(),
            'journals' => ClubJournalEntry::count(),
            'integrations' => ClubAccountingSourceIntegration::count(),
            'events' => DB::table('club_accounting_source_integration_events')->count(),
        ]);
    }

    public function test_draft_cancelled_charges_are_excluded_from_reconciliation_before_and_after_activation(): void
    {
        foreach ([false, true] as $enabled) {
            $f = $this->fixture();
            if ($enabled) {
                $this->enable($f);
            }
            $write = app(FinanceWriteService::class);
            $draft = $this->draftCharge($f);
            $cancelled = $write->cancel($f['actor'], $f['club'], $draft, $draft->lock_version, 'Cancelled before issue');

            $this->assertSame('cancelled', $cancelled->status);
            $this->assertNull($cancelled->issued_at);
            $this->assertSame([], app(AccountingIntegrationReconciliationService::class)->defects($f['club']->id));
            $this->assertDatabaseMissing('club_accounting_source_coverage_decisions', ['club_id' => $f['club']->id, 'source_type' => 'member_charge', 'source_identifier' => $cancelled->public_id]);
            $this->assertSame(0, MemberCharge::where('club_charge_id', $cancelled->id)->count());
            $this->assertSame(0, ClubAccountingSourceIntegration::where('club_id', $f['club']->id)->count());
            $this->assertSame(1, ClubJournalEntry::where('club_id', $f['club']->id)->count());
        }
    }

    public function test_historical_replay_rejects_missing_required_links_but_allows_precoverage_and_post_revocation_sources(): void
    {
        $f = $this->fixture();
        $config = app(AccountingIntegrationConfigurationService::class);
        $config->map($f['club']->id, 'member_payment', 'bank_transfer', $f['bank'], 'MAP-V1', '2027-07-01', 'HIST-MAP', $f['actor']);
        $activation = $config->activate($f['club']->id, 'member_payment', '2027-07-01', 'MAP-V1', 'HIST-ACT', $f['actor']);
        $covered = MemberPayment::create(['club_id' => $f['club']->id, 'member_id' => $f['member']->id, 'amount_paise' => 100, 'payment_date' => '2027-07-05', 'payment_mode' => 'bank_transfer', 'idempotency_key' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64), 'status' => 'recorded', 'recorded_by_user_id' => $f['actor']->id]);
        CarbonImmutable::setTestNow('2027-07-06 10:00:00');
        $config->revokeActivation($activation, $f['actor'], 'HIST-REVOKE', 'Stopped');
        try {
            app(OrganisationTransactionGuard::class)->run($f['club'], fn ($context) => app(SourceAccountingIntegrationService::class)->validatePaymentReplayWithin($context, $covered->fresh()));
            $this->fail('Historically required missing linkage must fail.');
        } catch (AccountingIntegrationException) {
            $this->addToAssertionCount(1);
        }
        $post = MemberPayment::create(['club_id' => $f['club']->id, 'member_id' => $f['member']->id, 'amount_paise' => 100, 'payment_date' => '2027-07-06', 'payment_mode' => 'bank_transfer', 'idempotency_key' => (string) Str::uuid(), 'request_hash' => str_repeat('b', 64), 'status' => 'recorded', 'recorded_by_user_id' => $f['actor']->id]);
        app(OrganisationTransactionGuard::class)->run($f['club'], fn ($context) => app(SourceAccountingIntegrationService::class)->recordPaymentCoverageDecisionWithin($context, $post, $f['actor']));
        $this->assertNull(app(OrganisationTransactionGuard::class)->run($f['club'], fn ($context) => app(SourceAccountingIntegrationService::class)->validatePaymentReplayWithin($context, $post->fresh())));

        $legacy = $this->fixture();
        $legacyCharge = app(FinanceWriteService::class)->issue($legacy['actor'], $legacy['club'], $this->draftCharge($legacy), 1);
        app(AccountingIntegrationConfigurationService::class)->map($legacy['club']->id, 'member_charge', 'membership_dues', $legacy['income'], 'MAP-V1', '2027-07-01', 'PRE-MAP', $legacy['actor']);
        app(AccountingIntegrationConfigurationService::class)->activate($legacy['club']->id, 'member_charge', '2027-07-10', 'MAP-V1', 'PRE-ACT', $legacy['actor']);
        $this->assertNull(app(OrganisationTransactionGuard::class)->run($legacy['club'], fn ($context) => app(SourceAccountingIntegrationService::class)->validateChargeReplayWithin($context, $legacyCharge)));
    }

    public function test_source_and_journal_idempotency_are_separate_and_lifecycle_misuse_fails(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $memberCharge = MemberCharge::where('club_charge_id', $charge->id)->firstOrFail();
        $payload = $this->paymentPayload($memberCharge);
        app(ClubJournalPostingService::class)->post(new JournalPostingRequest($f['club']->id, $f['period']->id, '2027-07-05', 'Unrelated', 'INR', 'test', 'unrelated', 'committed', $payload['idempotency_key'], $f['actor']->id, [new JournalLineInput($f['receivable']->id, 'debit', 1, fundCode: 'GENERAL_CLUB_FUND'), new JournalLineInput($f['equity']->id, 'credit', 1, fundCode: 'GENERAL_CLUB_FUND')]));
        $payment = $write->recordPayment($f['actor'], $f['club'], $f['member'], $payload)['payment'];
        $integration = ClubAccountingSourceIntegration::where('source_identifier', $payment->public_id)->firstOrFail();
        $this->assertSame(strtolower($payload['idempotency_key']), strtolower($integration->source_request_idempotency_key));
        $this->assertNotSame(strtolower($payload['idempotency_key']), strtolower($integration->journal_idempotency_key));

        $draft = $this->draftCharge($f);
        try {
            app(OrganisationTransactionGuard::class)->run($f['club'], fn ($context) => app(SourceAccountingIntegrationService::class)->postChargeWithin($context, $draft, $f['actor']));
            $this->fail('Draft direct posting must fail.');
        } catch (AccountingIntegrationException) {
            $this->addToAssertionCount(1);
        }
        try {
            app(OrganisationTransactionGuard::class)->run($f['club'], fn ($context) => app(SourceAccountingIntegrationService::class)->reverseWithin($context, $f['club']->id, 'member_charge', $charge->public_id, $charge->issued_at->toDateString(), $charge->issued_at->toISOString(), '2027-07-06', $f['actor'], 'Invalid direct reversal'));
            $this->fail('Issued direct reversal must fail.');
        } catch (AccountingIntegrationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_terminal_and_stage_events_are_unique_and_repeated_revocation_is_rejected(): void
    {
        $f = $this->fixture();
        $this->enable($f);
        $activation = ClubAccountingIntegrationActivation::where('club_id', $f['club']->id)->where('source_type', 'member_charge')->firstOrFail();
        $config = app(AccountingIntegrationConfigurationService::class);
        $config->revokeActivation($activation, $f['actor'], 'ONE-TERMINAL', 'Stopped');
        try {
            $config->revokeActivation($activation, $f['actor'], 'TWO-TERMINAL', 'Again');
            $this->fail('Repeated terminal event must fail.');
        } catch (AccountingIntegrationException) {
            $this->addToAssertionCount(1);
        }
        $chargeActivation = ClubAccountingIntegrationActivation::whereKey($activation->id)->firstOrFail();
        $this->assertSame(1, $chargeActivation->events()->count());
        $mapping = ClubAccountingSourceMapping::where('club_id', $f['club']->id)->where('source_type', 'member_payment')->firstOrFail();
        $config->revokeMapping($mapping, $f['actor'], 'ONE-MAP-TERMINAL', 'Stopped');
        try {
            $config->revokeMapping($mapping, $f['actor'], 'TWO-MAP-TERMINAL', 'Again');
            $this->fail('Repeated mapping terminal event must fail.');
        } catch (AccountingIntegrationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, $mapping->events()->count());
    }

    public function test_posting_relocks_and_revalidates_mapped_account_state(): void
    {
        foreach ([['is_active' => false], ['currency' => 'USD'], ['account_type' => 'asset']] as $mutation) {
            $f = $this->fixture();
            $this->enable($f);
            try {
                DB::transaction(fn () => $f['income']->update($mutation));
            } catch (QueryException) {
                $this->addToAssertionCount(1);

                continue;
            }
            $charge = $this->draftCharge($f);
            try {
                app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $charge, 1);
                $this->fail('Changed mapped account contract must fail posting.');
            } catch (AccountingIntegrationException) {
                $this->addToAssertionCount(1);
            }
            $this->assertSame('draft', $charge->fresh()->status);
        }
    }

    public function test_reconciliation_detects_child_binding_key_checksum_and_binding_corruption(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Controlled corrupt-fixture test uses a disposable SQLite trigger override.');
        }
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $obligation = MemberCharge::where('club_charge_id', $charge->id)->firstOrFail();
        $payment = $write->recordPayment($f['actor'], $f['club'], $f['member'], $this->paymentPayload($obligation))['payment'];
        $otherMember = Member::create(['club_id' => $f['club']->id, 'member_code' => 'M002', 'full_name' => 'Other', 'status' => 'active']);
        $changedMember = Member::create(['club_id' => $f['club']->id, 'member_code' => 'M003', 'full_name' => 'Changed', 'status' => 'active']);
        $otherObligation = MemberCharge::create(['club_id' => $f['club']->id, 'club_charge_id' => $charge->id, 'member_id' => $otherMember->id, 'original_amount_paise' => 50000]);
        PaymentAllocation::where('member_payment_id', $payment->id)->update(['member_charge_id' => $otherObligation->id]);
        $obligation->update(['member_id' => $changedMember->id, 'original_amount_paise' => 25000]);

        DB::unprepared('DROP TRIGGER club_accounting_source_integrations_immutable_update');
        try {
            $integration = ClubAccountingSourceIntegration::where('source_type', 'member_charge')->firstOrFail();
            DB::table('club_accounting_source_integrations')->where('id', $integration->id)->update(['canonical_source_key' => 'corrupt', 'integration_checksum' => str_repeat('A', 64), 'source_binding' => json_encode(['corrupt' => true])]);
            DB::table('club_journal_lines')->where('journal_entry_id', $integration->journal_entry_id)->where('line_number', 1)->update(['line_number' => 99]);
            DB::table('club_journal_lines')->where('journal_entry_id', $integration->journal_entry_id)->where('line_number', 2)->update(['line_number' => 1]);
            DB::table('club_journal_lines')->where('journal_entry_id', $integration->journal_entry_id)->where('line_number', 99)->update(['line_number' => 2]);
            $types = collect(app(AccountingIntegrationReconciliationService::class)->defects($f['club']->id))->pluck('type');
            $this->assertTrue($types->contains('canonical_source_binding_mismatch'));
            $this->assertTrue($types->contains('canonical_source_key_mismatch'));
            $this->assertTrue($types->contains('integration_checksum_mismatch'));
            $this->assertTrue($types->contains('journal_lines_mismatch'));
        } finally {
            DB::unprepared("CREATE TRIGGER club_accounting_source_integrations_immutable_update BEFORE UPDATE ON club_accounting_source_integrations BEGIN SELECT RAISE(ABORT, 'club_accounting_source_integrations_immutable'); END");
        }
    }

    public function test_exact_replay_rejects_every_material_source_and_journal_corruption(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Controlled replay corruption uses disposable SQLite fixtures.');
        }

        $cases = [
            ['payment', 'payment date', fn (MemberPayment $source, ClubAccountingSourceIntegration $integration) => DB::table('member_payments')->where('id', $source->id)->update(['payment_date' => '2027-07-07'])],
            ['charge', 'charge issued date', fn (ClubCharge $source, ClubAccountingSourceIntegration $integration) => DB::table('club_charges')->where('id', $source->id)->update(['issued_at' => '2027-07-06 10:00:00'])],
            ['charge', 'canonical source key', fn (ClubCharge $source, ClubAccountingSourceIntegration $integration) => DB::table('club_charges')->where('id', $source->id)->update(['category' => 'service_activity'])],
            ['payment', 'source amount', fn (MemberPayment $source, ClubAccountingSourceIntegration $integration) => DB::table('member_payments')->where('id', $source->id)->update(['amount_paise' => 49999])],
            ['payment', 'source request idempotency key', fn (MemberPayment $source, ClubAccountingSourceIntegration $integration) => DB::table('member_payments')->where('id', $source->id)->update(['idempotency_key' => (string) Str::uuid()])],
            ['charge', 'journal source event', fn (ClubCharge $source, ClubAccountingSourceIntegration $integration) => DB::table('club_journal_entries')->where('id', $integration->journal_entry_id)->update(['source_event' => 'corrupt'])],
            ['charge', 'journal idempotency key', fn (ClubCharge $source, ClubAccountingSourceIntegration $integration) => DB::table('club_journal_entries')->where('id', $integration->journal_entry_id)->update(['idempotency_key' => (string) Str::uuid()])],
            ['charge', 'journal metadata checksum', function (ClubCharge $source, ClubAccountingSourceIntegration $integration): void {
                $metadata = $integration->journalEntry->metadata;
                $metadata['integration_checksum'] = str_repeat('f', 64);
                DB::table('club_journal_entries')->where('id', $integration->journal_entry_id)->update(['metadata' => json_encode($metadata, JSON_THROW_ON_ERROR)]);
            }],
        ];

        foreach ($cases as [$type, $label, $corrupt]) {
            $f = $this->fixture();
            $this->enable($f);
            $write = app(FinanceWriteService::class);
            $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
            $source = $type === 'charge'
                ? $charge
                : $write->recordPayment($f['actor'], $f['club'], $f['member'], $this->paymentPayload(MemberCharge::where('club_charge_id', $charge->id)->firstOrFail()))['payment'];
            $integration = ClubAccountingSourceIntegration::with('journalEntry')->where('club_id', $f['club']->id)->where('source_type', "member_{$type}")->firstOrFail();
            $corrupt($source, $integration);

            try {
                $service = app(SourceAccountingIntegrationService::class);
                $type === 'charge' ? $service->validateChargeReplay($source->fresh()) : $service->validatePaymentReplay($source->fresh());
                $this->fail("Replay must reject {$label} corruption.");
            } catch (AccountingIntegrationConflictException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_reconciliation_independently_detects_coverage_decision_corruption(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Controlled decision corruption uses disposable SQLite trigger overrides.');
        }
        $f = $this->fixture();
        $this->enable($f);
        $write = app(FinanceWriteService::class);
        $charge = $write->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $payment = $write->recordPayment($f['actor'], $f['club'], $f['member'], $this->paymentPayload(MemberCharge::firstOrFail()))['payment'];
        $activation = ClubAccountingIntegrationActivation::where('club_id', $f['club']->id)->where('source_type', 'member_charge')->firstOrFail();
        DB::unprepared('DROP TRIGGER club_accounting_source_coverage_decisions_immutable_update');
        DB::unprepared('DROP TRIGGER club_accounting_source_coverage_decisions_immutable_delete');
        try {
            DB::table('club_accounting_source_coverage_decisions')->where('source_identifier', $charge->public_id)->update(['integration_required' => false, 'decision_reason' => 'before_coverage']);
            DB::table('club_accounting_source_coverage_decisions')->where('source_identifier', $payment->public_id)->delete();
            $missingIntegration = ClubCharge::create(['club_id' => $f['club']->id, 'title' => 'Missing integration', 'category' => 'membership_dues', 'amount_paise' => 100, 'due_date' => '2027-07-31', 'financial_year' => '2027-28', 'status' => 'issued', 'assignment_member_ids' => [$f['member']->id], 'created_by_user_id' => $f['actor']->id, 'issued_at' => now(), 'issued_by_user_id' => $f['actor']->id, 'lock_version' => 2]);
            DB::table('club_accounting_source_coverage_decisions')->insert(['public_id' => (string) Str::uuid(), 'club_id' => $f['club']->id, 'source_type' => 'member_charge', 'source_identifier' => $missingIntegration->public_id, 'accounting_date' => '2027-07-04', 'integration_required' => true, 'integration_activation_id' => $activation->id, 'terminal_activation_event_id' => null, 'decision_reason' => 'integration_required', 'decided_by_user_id' => $f['actor']->id, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('club_accounting_source_coverage_decisions')->insert(['public_id' => (string) Str::uuid(), 'club_id' => $f['club']->id, 'source_type' => 'member_charge', 'source_identifier' => (string) Str::uuid(), 'accounting_date' => '2027-07-05', 'integration_required' => false, 'integration_activation_id' => null, 'terminal_activation_event_id' => null, 'decision_reason' => 'not_activated', 'decided_by_user_id' => $f['actor']->id, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $types = collect(app(AccountingIntegrationReconciliationService::class)->defects($f['club']->id))->pluck('type');
            foreach (['outside_coverage_source_has_integration', 'source_without_coverage_decision', 'covered_source_without_integration', 'coverage_decision_source_missing', 'coverage_decision_source_mismatch'] as $type) {
                $this->assertTrue($types->contains($type), $type);
            }
        } finally {
            DB::unprepared("CREATE TRIGGER club_accounting_source_coverage_decisions_immutable_update BEFORE UPDATE ON club_accounting_source_coverage_decisions BEGIN SELECT RAISE(ABORT, 'club_accounting_source_coverage_decisions_immutable'); END");
            DB::unprepared("CREATE TRIGGER club_accounting_source_coverage_decisions_immutable_delete BEFORE DELETE ON club_accounting_source_coverage_decisions BEGIN SELECT RAISE(ABORT, 'club_accounting_source_coverage_decisions_immutable'); END");
        }
    }

    public function test_context_replay_rejects_a_stale_source_version_without_financial_effects(): void
    {
        $f = $this->fixture();
        $payment = MemberPayment::create(['club_id' => $f['club']->id, 'member_id' => $f['member']->id, 'amount_paise' => 100, 'payment_date' => '2027-07-05', 'payment_mode' => 'bank_transfer', 'idempotency_key' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64), 'status' => 'recorded', 'recorded_by_user_id' => $f['actor']->id])->fresh();
        $before = ClubJournalEntry::count();
        DB::table('member_payments')->where('id', $payment->id)->update(['lock_version' => $payment->lock_version + 1]);
        try {
            app(OrganisationTransactionGuard::class)->run($f['club'], fn ($context) => app(SourceAccountingIntegrationService::class)->validatePaymentReplayWithin($context, $payment));
            $this->fail('A stale replay version must not be rebound.');
        } catch (AccountingIntegrationConflictException $exception) {
            $this->assertSame('The source replay version changed.', $exception->getMessage());
        }
        $this->assertSame($before, ClubJournalEntry::count());
        $this->assertSame(0, ClubAccountingSourceIntegration::count());
        $this->assertSame(0, DB::transactionLevel());
    }

    private function fixture(): array
    {
        CarbonImmutable::setTestNow('2027-07-05 10:00:00');
        $actor = User::factory()->create();
        $sequence = Club::count() + 1;
        $club = Club::create(['name' => 'Beenachi '.$sequence, 'code' => 'LCB'.$sequence, 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        config(['instance.organisation_code' => $club->code]);
        $member = Member::create(['club_id' => $club->id, 'member_code' => 'M001', 'full_name' => 'Member One', 'status' => 'active']);
        $accounts = [];
        foreach ([['receivable', 'MEMBER-RECEIVABLES', 'asset', 'debit'], ['bank', 'BANK-OPERATING', 'asset', 'debit'], ['income', 'INCOME-MEMBERSHIP-DUES', 'income', 'credit'], ['equity', 'OPENING-BALANCE-EQUITY', 'equity', 'credit']] as [$key, $code, $type, $normal]) {
            $accounts[$key] = LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id]);
        }
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]);
        $opening = app(ClubJournalPostingService::class)->post(new JournalPostingRequest($club->id, $period->id, '2027-07-01', 'Opening', 'INR', 'accounting_opening_package', 'opening-fixture', 'activated', '00000000-0000-4000-8000-000000000001', $actor->id, [new JournalLineInput($accounts['receivable']->id, 'debit', 1, fundCode: 'GENERAL_CLUB_FUND'), new JournalLineInput($accounts['equity']->id, 'credit', 1, fundCode: 'GENERAL_CLUB_FUND')]));
        ClubAccountingCutoverPackage::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01', 'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'posted', 'currency' => 'INR', 'canonical_checksum' => str_repeat('a', 64), 'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => true, 'opening_journal_entry_id' => $opening->id, 'posted_at' => now(), 'posted_by_user_id' => $actor->id, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]);

        return ['actor' => $actor, 'club' => $club, 'member' => $member, 'period' => $period, ...$accounts];
    }

    private function enable(array $f): void
    {
        $config = app(AccountingIntegrationConfigurationService::class);
        foreach ([['member_charge', 'membership_dues', $f['income']], ['member_payment', 'bank_transfer', $f['bank']]] as $index => [$source, $key, $account]) {
            $config->map($f['club']->id, $source, $key, $account, 'MAP-V1', '2027-07-01', "MAP-{$f['club']->id}-{$index}", $f['actor']);
            $config->activate($f['club']->id, $source, '2027-07-01', 'MAP-V1', "ACT-{$f['club']->id}-{$index}", $f['actor']);
        }
    }

    private function draftCharge(array $f): ClubCharge
    {
        return app(FinanceWriteService::class)->createCharge($f['actor'], $f['club'], ['title' => 'Dues', 'category' => 'membership_dues', 'amount_paise' => 50000, 'due_date' => '2027-07-31', 'financial_year' => '2027-28', 'member_ids' => [$f['member']->id], 'assign_all_active_members' => false]);
    }

    private function paymentPayload(MemberCharge $charge): array
    {
        return ['payment_date' => '2027-07-06', 'payment_mode' => 'bank_transfer', 'external_reference' => 'BANK-1', 'amount_paise' => 50000, 'allocations' => [['member_charge_id' => $charge->id, 'amount_paise' => 50000]], 'idempotency_key' => '11111111-1111-4111-8111-111111111111'];
    }

    private function assertJournal(ClubJournalEntry $journal, int $debitAccount, int $creditAccount, int $amount): void
    {
        $this->assertDatabaseHas('club_journal_lines', ['journal_entry_id' => $journal->id, 'ledger_account_id' => $debitAccount, 'direction' => 'debit', 'amount_paise' => $amount]);
        $this->assertDatabaseHas('club_journal_lines', ['journal_entry_id' => $journal->id, 'ledger_account_id' => $creditAccount, 'direction' => 'credit', 'amount_paise' => $amount]);
    }
}
