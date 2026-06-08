<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('receipt_number');
            $table->date('receipt_date');
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', [
                'issued',
                'cancelled',
            ])->default('issued');
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'receipt_number']);
            $table->index(['club_id', 'payment_id']);
            $table->index(['club_id', 'receipt_date']);
            $table->index(['club_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};