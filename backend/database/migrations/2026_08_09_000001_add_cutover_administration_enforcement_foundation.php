<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_accounting_cutover_bootstrap_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('operation_reference', 120)->unique();
            $table->unsignedInteger('package_version');
            $table->char('lions_year', 7);
            $table->date('cutover_date');
            $table->string('chart_version', 40);
            $table->foreignId('preparer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->char('request_digest', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('cutover_package_id')->nullable();
            $table->unsignedBigInteger('permission_assignment_id')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'cutover_date', 'package_version'], 'cutover_bootstrap_boundary_unique');
            $table->foreign('cutover_package_id', 'cutover_bootstrap_package_fk')->references('id')->on('club_accounting_cutover_packages')->restrictOnDelete();
            $table->foreign('permission_assignment_id', 'cutover_bootstrap_assignment_fk')->references('id')->on('club_accounting_cutover_permission_assignments')->restrictOnDelete();
        });
        Schema::create('club_accounting_cutover_bootstrap_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('bootstrap_operation_id')->constrained('club_accounting_cutover_bootstrap_operations')->restrictOnDelete();
            $table->string('approval_role', 20);
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('approval_reference', 120)->unique();
            $table->char('operation_digest', 64);
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(['bootstrap_operation_id', 'approval_role'], 'cutover_bootstrap_one_role_approval_unique');
            $table->unique(['bootstrap_operation_id', 'approved_by_user_id'], 'cutover_bootstrap_distinct_actor_unique');
        });
        Schema::create('club_accounting_cutover_admin_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->string('operation_reference', 120)->unique();
            $table->unsignedInteger('package_version');
            $table->unsignedInteger('package_lock_version');
            $table->string('action', 40);
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('target_type', 40);
            $table->text('target_key');
            $table->unsignedBigInteger('target_record_id')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->char('request_digest', 64);
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_admin_operation_package_club_fk')->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
            $table->index(['cutover_package_id', 'action', 'status'], 'cutover_admin_operation_lookup_idx');
        });
        Schema::create('club_accounting_cutover_admin_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->foreignId('admin_operation_id')->constrained('club_accounting_cutover_admin_operations')->restrictOnDelete();
            $table->string('approval_role', 20);
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('approval_reference', 120)->unique();
            $table->char('operation_digest', 64);
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(['admin_operation_id', 'approval_role'], 'cutover_admin_one_role_approval_unique');
            $table->unique(['admin_operation_id', 'approved_by_user_id'], 'cutover_admin_distinct_actor_unique');
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_admin_approval_package_club_fk')->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });
        Schema::create('club_accounting_cutover_evidence_verifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->unsignedBigInteger('evidence_reference_id');
            $table->uuid('evidence_reference_public_id');
            $table->char('evidence_binding_digest', 64);
            $table->unsignedInteger('package_version');
            $table->unsignedInteger('package_lock_version');
            $table->string('result', 20);
            $table->string('verification_reference', 120)->unique();
            $table->string('safe_note', 500)->nullable();
            $table->foreignId('verified_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_evidence_verification_package_club_fk')->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });
        Schema::create('club_accounting_cutover_ca_eligibilities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->foreignId('authority_appointment_id')->constrained('club_accounting_cutover_authority_appointments')->restrictOnDelete();
            $table->string('external_reference_set', 500);
            $table->char('external_reference_digest', 64)->nullable();
            $table->date('checked_on');
            $table->string('independence_state', 20);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('admin_operation_id')->unique()->constrained('club_accounting_cutover_admin_operations')->restrictOnDelete();
            $table->timestamp('eligible_at');
            $table->timestamps();
            $table->unique('authority_appointment_id', 'cutover_ca_eligibility_appointment_unique');
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_ca_eligibility_package_club_fk')->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION cutover_admin_reject_mutation() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'cutover administration provenance is immutable'; END; \$\$");
            foreach (['club_accounting_cutover_bootstrap_approvals', 'club_accounting_cutover_admin_approvals', 'club_accounting_cutover_evidence_verifications', 'club_accounting_cutover_ca_eligibilities'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION cutover_admin_reject_mutation()");
            }
            DB::unprepared("CREATE OR REPLACE FUNCTION cutover_guard_current_permission() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN PERFORM 1 FROM club_accounting_cutover_packages WHERE id=NEW.cutover_package_id FOR UPDATE; IF EXISTS (SELECT 1 FROM club_accounting_cutover_permission_assignments a WHERE a.cutover_package_id=NEW.cutover_package_id AND a.user_id=NEW.user_id AND a.permission_id=NEW.permission_id AND a.effective_at<=CURRENT_TIMESTAMP AND (a.expires_at IS NULL OR a.expires_at>CURRENT_TIMESTAMP) AND NOT EXISTS (SELECT 1 FROM club_accounting_cutover_permission_events e WHERE e.permission_assignment_id=a.id AND e.event_type IN ('revoked','invalidated'))) THEN RAISE EXCEPTION 'one current package permission assignment is permitted'; END IF; RETURN NEW; END; \$\$");
            DB::unprepared('CREATE TRIGGER cutover_one_current_permission_guard BEFORE INSERT ON club_accounting_cutover_permission_assignments FOR EACH ROW EXECUTE FUNCTION cutover_guard_current_permission()');
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['club_accounting_cutover_bootstrap_approvals', 'club_accounting_cutover_admin_approvals', 'club_accounting_cutover_evidence_verifications', 'club_accounting_cutover_ca_eligibilities'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'cutover administration provenance is immutable'); END");
                DB::unprepared("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'cutover administration provenance is immutable'); END");
            }
            DB::unprepared("CREATE TRIGGER cutover_one_current_permission_guard BEFORE INSERT ON club_accounting_cutover_permission_assignments WHEN EXISTS (SELECT 1 FROM club_accounting_cutover_permission_assignments a WHERE a.cutover_package_id=NEW.cutover_package_id AND a.user_id=NEW.user_id AND a.permission_id=NEW.permission_id AND a.effective_at<=CURRENT_TIMESTAMP AND (a.expires_at IS NULL OR a.expires_at>CURRENT_TIMESTAMP) AND NOT EXISTS (SELECT 1 FROM club_accounting_cutover_permission_events e WHERE e.permission_assignment_id=a.id AND e.event_type IN ('revoked','invalidated'))) BEGIN SELECT RAISE(ABORT, 'one current package permission assignment is permitted'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS cutover_one_current_permission_guard ON club_accounting_cutover_permission_assignments');
            DB::statement('DROP FUNCTION IF EXISTS cutover_guard_current_permission()');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS cutover_one_current_permission_guard');
        }
        Schema::dropIfExists('club_accounting_cutover_ca_eligibilities');
        Schema::dropIfExists('club_accounting_cutover_evidence_verifications');
        Schema::dropIfExists('club_accounting_cutover_admin_approvals');
        Schema::dropIfExists('club_accounting_cutover_admin_operations');
        Schema::dropIfExists('club_accounting_cutover_bootstrap_approvals');
        Schema::dropIfExists('club_accounting_cutover_bootstrap_operations');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS cutover_admin_reject_mutation()');
        }
    }
};
