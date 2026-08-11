<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_expenses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->date('accounting_date');
            $table->foreignId('expense_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignId('payment_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('fund_code', 50);
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 3);
            $table->string('payee', 160)->nullable();
            $table->string('description', 500);
            $table->string('external_reference', 120)->nullable();
            $table->uuid('idempotency_key');
            $table->char('request_digest', 64);
            $table->foreignId('journal_entry_id')->nullable()->constrained('club_journal_entries')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'idempotency_key'], 'club_expenses_idempotency_unique');
            $table->unique(['club_id', 'journal_entry_id'], 'club_expenses_journal_unique');
            $table->index(['club_id', 'accounting_date'], 'club_expenses_date_idx');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE club_expenses ADD CONSTRAINT club_expenses_amount_check CHECK (amount_paise > 0)');
            DB::statement("ALTER TABLE club_expenses ADD CONSTRAINT club_expenses_currency_check CHECK (currency = 'INR')");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach ([
                'club_expenses_amount_check' => 'NEW.amount_paise > 0',
                'club_expenses_currency_check' => "NEW.currency = 'INR'",
            ] as $name => $expression) {
                foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $operation => $suffix) {
                    DB::unprepared("CREATE TRIGGER {$name}_{$suffix} BEFORE {$operation} ON club_expenses FOR EACH ROW WHEN NOT ({$expression}) BEGIN SELECT RAISE(ABORT, '{$name}'); END");
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_expenses');
    }
};
