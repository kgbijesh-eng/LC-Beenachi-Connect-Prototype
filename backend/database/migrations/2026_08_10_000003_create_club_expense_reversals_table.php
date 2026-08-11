<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_expenses', function (Blueprint $table): void {
            $table->foreignId('corrected_from_expense_id')->nullable()->after('journal_entry_id')->constrained('club_expenses')->restrictOnDelete();
            $table->unique('corrected_from_expense_id', 'club_expenses_one_correction_unique');
        });

        Schema::create('club_expense_reversals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('club_expense_id')->constrained('club_expenses')->restrictOnDelete();
            $table->foreignId('reversal_journal_entry_id')->constrained('club_journal_entries')->restrictOnDelete();
            $table->date('accounting_date');
            $table->string('reason', 1000);
            $table->uuid('idempotency_key');
            $table->char('request_digest', 64);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'club_expense_id'], 'club_expense_reversals_expense_unique');
            $table->unique(['club_id', 'idempotency_key'], 'club_expense_reversals_idempotency_unique');
            $table->unique(['club_id', 'reversal_journal_entry_id'], 'club_expense_reversals_journal_unique');
            $table->index(['club_id', 'accounting_date'], 'club_expense_reversals_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_expense_reversals');
        Schema::table('club_expenses', function (Blueprint $table): void {
            $table->dropUnique('club_expenses_one_correction_unique');
            $table->dropConstrainedForeignId('corrected_from_expense_id');
        });
    }
};
