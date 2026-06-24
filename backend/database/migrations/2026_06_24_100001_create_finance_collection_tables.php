<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('club_charges', function (Blueprint $t): void {
            $t->id(); $t->foreignId('club_id')->constrained()->restrictOnDelete();
            $t->string('title', 160); $t->text('description')->nullable(); $t->string('category', 40);
            $t->unsignedBigInteger('amount_paise'); $t->date('due_date'); $t->char('financial_year', 7);
            $t->string('status', 20)->default('draft'); $t->json('assignment_member_ids');
            $t->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('issued_at')->nullable(); $t->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('closed_at')->nullable(); $t->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable(); $t->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('cancellation_reason')->nullable(); $t->unsignedInteger('lock_version')->default(1); $t->timestamps();
            $t->index(['club_id','status']); $t->index(['club_id','financial_year']); $t->index(['club_id','due_date']);
        });
        Schema::create('member_charges', function (Blueprint $t): void {
            $t->id(); $t->foreignId('club_id')->constrained()->restrictOnDelete();
            $t->foreignId('club_charge_id')->constrained()->restrictOnDelete();
            $t->foreignId('member_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('original_amount_paise'); $t->unsignedInteger('lock_version')->default(1); $t->timestamps();
            $t->unique(['club_charge_id','member_id']); $t->index(['club_id','member_id']);
        });
        Schema::create('member_payments', function (Blueprint $t): void {
            $t->id(); $t->foreignId('club_id')->constrained()->restrictOnDelete(); $t->foreignId('member_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount_paise'); $t->date('payment_date'); $t->string('payment_mode', 30);
            $t->string('external_reference', 120)->nullable(); $t->text('internal_note')->nullable();
            $t->uuid('idempotency_key'); $t->char('request_hash', 64); $t->string('status', 20)->default('recorded');
            $t->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('voided_at')->nullable(); $t->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('void_reason')->nullable(); $t->foreignId('replacement_payment_id')->nullable()->constrained('member_payments')->nullOnDelete();
            $t->unsignedInteger('lock_version')->default(1); $t->timestamps();
            $t->unique(['club_id','idempotency_key']); $t->index(['club_id','member_id']); $t->index(['club_id','payment_date']);
        });
        Schema::create('payment_allocations', function (Blueprint $t): void {
            $t->id(); $t->foreignId('club_id')->constrained()->restrictOnDelete();
            $t->foreignId('member_payment_id')->constrained()->restrictOnDelete(); $t->foreignId('member_charge_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount_paise'); $t->timestamps();
            $t->unique(['member_payment_id','member_charge_id']); $t->index(['club_id','member_charge_id']);
        });
        Schema::create('payment_receipts', function (Blueprint $t): void {
            $t->id(); $t->foreignId('club_id')->constrained()->restrictOnDelete(); $t->foreignId('member_payment_id')->unique()->constrained()->restrictOnDelete();
            $t->string('receipt_number', 80); $t->date('receipt_date'); $t->char('financial_year', 7); $t->string('status', 20)->default('issued');
            $t->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete(); $t->timestamp('voided_at')->nullable(); $t->timestamps();
            $t->unique(['club_id','receipt_number']); $t->index(['club_id','financial_year']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('payment_receipts'); Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('member_payments'); Schema::dropIfExists('member_charges'); Schema::dropIfExists('club_charges');
    }
};
