<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_accounting_cutover_packages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->char('lions_year', 7);
            $table->date('cutover_date');
            $table->string('chart_version', 40);
            $table->unsignedInteger('package_version');
            $table->string('status', 30)->default('draft');
            $table->char('currency', 3)->default('INR');
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->char('canonical_checksum', 64)->nullable();
            $table->bigInteger('reconciliation_difference_paise')->default(0);
            $table->string('dr033_classification', 40)->nullable();
            $table->boolean('activation_ready')->default(false);
            $table->timestamp('readiness_checked_at')->nullable();
            $table->text('invalidation_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['club_id', 'cutover_date', 'package_version'], 'cutover_package_version_unique');
            $table->unique(['id', 'club_id']);
            $table->index(['club_id', 'status', 'cutover_date']);
            $table->index(['club_id', 'canonical_checksum']);
        });

        Schema::create('club_accounting_cutover_schedule_rows', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->string('schedule_type', 40);
            $table->string('row_key', 80);
            $table->string('non_sensitive_label', 160);
            $table->unsignedBigInteger('ledger_account_id')->nullable();
            $table->string('presentation_group', 40)->nullable();
            $table->string('direction', 6);
            $table->unsignedBigInteger('amount_paise');
            $table->timestamps();

            $table->unique(['cutover_package_id', 'row_key'], 'cutover_schedule_row_unique');
            $table->index(['club_id', 'schedule_type']);
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_schedule_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
            $table->foreign(['ledger_account_id', 'club_id'], 'cutover_schedule_account_club_fk')
                ->references(['id', 'club_id'])->on('ledger_accounts')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_evidence_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->string('evidence_type', 40);
            $table->string('opaque_reference', 120);
            $table->date('evidence_date');
            $table->string('evidence_version', 40);
            $table->char('digest_sha256', 64)->nullable();
            $table->string('verification_status', 20);
            $table->string('reviewer_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['cutover_package_id', 'opaque_reference'], 'cutover_evidence_reference_unique');
            $table->index(['club_id', 'evidence_type', 'verification_status'], 'cutover_evidence_lookup_idx');
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_evidence_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->string('reviewer_identifier', 160);
            $table->date('review_date');
            $table->string('selected_classification', 40);
            $table->text('written_rationale');
            $table->unsignedInteger('package_version');
            $table->string('chart_version', 40);
            $table->char('package_checksum', 64);
            $table->string('status', 20)->default('current');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->text('invalidation_reason')->nullable();
            $table->timestamps();

            $table->index(['cutover_package_id', 'status']);
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_review_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->string('approval_authority', 160);
            $table->date('approval_date');
            $table->string('approval_reference', 120);
            $table->unsignedInteger('package_version');
            $table->string('chart_version', 40);
            $table->char('package_checksum', 64);
            $table->string('status', 20)->default('current');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->text('invalidation_reason')->nullable();
            $table->timestamps();

            $table->unique(['cutover_package_id', 'approval_reference'], 'cutover_approval_reference_unique');
            $table->index(['cutover_package_id', 'status']);
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_approval_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_accounting_cutover_approvals');
        Schema::dropIfExists('club_accounting_cutover_reviews');
        Schema::dropIfExists('club_accounting_cutover_evidence_references');
        Schema::dropIfExists('club_accounting_cutover_schedule_rows');
        Schema::dropIfExists('club_accounting_cutover_packages');
    }
};
