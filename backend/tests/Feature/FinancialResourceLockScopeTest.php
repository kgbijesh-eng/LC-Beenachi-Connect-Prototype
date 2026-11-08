<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\Exceptions\ClubExpenseException;
use App\Services\Finance\Exceptions\ClubInternalTransferException;
use App\Services\Finance\Exceptions\ClubOrdinaryIncomeReceiptException;
use App\Services\Finance\Exceptions\JournalClubScopeViolationException;
use App\Services\Finance\Exceptions\ReversalClubScopeViolationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FinancialOperationFixture as Fixture;
use Tests\Support\FinancialPostgresTestCase;

class FinancialResourceLockScopeTest extends FinancialPostgresTestCase
{
    public static function resources(): array
    {
        return [
            ['post-period', 'post'], ['post-account', 'post'], ['reverse-entry', 'reverse'], ['reverse-period', 'reverse'],
            ['legacy-payment', 'legacy-payment'], ['legacy-receipt', 'legacy-receipt'],
            ['create-member', 'collections-create'], ['update-charge', 'collections-update'], ['issue-charge', 'collections-issue'],
            ['close-charge', 'collections-close'], ['cancel-charge', 'collections-cancel'], ['payment-member', 'collections-payment'], ['void-payment', 'collections-void'],
            ['expense-account', 'expense'], ['reverse-expense', 'expense-reversal'], ['transfer-account', 'transfer'], ['income-account', 'income'], ['correction-receipt', 'correction'],
        ];
    }

    #[DataProvider('resources')]
    public function test_foreign_resource_is_rejected_without_waiting_for_its_row_lock(string $resource, string $action): void
    {
        $p = (new Fixture)->prepare($action, 'LOCKA');
        $foreign = (new Fixture)->prepare($action, 'LOCKB');
        config(['instance.organisation_code' => $p['code']]);
        [$table,$id,$expected] = match ($resource) {
            'post-period','reverse-period' => ['club_accounting_periods', $foreign['period'], $action === 'post' ? JournalClubScopeViolationException::class : ReversalClubScopeViolationException::class],
            'post-account' => ['ledger_accounts', $foreign['bank'], JournalClubScopeViolationException::class],
            'reverse-entry' => ['club_journal_entries', $foreign['original'], ReversalClubScopeViolationException::class],
            'legacy-payment' => ['members', $foreign['member'], OrganisationAccessRejected::class],
            'legacy-receipt' => ['payments', $foreign['legacy_payment'], OrganisationAccessRejected::class],
            'create-member' => ['members', $foreign['member'], ValidationException::class],
            'update-charge','issue-charge','close-charge','cancel-charge' => ['club_charges', $foreign['charge'], ModelNotFoundException::class],
            'payment-member' => ['members', $foreign['member'], ModelNotFoundException::class],
            'void-payment' => ['member_payments', $foreign['collection_payment'], ModelNotFoundException::class],
            'expense-account','transfer-account','income-account' => ['ledger_accounts', $foreign['bank'], match ($resource) {
                'expense-account' => ClubExpenseException::class, 'transfer-account' => ClubInternalTransferException::class, default => ClubOrdinaryIncomeReceiptException::class
            }],
            'reverse-expense' => ['club_expenses', DB::table('club_expenses')->where('public_id', $foreign['expense'])->value('id'), ModelNotFoundException::class],
            'correction-receipt' => ['club_ordinary_income_receipts', DB::table('club_ordinary_income_receipts')->where('public_id', $foreign['receipt'])->value('id'), ModelNotFoundException::class],
        };
        match ($resource) {
            'post-period','reverse-period' => $p['period'] = $foreign['period'],
            'post-account' => $p['bank'] = $foreign['bank'],
            'reverse-entry' => $p['original'] = $foreign['original'],
            'legacy-payment','payment-member' => $p['member'] = $foreign['member'],
            'legacy-receipt' => $p['legacy_payment'] = $foreign['legacy_payment'],
            'create-member' => $p['charge_input']['member_ids'] = [$foreign['member']],
            'update-charge','issue-charge','close-charge','cancel-charge' => $p['charge'] = $foreign['charge'],
            'void-payment' => $p['collection_payment'] = $foreign['collection_payment'],
            'expense-account' => $p['expense_input']['payment_account'] = $foreign['transfer_input']['source_account'],
            'transfer-account' => $p['transfer_input']['destination_account'] = $foreign['transfer_input']['source_account'],
            'income-account' => $p['income_input']['receiving_account'] = $foreign['income_input']['receiving_account'],
            'reverse-expense' => $p['expense'] = $foreign['expense'],
            'correction-receipt' => $p['receipt'] = $foreign['receipt'],
        };
        $before = [Fixture::snapshot($p['club']), Fixture::snapshot($foreign['club'])];
        config(['database.connections.b3_foreign_lock' => config('database.connections.'.DB::getDefaultConnection())]);
        $peer = DB::connection('b3_foreign_lock');
        $peer->beginTransaction();
        $peer->table($table)->where('club_id', $foreign['club'])->where('id', $id)->lockForUpdate()->first();
        DB::unprepared("SET lock_timeout='250ms'");
        try {
            try {
                Fixture::perform($p);
                $this->fail('Foreign resources must not be locked or accepted.');
            } catch (\Throwable $e) {
                $this->assertInstanceOf($expected, $e, 'A PostgreSQL lock timeout is a failure, not a successful rejection.');
            }
            $this->assertSame($before, [Fixture::snapshot($p['club']), Fixture::snapshot($foreign['club'])]);
            $this->assertSame(0, DB::transactionLevel());
        } finally {
            DB::unprepared('SET lock_timeout=DEFAULT');
            $peer->rollBack();
            DB::purge('b3_foreign_lock');
        }
    }
}
