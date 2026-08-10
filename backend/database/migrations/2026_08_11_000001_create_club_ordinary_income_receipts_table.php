<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_ordinary_income_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('subtype', 50);
            $table->date('accounting_date');
            $table->foreignId('receiving_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignId('income_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('fund_code', 50);
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 3);
            $table->string('description', 500);
            $table->string('payer_name', 160)->nullable();
            $table->string('external_reference', 120)->nullable();
            $table->string('event_label', 160)->nullable();
            $table->uuid('idempotency_key');
            $table->char('request_digest', 64);
            $table->foreignId('journal_entry_id')->nullable()->constrained('club_journal_entries')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'idempotency_key'], 'ordinary_income_receipts_idempotency_unique');
            $table->unique(['club_id', 'journal_entry_id'], 'ordinary_income_receipts_journal_unique');
            $table->index(['club_id', 'accounting_date', 'id'], 'ordinary_income_receipts_history_idx');
            $table->index(['club_id', 'subtype'], 'ordinary_income_receipts_subtype_idx');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE club_ordinary_income_receipts ADD CONSTRAINT ordinary_income_receipts_amount_check CHECK (amount_paise > 0)');
            DB::statement("ALTER TABLE club_ordinary_income_receipts ADD CONSTRAINT ordinary_income_receipts_currency_check CHECK (currency = 'INR')");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['ordinary_income_receipts_amount_check' => 'NEW.amount_paise > 0', 'ordinary_income_receipts_currency_check' => "NEW.currency = 'INR'"] as $name => $expression) {
                foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $operation => $suffix) DB::unprepared("CREATE TRIGGER {$name}_{$suffix} BEFORE {$operation} ON club_ordinary_income_receipts FOR EACH ROW WHEN NOT ({$expression}) BEGIN SELECT RAISE(ABORT, '{$name}'); END");
            }
        }
    }

    public function down(): void { Schema::dropIfExists('club_ordinary_income_receipts'); }
};
