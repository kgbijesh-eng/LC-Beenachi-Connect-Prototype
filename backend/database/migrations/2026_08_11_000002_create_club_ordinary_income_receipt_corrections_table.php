<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_ordinary_income_receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('corrected_from_receipt_id')->nullable()->after('journal_entry_id');
            $table->unique('corrected_from_receipt_id', 'ordinary_income_receipts_one_replacement_unique');
            $table->unique(['id', 'club_id'], 'ordinary_income_receipts_id_club_unique');
            $table->unique(['id', 'corrected_from_receipt_id', 'club_id'], 'ordinary_income_receipts_replacement_lineage_unique');
            $table->foreign(['corrected_from_receipt_id', 'club_id'], 'ordinary_income_receipt_corrected_from_club_fk')->references(['id', 'club_id'])->on('club_ordinary_income_receipts')->restrictOnDelete();
        });

        Schema::create('club_ordinary_income_receipt_corrections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id');
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('original_receipt_id');
            $table->unsignedBigInteger('original_journal_entry_id');
            $table->unsignedBigInteger('reversal_journal_entry_id');
            $table->unsignedBigInteger('replacement_receipt_id')->nullable();
            $table->date('accounting_date');
            $table->string('reason', 1000);
            $table->uuid('idempotency_key');
            $table->char('request_digest', 64);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'public_id'], 'ordinary_income_corrections_public_unique');
            $table->unique(['club_id', 'original_receipt_id'], 'ordinary_income_corrections_original_unique');
            $table->unique(['club_id', 'original_journal_entry_id'], 'ordinary_income_corrections_original_journal_unique');
            $table->unique(['club_id', 'reversal_journal_entry_id'], 'ordinary_income_corrections_reversal_journal_unique');
            $table->unique(['club_id', 'replacement_receipt_id'], 'ordinary_income_corrections_replacement_unique');
            $table->unique(['club_id', 'idempotency_key'], 'ordinary_income_corrections_idempotency_unique');
            $table->index(['club_id', 'accounting_date', 'id'], 'ordinary_income_corrections_history_idx');
            $table->foreign(['original_receipt_id', 'club_id'], 'ordinary_income_correction_original_club_fk')->references(['id', 'club_id'])->on('club_ordinary_income_receipts')->restrictOnDelete();
            $table->foreign(['original_journal_entry_id', 'club_id'], 'ordinary_income_correction_original_journal_club_fk')->references(['id', 'club_id'])->on('club_journal_entries')->restrictOnDelete();
            $table->foreign(['reversal_journal_entry_id', 'club_id'], 'ordinary_income_correction_reversal_journal_club_fk')->references(['id', 'club_id'])->on('club_journal_entries')->restrictOnDelete();
            $table->foreign(['replacement_receipt_id', 'club_id'], 'ordinary_income_correction_replacement_club_fk')->references(['id', 'club_id'])->on('club_ordinary_income_receipts')->restrictOnDelete();
            $table->foreign(['replacement_receipt_id', 'original_receipt_id', 'club_id'], 'ordinary_income_correction_replacement_lineage_fk')->references(['id', 'corrected_from_receipt_id', 'club_id'])->on('club_ordinary_income_receipts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_ordinary_income_receipt_corrections');
        Schema::table('club_ordinary_income_receipts', function (Blueprint $table): void {
            $table->dropUnique('ordinary_income_receipts_one_replacement_unique');
            $table->dropForeign('ordinary_income_receipt_corrected_from_club_fk');
            $table->dropUnique('ordinary_income_receipts_id_club_unique');
            $table->dropUnique('ordinary_income_receipts_replacement_lineage_unique');
            $table->dropColumn('corrected_from_receipt_id');
        });
    }
};
