<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('visibility', 20);
            $table->string('status', 20)->default('draft');
            $table->boolean('is_all_day')->default(true);
            $table->date('due_date')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['club_id', 'status', 'due_date']);
            $table->index(['club_id', 'status', 'due_at']);
            $table->index(['club_id', 'visibility', 'status']);
            $table->index(['club_id', 'priority']);
            $table->index(['club_id', 'activated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_reminders');
    }
};
