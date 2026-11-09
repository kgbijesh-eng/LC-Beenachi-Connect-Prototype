<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Club;
use App\Models\ClubCharge;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberCharge;
use App\Models\MemberPayment;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\AccountingIntegrationConfigurationService;
use App\Services\Finance\ClubExpenseRecordingService;
use App\Services\Finance\ClubExpenseReversalService;
use App\Services\Finance\ClubInternalTransferService;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\ClubOrdinaryIncomeReceiptCorrectionService;
use App\Services\Finance\ClubOrdinaryIncomeReceiptService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use App\Services\Finance\JournalReversalRequest;
use App\Services\Finance\JournalReversalService;
use App\Services\Finance\PaymentRecordingService;
use App\Services\Finance\ReceiptGenerationService;
use App\Services\FinanceCollections\FinanceWriteService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Synthetic, committed prerequisites. No ambient wrapper or fake eligibility context. */
final class FinancialOperationFixture
{
    use BuildsMilestone3ijaeFixture;

    public static function actions(): array
    {
        $base = ['post', 'reverse', 'legacy-payment', 'legacy-receipt', 'collections-create', 'collections-update', 'collections-issue', 'collections-close', 'collections-cancel', 'collections-payment', 'collections-void', 'expense', 'expense-reversal', 'transfer', 'income', 'correction', 'correction-replacement'];

        return [...$base, ...array_map(fn ($a) => $a.'-replay', ['post', 'reverse', 'collections-issue', 'collections-payment', 'expense', 'expense-reversal', 'transfer', 'income', 'correction', 'correction-replacement'])];
    }

    public function prepare(string $action, string $suffix = 'B3'): array
    {
        CarbonImmutable::setTestNow('2027-08-12 10:00:00');
        Carbon::setTestNow('2027-08-12 10:00:00');
        $f = $this->fixture3ijae($suffix);
        $member = Member::create(['club_id' => $f['club']->id, 'member_code' => 'PROBE', 'full_name' => 'Synthetic financial member', 'status' => 'active']);
        $expense = $this->account3ijae($f['club'], $f['actor'], 'EXPENSE-'.$suffix, 'expense', 'debit', 'EXPENSES');
        $cash = $this->account3ijae($f['club'], $f['actor'], 'CASH-'.$suffix, 'asset', 'debit', 'CASH_AND_BANK');
        $permission = Permission::firstOrCreate(['slug' => 'payments.record'], ['name' => 'Record payments', 'group' => 'finance']);
        $role = Role::findOrFail(ClubMembership::where('club_id', $f['club']->id)->where('user_id', $f['actor']->id)->value('role_id'));
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        // This shared fixture provisions a synthetic finance operator for all supported actions.
        foreach (['finance.manage', 'finance.void', 'finance.club.expenses.create', 'finance.club.expenses.reverse', 'finance.club.transfers.create'] as $slug) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'finance'])->id]);
        }
        $p = ['action' => $action, 'club' => $f['club']->id, 'code' => $f['club']->code, 'actor' => $f['actor']->id, 'member' => $member->id, 'period' => $f['period']->id, 'bank' => $f['bank']->id, 'income' => $f['income']->id, 'key' => (string) Str::uuid()];
        $p['expense_input'] = ['accounting_date' => '2027-08-12', 'expense_account' => $expense->public_id, 'payment_account' => $f['bank']->public_id, 'fund' => 'GENERAL_CLUB_FUND', 'amount_paise' => 100, 'description' => 'Synthetic expense', 'idempotency_key' => $p['key']];
        $p['transfer_input'] = ['accounting_date' => '2027-08-12', 'source_account' => $f['bank']->public_id, 'destination_account' => $cash->public_id, 'fund' => 'GENERAL_CLUB_FUND', 'amount_paise' => 100, 'description' => 'Synthetic transfer', 'idempotency_key' => $p['key']];
        $p['income_input'] = [...$this->receiptPayload3ijae($f, $p['key']), 'accounting_date' => '2027-08-12', 'amount_paise' => 100];
        $p['correction_input'] = $this->correctionPayload3ijae($p['key']);
        $p['reversal_input'] = ['accounting_date' => '2027-08-12', 'reason' => 'B3 probe', 'idempotency_key' => $p['key']];
        $base = self::base($action);
        if (str_starts_with($base, 'collections-')) {
            $this->account3ijae($f['club'], $f['actor'], 'MEMBER-RECEIVABLES', 'asset', 'debit', 'RECEIVABLES');
            $config = app(AccountingIntegrationConfigurationService::class);
            foreach ([['member_charge', 'membership_dues', $f['income']], ['member_payment', 'bank_transfer', $f['bank']]] as [$type,$key,$account]) {
                $config->map($p['club'], $type, $key, $account, 'B3-V1', '2027-07-01', $suffix.'-'.$type.'-MAP', $f['actor']);
                $config->activate($p['club'], $type, '2027-07-01', 'B3-V1', $suffix.'-'.$type.'-ACT', $f['actor']);
            }
            $p['charge_input'] = ['title' => 'Synthetic dues', 'category' => 'membership_dues', 'amount_paise' => 100, 'due_date' => '2027-08-31', 'financial_year' => '2027-28', 'member_ids' => [$member->id], 'assign_all_active_members' => false];
            if ($base !== 'collections-create') {
                $charge = app(FinanceWriteService::class)->createCharge($f['actor'], $f['club'], $p['charge_input']);
                $p['charge'] = $charge->id;
                $p['version'] = $charge->lock_version;
                if (in_array($base, ['collections-close', 'collections-cancel', 'collections-payment', 'collections-void'], true)) {
                    $charge = app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $charge, $p['version']);
                    $p['version'] = $charge->lock_version;
                    $p['payment_input'] = ['payment_date' => '2027-08-12', 'payment_mode' => 'bank_transfer', 'amount_paise' => 100, 'idempotency_key' => $p['key'], 'allocations' => [['member_charge_id' => MemberCharge::where('club_charge_id', $charge->id)->value('id'), 'amount_paise' => 100]]];
                    if (in_array($base, ['collections-void', 'collections-close'], true)) {
                        $result = app(FinanceWriteService::class)->recordPayment($f['actor'], $f['club'], $member, $p['payment_input']);
                        $p['collection_payment'] = $result['payment']->id;
                        if ($base === 'collections-void') {
                            $p['version'] = $result['payment']->lock_version;
                        }
                    }
                }
            }
        }
        if ($base === 'reverse') {
            $p['original'] = app(ClubJournalPostingService::class)->post(self::posting([...$p, 'key' => (string) Str::uuid()]))->id;
        }
        if ($base === 'legacy-receipt') {
            $p['legacy_payment'] = app(PaymentRecordingService::class)->record($f['actor'], $member, ['amount' => '1.00', 'payment_date' => '2027-08-12', 'payment_mode' => 'cash'])->id;
        }
        if ($base === 'expense-reversal') {
            $p['expense'] = app(ClubExpenseRecordingService::class)->record($f['club'], $f['actor'], [...$p['expense_input'], 'idempotency_key' => (string) Str::uuid()])->public_id;
        }
        if (in_array($base, ['correction', 'correction-replacement'], true)) {
            $p['receipt'] = app(ClubOrdinaryIncomeReceiptService::class)->record($f['club'], $f['actor'], [...$p['income_input'], 'idempotency_key' => (string) Str::uuid()])->public_id;
            if ($base === 'correction-replacement') {
                $replacement = $p['income_input'];
                unset($replacement['idempotency_key']);
                $replacement['amount_paise'] = 125;
                $p['correction_input']['replacement'] = $replacement;
            }
        }
        if (str_ends_with($action, '-replay')) {
            self::perform($p);
        }

        return $p;
    }

    public static function base(string $action): string
    {
        return str_ends_with($action, '-replay') ? substr($action, 0, -7) : $action;
    }

    public static function posting(array $p): JournalPostingRequest
    {
        return new JournalPostingRequest($p['club'], $p['period'], '2027-08-12', 'B3 probe', 'INR', 'b3_probe', $p['key'], 'posted', $p['key'], $p['actor'], [new JournalLineInput($p['bank'], 'debit', 100, fundCode: 'GENERAL_CLUB_FUND'), new JournalLineInput($p['income'], 'credit', 100, fundCode: 'GENERAL_CLUB_FUND')]);
    }

    public static function reversal(array $p): JournalReversalRequest
    {
        return new JournalReversalRequest($p['club'], $p['original'], $p['period'], '2027-08-12', $p['actor'], 'B3 probe', 'b3_probe', $p['key'], 'reversed', $p['key']);
    }

    public static function perform(array $p, ?Club $club = null, ?Member $member = null): mixed
    {
        CarbonImmutable::setTestNow('2027-08-12 10:00:00');
        Carbon::setTestNow('2027-08-12 10:00:00');
        $club ??= Club::findOrFail($p['club']);
        $member ??= Member::findOrFail($p['member']);
        $actor = User::findOrFail($p['actor']);

        return match (self::base($p['action'])) {
            'post' => app(ClubJournalPostingService::class)->post(self::posting($p)),
            'reverse' => app(JournalReversalService::class)->reverse(self::reversal($p)),
            'legacy-payment' => app(PaymentRecordingService::class)->record($actor, $member, ['amount' => '1.00', 'payment_date' => '2027-08-12', 'payment_mode' => 'cash']),
            'legacy-receipt' => app(ReceiptGenerationService::class)->generate($actor, Payment::findOrFail($p['legacy_payment'])),
            'collections-create' => app(FinanceWriteService::class)->createCharge($actor, $club, $p['charge_input']),
            'collections-update' => app(FinanceWriteService::class)->updateCharge($actor, $club, ClubCharge::findOrFail($p['charge']), [...$p['charge_input'], 'title' => 'Updated synthetic dues', 'lock_version' => $p['version']]),
            'collections-issue' => app(FinanceWriteService::class)->issue($actor, $club, ClubCharge::findOrFail($p['charge']), $p['version']),
            'collections-close' => app(FinanceWriteService::class)->close($actor, $club, ClubCharge::findOrFail($p['charge']), $p['version']),
            'collections-cancel' => app(FinanceWriteService::class)->cancel($actor, $club, ClubCharge::findOrFail($p['charge']), $p['version'], 'B3 probe'),
            'collections-payment' => app(FinanceWriteService::class)->recordPayment($actor, $club, $member, $p['payment_input']),
            'collections-void' => app(FinanceWriteService::class)->void($actor, $club, MemberPayment::findOrFail($p['collection_payment']), $p['version'], 'B3 probe'),
            'expense' => app(ClubExpenseRecordingService::class)->record($club, $actor, $p['expense_input']),
            'expense-reversal' => app(ClubExpenseReversalService::class)->reverse($club, $p['expense'], $actor, $p['reversal_input']),
            'transfer' => app(ClubInternalTransferService::class)->record($club, $actor, $p['transfer_input']),
            'income' => app(ClubOrdinaryIncomeReceiptService::class)->record($club, $actor, $p['income_input']),
            'correction','correction-replacement' => app(ClubOrdinaryIncomeReceiptCorrectionService::class)->correct($club, $p['receipt'], $actor, $p['correction_input']),
            default => throw new RuntimeException('Unknown synthetic financial operation.'),
        };
    }

    public static function journalDelta(string $action): int
    {
        if (str_ends_with($action, '-replay')) {
            return 0;
        }

        return match ($action) {
            'post','reverse','collections-issue','collections-cancel','collections-payment','collections-void','expense','expense-reversal','transfer','income','correction' => 1,
            'correction-replacement' => 2,
            default => 0,
        };
    }

    public static function snapshot(int $club): array
    {
        $tables = ['club_journal_entries', 'club_journal_lines', 'payments', 'receipts', 'member_ledger_entries', 'club_charges', 'member_charges', 'member_payments', 'payment_allocations', 'payment_receipts', 'club_expenses', 'club_expense_reversals', 'club_internal_transfers', 'club_ordinary_income_receipts', 'club_ordinary_income_receipt_corrections', 'club_accounting_source_integrations', 'club_accounting_source_integration_events', 'club_accounting_source_coverage_decisions', 'audit_logs'];
        $result = [];
        foreach ($tables as $table) {
            $result[$table] = json_decode(json_encode(DB::table($table)->where('club_id', $club)->orderBy('id')->get(), JSON_THROW_ON_ERROR), true);
        }

        return $result;
    }
}
