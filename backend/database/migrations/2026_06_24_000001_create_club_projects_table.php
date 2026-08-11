<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('record_type', 20);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('objective')->nullable();
            $table->string('service_category', 100)->nullable();
            $table->string('visibility', 20);
            $table->string('status', 20)->default('draft');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('location', 255)->nullable();
            $table->foreignId('lead_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->unsignedInteger('expected_beneficiary_count')->nullable();
            $table->unsignedInteger('actual_beneficiary_count')->nullable();
            $table->text('impact_summary')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('planned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('planned_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('started_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['club_id', 'status', 'planned_start_date']);
            $table->index(['club_id', 'record_type', 'planned_start_date']);
            $table->index(['club_id', 'visibility', 'status']);
            $table->index(['club_id', 'service_category']);
            $table->index(['club_id', 'planned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_projects');
    }
};