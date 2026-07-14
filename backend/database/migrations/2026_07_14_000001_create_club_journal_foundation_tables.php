<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('account_type', 20);
            $table->string('normal_balance', 10);
            $table->char('currency', 3)->default('INR');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['club_id', 'code']);
            $table->unique(['id', 'club_id']);
            $table->index(['club_id', 'account_type', 'is_active']);
        });

        Schema::create('club_accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->char('lions_year', 7);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 10)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['club_id', 'lions_year']);
            $table->unique(['id', 'club_id']);
            $table->index(['club_id', 'status', 'start_date']);
        });

        Schema::create('club_journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('accounting_period_id');
            $table->unsignedBigInteger('sequence_number');
            $table->string('reference', 80);
            $table->date('effective_date');
            $table->date('posting_date');
            $table->timestamp('posted_at')->nullable();
            $table->string('status', 10)->default('draft');
            $table->text('description');
            $table->char('currency', 3)->default('INR');
            $table->string('source_type', 80)->nullable();
            $table->string('source_id', 120)->nullable();
            $table->string('source_event', 80)->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->unsignedBigInteger('reversal_of_entry_id')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'accounting_period_id', 'sequence_number']);
            $table->unique(['club_id', 'accounting_period_id', 'reference']);
            $table->unique(['club_id', 'idempotency_key']);
            $table->unique(['club_id', 'source_type', 'source_id', 'source_event']);
            $table->unique(['id', 'club_id']);
            $table->index(['club_id', 'accounting_period_id', 'posting_date', 'effective_date'], 'journal_statement_period_idx');
            $table->index(['club_id', 'status', 'posting_date']);
            $table->index(['club_id', 'reversal_of_entry_id']);

            $table->foreign(['accounting_period_id', 'club_id'], 'journal_period_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_periods')->restrictOnDelete();
            $table->foreign(['reversal_of_entry_id', 'club_id'], 'journal_reversal_club_fk')
                ->references(['id', 'club_id'])->on('club_journal_entries')->restrictOnDelete();
        });

        Schema::create('club_journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('ledger_account_id');
            $table->unsignedInteger('line_number');
            $table->string('direction', 6);
            $table->unsignedBigInteger('amount_paise');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['journal_entry_id', 'line_number']);
            $table->unique(['id', 'club_id']);
            $table->index(['club_id', 'ledger_account_id', 'journal_entry_id'], 'journal_lines_statement_idx');

            $table->foreign(['journal_entry_id', 'club_id'], 'journal_line_entry_club_fk')
                ->references(['id', 'club_id'])->on('club_journal_entries')->restrictOnDelete();
            $table->foreign(['ledger_account_id', 'club_id'], 'journal_line_account_club_fk')
                ->references(['id', 'club_id'])->on('ledger_accounts')->restrictOnDelete();
        });

        $this->addStructuralChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('club_journal_lines');
        Schema::dropIfExists('club_journal_entries');
        Schema::dropIfExists('club_accounting_periods');
        Schema::dropIfExists('ledger_accounts');
    }

    private function addStructuralChecks(): void
    {
        $this->addCheck('ledger_accounts', 'ledger_account_type_check',
            "NEW.account_type IN ('asset','liability','equity','income','expense')");
        $this->addCheck('ledger_accounts', 'ledger_account_normal_balance_check',
            "NEW.normal_balance IN ('debit','credit')");
        $this->addCheck('ledger_accounts', 'ledger_account_currency_check', "NEW.currency = 'INR'");
        $this->addCheck('ledger_accounts', 'ledger_account_code_check', 'length(trim(NEW.code)) > 0');

        $this->addCheck('club_accounting_periods', 'accounting_period_status_check',
            "NEW.status IN ('open','closed')");
        $this->addCheck('club_accounting_periods', 'accounting_period_close_metadata_check',
            "(NEW.status = 'open' AND NEW.closed_at IS NULL AND NEW.closed_by_user_id IS NULL) OR ".
            "(NEW.status = 'closed' AND NEW.closed_at IS NOT NULL AND NEW.closed_by_user_id IS NOT NULL)");
        $this->addLionsYearCheck();

        $this->addCheck('club_journal_entries', 'journal_entry_status_check',
            "NEW.status IN ('draft','posted','reversed','voided')");
        $this->addCheck('club_journal_entries', 'journal_entry_currency_check', "NEW.currency = 'INR'");
        $this->addCheck('club_journal_entries', 'journal_entry_sequence_check', 'NEW.sequence_number > 0');
        $this->addCheck('club_journal_entries', 'journal_entry_source_identity_check',
            '(NEW.source_type IS NULL AND NEW.source_id IS NULL AND NEW.source_event IS NULL AND NEW.idempotency_key IS NULL) OR '.
            '(NEW.source_type IS NOT NULL AND NEW.source_id IS NOT NULL AND NEW.source_event IS NOT NULL AND NEW.idempotency_key IS NOT NULL)');
        $this->addCheck('club_journal_entries', 'journal_entry_reversal_self_check',
            'NEW.reversal_of_entry_id IS NULL OR NEW.reversal_of_entry_id <> NEW.id');
        $this->addCheck('club_journal_entries', 'journal_entry_lifecycle_check',
            "(NEW.status = 'draft' AND NEW.posted_at IS NULL AND NEW.posted_by_user_id IS NULL AND NEW.voided_at IS NULL AND NEW.voided_by_user_id IS NULL) OR ".
            "(NEW.status IN ('posted','reversed') AND NEW.posted_at IS NOT NULL AND NEW.posted_by_user_id IS NOT NULL AND NEW.voided_at IS NULL AND NEW.voided_by_user_id IS NULL) OR ".
            "(NEW.status = 'voided' AND NEW.posted_at IS NULL AND NEW.posted_by_user_id IS NULL AND NEW.voided_at IS NOT NULL AND NEW.voided_by_user_id IS NOT NULL)");

        $this->addCheck('club_journal_lines', 'journal_line_direction_check',
            "NEW.direction IN ('debit','credit')");
        $this->addCheck('club_journal_lines', 'journal_line_amount_check', 'NEW.amount_paise > 0');
        $this->addCheck('club_journal_lines', 'journal_line_number_check', 'NEW.line_number > 0');
    }

    private function addLionsYearCheck(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $expression = "strftime('%m-%d', NEW.start_date) = '07-01' ".
                "AND strftime('%m-%d', NEW.end_date) = '06-30' ".
                "AND NEW.end_date = date(NEW.start_date, '+1 year', '-1 day') ".
                "AND NEW.lions_year = printf('%04d-%02d', ".
                "CAST(strftime('%Y', NEW.start_date) AS INTEGER), ".
                "CAST(strftime('%Y', NEW.end_date) AS INTEGER) % 100)";
            $this->addSqliteTriggerCheck('club_accounting_periods', 'accounting_period_lions_year_check', $expression);

            return;
        }

        $expression = 'EXTRACT(MONTH FROM start_date) = 7 AND EXTRACT(DAY FROM start_date) = 1 '.
            'AND EXTRACT(MONTH FROM end_date) = 6 AND EXTRACT(DAY FROM end_date) = 30 '.
            "AND end_date = (start_date + INTERVAL '1 year - 1 day')::date ".
            "AND lions_year = to_char(start_date, 'YYYY') || '-' || to_char(end_date, 'YY')";
        $this->addCheck('club_accounting_periods', 'accounting_period_lions_year_check', $expression);
    }

    private function addCheck(string $table, string $name, string $expression): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->addSqliteTriggerCheck($table, $name, $expression);

            return;
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK (".str_replace('NEW.', '', $expression).')');
    }

    private function addSqliteTriggerCheck(string $table, string $name, string $expression): void
    {
        foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $operation => $suffix) {
            DB::unprepared(
                "CREATE TRIGGER {$name}_{$suffix} BEFORE {$operation} ON {$table} ".
                "FOR EACH ROW WHEN NOT ({$expression}) BEGIN SELECT RAISE(ABORT, '{$name}'); END"
            );
        }
    }
};
