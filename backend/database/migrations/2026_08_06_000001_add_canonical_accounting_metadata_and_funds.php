<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFINITIONS = [
        ['membership_dues', 'Membership Dues', 'GENERAL_CLUB_FUND', true],
        ['installation_contribution', 'Annual Installation Contribution', 'FELLOWSHIP_MEMBER_ACTIVITY_FUND', true],
        ['service_project_contribution', 'Service Project Contribution', 'SERVICE_ACTIVITY_FUND', true],
        ['general_club_event_contribution', 'General Club Event Contribution', 'GENERAL_CLUB_FUND', true],
        ['fellowship_member_activity_contribution', 'Fellowship and Member Activity Contribution', 'FELLOWSHIP_MEMBER_ACTIVITY_FUND', true],
        ['event_contribution', 'Legacy Event Contribution', null, false],
        ['other_contribution', 'Legacy Other Contribution', null, false],
    ];

    public function up(): void
    {
        $this->assertSafeExistingData();

        Schema::create('accounting_charge_categories', function (Blueprint $table): void {
            $table->string('code', 80)->primary();
            $table->string('display_name', 160);
            $table->string('fund_code', 50)->nullable();
            $table->boolean('is_accounting_enabled');
            $table->string('decision_reference', 40);
            $table->timestamps();
        });
        foreach (self::DEFINITIONS as [$code, $name, $fund, $enabled]) {
            DB::table('accounting_charge_categories')->insert([
                'code' => $code, 'display_name' => $name, 'fund_code' => $fund,
                'is_accounting_enabled' => $enabled, 'decision_reference' => 'DR-055',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('ledger_accounts', function (Blueprint $table): void {
            $table->string('origin_kind', 20)->nullable();
            $table->string('origin_template_code', 40)->nullable();
            $table->string('statement_classification', 40)->nullable();
        });
        Schema::table('club_charges', fn (Blueprint $table) => $table->string('fund_code', 50)->nullable());
        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->string('fund_code', 50)->nullable();
            $table->unique(['id', 'club_id'], 'payment_allocations_id_club_unique');
        });
        Schema::table('club_accounting_cutover_schedule_rows', function (Blueprint $table): void {
            $table->string('fund_code', 50)->nullable();
            $table->unique(['id', 'club_id'], 'cutover_schedule_id_club_unique');
            $table->unique(['cutover_package_id', 'ledger_account_id', 'fund_code'], 'cutover_account_fund_unique');
        });
        Schema::table('club_journal_lines', function (Blueprint $table): void {
            $table->string('fund_code', 50)->nullable();
            $table->unsignedBigInteger('opening_schedule_row_id')->nullable();
            $table->unsignedBigInteger('reversal_of_line_id')->nullable();
            $table->index(['club_id', 'fund_code', 'journal_entry_id'], 'journal_lines_fund_statement_idx');
            $table->foreign(['opening_schedule_row_id', 'club_id'], 'journal_line_opening_row_club_fk')->references(['id', 'club_id'])->on('club_accounting_cutover_schedule_rows')->restrictOnDelete();
            $table->foreign(['reversal_of_line_id', 'club_id'], 'journal_line_reversal_line_club_fk')->references(['id', 'club_id'])->on('club_journal_lines')->restrictOnDelete();
        });
        Schema::create('payment_allocation_journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('payment_allocation_id');
            $table->unsignedBigInteger('journal_line_id');
            $table->string('line_role', 30);
            $table->unsignedBigInteger('amount_paise');
            $table->timestamps();
            $table->unique(['payment_allocation_id', 'line_role'], 'allocation_journal_line_role_unique');
            $table->foreign(['payment_allocation_id', 'club_id'], 'allocation_line_allocation_club_fk')->references(['id', 'club_id'])->on('payment_allocations')->restrictOnDelete();
            $table->foreign(['journal_line_id', 'club_id'], 'allocation_line_journal_club_fk')->references(['id', 'club_id'])->on('club_journal_lines')->restrictOnDelete();
        });
        $this->restoreSqliteAccountingGuards();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocation_journal_lines');
        Schema::table('club_journal_lines', function (Blueprint $table): void {
            $table->dropForeign('journal_line_opening_row_club_fk');
            $table->dropForeign('journal_line_reversal_line_club_fk');
            $table->dropIndex('journal_lines_fund_statement_idx');
            $table->dropColumn(['fund_code', 'opening_schedule_row_id', 'reversal_of_line_id']);
        });
        Schema::table('club_accounting_cutover_schedule_rows', function (Blueprint $table): void {
            $table->dropUnique('cutover_account_fund_unique');
            $table->dropUnique('cutover_schedule_id_club_unique');
            $table->dropColumn('fund_code');
        });
        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->dropUnique('payment_allocations_id_club_unique');
            $table->dropColumn('fund_code');
        });
        Schema::table('club_charges', fn (Blueprint $table) => $table->dropColumn('fund_code'));
        Schema::table('ledger_accounts', fn (Blueprint $table) => $table->dropColumn(['origin_kind', 'origin_template_code', 'statement_classification']));
        Schema::dropIfExists('accounting_charge_categories');
    }

    private function assertSafeExistingData(): void
    {
        $ambiguous = DB::table('club_charges')->whereIn('category', ['event_contribution', 'other_contribution'])->pluck('id');
        if ($ambiguous->isNotEmpty()) {
            throw new RuntimeException('Ambiguous charge categories block fund migration. Charge IDs: '.$ambiguous->implode(','));
        }
        foreach ([
            'ledger_accounts' => 'Existing ledger accounts require an explicit reviewed template/classification mapping.',
            'club_journal_lines' => 'Existing journal lines require authoritative fund remediation.',
            'club_accounting_cutover_schedule_rows' => 'Existing opening rows require authoritative fund remediation.',
            'payment_allocations' => 'Existing allocations require authoritative frozen-fund remediation.',
        ] as $table => $message) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException($message);
            }
        }
    }

    private function restoreSqliteAccountingGuards(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        $checks = [
            ['ledger_accounts', 'ledger_account_type_check', "NEW.account_type IN ('asset','liability','equity','income','expense')"],
            ['ledger_accounts', 'ledger_account_normal_balance_check', "NEW.normal_balance IN ('debit','credit')"],
            ['ledger_accounts', 'ledger_account_currency_check', "NEW.currency = 'INR'"],
            ['ledger_accounts', 'ledger_account_code_check', 'length(trim(NEW.code)) > 0'],
            ['club_journal_lines', 'journal_line_direction_check', "NEW.direction IN ('debit','credit')"],
            ['club_journal_lines', 'journal_line_amount_check', 'NEW.amount_paise > 0'],
            ['club_journal_lines', 'journal_line_number_check', 'NEW.line_number > 0'],
        ];
        foreach ($checks as [$table, $name, $expression]) {
            foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $operation => $suffix) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$name}_{$suffix}");
                DB::unprepared("CREATE TRIGGER {$name}_{$suffix} BEFORE {$operation} ON {$table} FOR EACH ROW WHEN NOT ({$expression}) BEGIN SELECT RAISE(ABORT, '{$name}'); END");
            }
        }
    }
};
