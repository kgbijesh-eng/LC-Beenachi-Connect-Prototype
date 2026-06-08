<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->enum('payment_mode', [
                'cash',
                'bank_transfer',
                'upi',
                'cheque',
                'card',
                'adjustment',
            ]);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('member_ledger_entries')->restrictOnDelete();
            $table->enum('status', [
                'draft',
                'posted',
                'cancelled',
                'reversed',
            ])->default('draft');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'member_id']);
            $table->index(['club_id', 'payment_date']);
            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'payment_mode']);
            $table->index('reference_number');
            $table->index('ledger_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};