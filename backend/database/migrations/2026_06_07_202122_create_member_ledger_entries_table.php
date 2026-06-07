<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('entry_type', 20);
            $table->string('source_type', 50);

            $table->decimal('amount', 12, 2);

            $table->char('accounting_month', 7)->nullable();
            $table->date('due_date')->nullable();
            $table->date('transaction_date')->nullable();

            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            $table->string('status', 30)->default('posted');

            $table->timestamps();

            $table->index(['club_id', 'member_id']);
            $table->index(['club_id', 'entry_type']);
            $table->index(['club_id', 'source_type']);
            $table->index(['member_id', 'accounting_month']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_ledger_entries');
    }
};