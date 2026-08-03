<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_accounting_cutover_packages', function (Blueprint $table): void {
            $table->foreignId('internally_verified_by_user_id')->nullable()->after('prepared_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('internally_verified_at')->nullable()->after('internally_verified_by_user_id');
            $table->unsignedBigInteger('opening_journal_entry_id')->nullable()->after('invalidation_reason');
            $table->timestamp('posted_at')->nullable()->after('opening_journal_entry_id');
            $table->foreignId('posted_by_user_id')->nullable()->after('posted_at')->constrained('users')->restrictOnDelete();
            $table->unique('opening_journal_entry_id', 'cutover_package_opening_journal_unique');
            $table->foreign(['opening_journal_entry_id', 'club_id'], 'cutover_package_journal_club_fk')
                ->references(['id', 'club_id'])->on('club_journal_entries')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_authority_appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->foreignId('appointed_user_id')->constrained('users')->restrictOnDelete();
            $table->string('authority_type', 30);
            $table->string('appointment_reference', 120);
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('appointed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['cutover_package_id', 'authority_type', 'appointment_reference'], 'cutover_appointment_reference_unique');
            $table->index(['cutover_package_id', 'authority_type', 'appointed_user_id'], 'cutover_appointment_resolve_idx');
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_appointment_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_authority_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->foreignId('authority_appointment_id')->constrained('club_accounting_cutover_authority_appointments')->restrictOnDelete();
            $table->string('event_type', 20);
            $table->string('event_reference', 120)->unique();
            $table->text('reason');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['authority_appointment_id', 'event_type']);
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_authority_event_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_permission_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->string('assignment_reference', 120);
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['cutover_package_id', 'user_id', 'permission_id', 'assignment_reference'], 'cutover_permission_assignment_unique');
            $table->index(['cutover_package_id', 'user_id', 'permission_id'], 'cutover_permission_resolve_idx');
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_permission_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_permission_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->foreignId('permission_assignment_id')->constrained('club_accounting_cutover_permission_assignments')->restrictOnDelete();
            $table->string('event_type', 20);
            $table->string('event_reference', 120)->unique();
            $table->text('reason');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['permission_assignment_id', 'event_type']);
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_permission_event_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });

        Schema::table('club_accounting_cutover_reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('authority_appointment_id')->nullable()->after('recorded_by_user_id');
            $table->unsignedBigInteger('permission_assignment_id')->nullable()->after('authority_appointment_id');
            $table->string('authority_basis', 40)->nullable()->after('permission_assignment_id');
            $table->foreign('authority_appointment_id', 'cutover_review_appointment_fk')->references('id')->on('club_accounting_cutover_authority_appointments')->restrictOnDelete();
            $table->foreign('permission_assignment_id', 'cutover_review_permission_fk')->references('id')->on('club_accounting_cutover_permission_assignments')->restrictOnDelete();
        });

        Schema::table('club_accounting_cutover_approvals', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_assignment_id')->nullable()->after('recorded_by_user_id');
            $table->string('authority_basis', 40)->nullable()->after('permission_assignment_id');
            $table->foreign('permission_assignment_id', 'cutover_approval_permission_fk')->references('id')->on('club_accounting_cutover_permission_assignments')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_review_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->foreignId('cutover_review_id')->constrained('club_accounting_cutover_reviews')->restrictOnDelete();
            $table->string('event_type', 20);
            $table->string('event_reference', 120)->unique();
            $table->text('reason');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_review_event_package_club_fk')->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_approval_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->foreignId('cutover_approval_id')->constrained('club_accounting_cutover_approvals')->restrictOnDelete();
            $table->string('event_type', 20);
            $table->string('event_reference', 120)->unique();
            $table->text('reason');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_approval_event_package_club_fk')->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->string('decision_stage', 20);
            $table->string('actor_authority', 20);
            $table->string('decision_result', 20);
            $table->string('decision_reference', 120)->unique();
            $table->unsignedInteger('package_version');
            $table->char('canonical_checksum', 64);
            $table->string('chart_version', 40);
            $table->char('lions_year', 7);
            $table->date('cutover_date');
            $table->string('dr033_classification', 40);
            $table->unsignedBigInteger('accounting_review_id');
            $table->unsignedBigInteger('president_approval_id');
            $table->unsignedBigInteger('authority_appointment_id')->nullable();
            $table->unsignedBigInteger('permission_assignment_id');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['cutover_package_id', 'decision_stage', 'actor_authority'], 'cutover_decision_resolve_idx');
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_decision_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
            $table->foreign('accounting_review_id', 'cutover_decision_review_fk')->references('id')->on('club_accounting_cutover_reviews')->restrictOnDelete();
            $table->foreign('president_approval_id', 'cutover_decision_approval_fk')->references('id')->on('club_accounting_cutover_approvals')->restrictOnDelete();
            $table->foreign('authority_appointment_id', 'cutover_decision_appointment_fk')->references('id')->on('club_accounting_cutover_authority_appointments')->restrictOnDelete();
            $table->foreign('permission_assignment_id', 'cutover_decision_permission_fk')->references('id')->on('club_accounting_cutover_permission_assignments')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_decision_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->foreignId('cutover_decision_id')->constrained('club_accounting_cutover_decisions')->restrictOnDelete();
            $table->string('event_type', 20);
            $table->string('event_reference', 120)->unique();
            $table->text('reason');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('permission_assignment_id');
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['cutover_decision_id', 'event_type']);
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_decision_event_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
            $table->foreign('permission_assignment_id', 'cutover_decision_event_permission_fk')->references('id')->on('club_accounting_cutover_permission_assignments')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_execution_successes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->unsignedInteger('package_version');
            $table->char('canonical_checksum', 64);
            $table->char('lions_year', 7);
            $table->date('cutover_date');
            $table->string('opening_source_type', 80);
            $table->string('execution_reference', 120)->unique();
            $table->uuid('idempotency_key');
            $table->unsignedBigInteger('president_activation_decision_id');
            $table->unsignedBigInteger('ca_activation_decision_id');
            $table->unsignedBigInteger('journal_entry_id');
            $table->foreignId('executed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('technical_appointment_id');
            $table->unsignedBigInteger('permission_assignment_id');
            $table->timestamp('committed_at');
            $table->timestamps();
            $table->unique(['cutover_package_id', 'package_version', 'canonical_checksum'], 'cutover_success_package_binding_unique');
            $table->unique('cutover_package_id', 'cutover_success_package_unique');
            $table->unique(['club_id', 'lions_year', 'cutover_date', 'opening_source_type'], 'cutover_success_boundary_unique');
            $table->unique('journal_entry_id', 'cutover_success_journal_unique');
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_success_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
            $table->foreign(['journal_entry_id', 'club_id'], 'cutover_success_journal_club_fk')
                ->references(['id', 'club_id'])->on('club_journal_entries')->restrictOnDelete();
            $table->foreign('president_activation_decision_id', 'cutover_success_president_decision_fk')->references('id')->on('club_accounting_cutover_decisions')->restrictOnDelete();
            $table->foreign('ca_activation_decision_id', 'cutover_success_ca_decision_fk')->references('id')->on('club_accounting_cutover_decisions')->restrictOnDelete();
            $table->foreign('technical_appointment_id', 'cutover_success_operator_fk')->references('id')->on('club_accounting_cutover_authority_appointments')->restrictOnDelete();
            $table->foreign('permission_assignment_id', 'cutover_success_permission_fk')->references('id')->on('club_accounting_cutover_permission_assignments')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_authority_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->unsignedInteger('package_version');
            $table->char('canonical_checksum', 64);
            $table->char('lions_year', 7);
            $table->date('cutover_date');
            $table->string('opening_source_type', 80);
            $table->unsignedBigInteger('president_activation_decision_id');
            $table->unsignedBigInteger('ca_activation_decision_id');
            $table->unsignedBigInteger('execution_success_id');
            $table->unsignedBigInteger('journal_entry_id');
            $table->timestamp('consumed_at');
            $table->timestamps();
            $table->unique(['president_activation_decision_id', 'ca_activation_decision_id'], 'cutover_consumption_pair_unique');
            $table->unique(['cutover_package_id', 'package_version', 'canonical_checksum'], 'cutover_consumption_package_binding_unique');
            $table->unique(['club_id', 'lions_year', 'cutover_date', 'opening_source_type'], 'cutover_consumption_boundary_unique');
            $table->unique('execution_success_id', 'cutover_consumption_success_unique');
            $table->unique('journal_entry_id', 'cutover_consumption_journal_unique');
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_consumption_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
            $table->foreign('execution_success_id', 'cutover_consumption_success_fk')->references('id')->on('club_accounting_cutover_execution_successes')->restrictOnDelete();
            $table->foreign(['journal_entry_id', 'club_id'], 'cutover_consumption_journal_club_fk')
                ->references(['id', 'club_id'])->on('club_journal_entries')->restrictOnDelete();
            $table->foreign('president_activation_decision_id', 'cutover_consumption_president_decision_fk')->references('id')->on('club_accounting_cutover_decisions')->restrictOnDelete();
            $table->foreign('ca_activation_decision_id', 'cutover_consumption_ca_decision_fk')->references('id')->on('club_accounting_cutover_decisions')->restrictOnDelete();
        });

        Schema::create('club_accounting_cutover_execution_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('cutover_package_id');
            $table->string('event_type', 30);
            $table->string('event_reference', 120)->unique();
            $table->string('attempted_execution_reference', 120);
            $table->unsignedBigInteger('execution_success_id')->nullable();
            $table->string('result', 30);
            $table->string('reason_code', 80)->nullable();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['cutover_package_id', 'attempted_execution_reference'], 'cutover_execution_event_lookup_idx');
            $table->foreign(['cutover_package_id', 'club_id'], 'cutover_execution_event_package_club_fk')
                ->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
            $table->foreign('execution_success_id', 'cutover_execution_event_success_fk')->references('id')->on('club_accounting_cutover_execution_successes')->restrictOnDelete();
        });

        $this->addChecks();
        $this->addImmutabilityAndLifecycleTriggers();
        $this->restoreAlteredTableChecksForSqlite();
        DB::statement('DROP INDEX IF EXISTS cutover_one_current_review_idx');
        DB::statement('DROP INDEX IF EXISTS cutover_one_current_approval_idx');
        DB::statement("CREATE UNIQUE INDEX cutover_one_current_review_idx ON club_accounting_cutover_reviews (cutover_package_id) WHERE status = 'current'");
        DB::statement("CREATE UNIQUE INDEX cutover_one_current_approval_idx ON club_accounting_cutover_approvals (cutover_package_id) WHERE status = 'current'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS cutover_package_posting_metadata_guard');
            DB::unprepared('DROP TRIGGER IF EXISTS cutover_package_accounting_content_guard');
            DB::unprepared('DROP TRIGGER IF EXISTS cutover_package_checksum_guard');
            foreach (['club_accounting_cutover_schedule_rows', 'club_accounting_cutover_evidence_references'] as $table) {
                foreach (['insert', 'update', 'delete'] as $operation) {
                    DB::unprepared("DROP TRIGGER IF EXISTS {$table}_prepared_immutable_{$operation}");
                }
            }
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS cutover_package_posting_metadata_guard ON club_accounting_cutover_packages');
            DB::unprepared('DROP FUNCTION IF EXISTS cutover_guard_posting_metadata()');
            DB::unprepared('DROP FUNCTION IF EXISTS cutover_reject_mutation() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS cutover_guard_active_appointment() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS cutover_guard_accounting_content() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS cutover_guard_child_accounting_content() CASCADE');
        }
        Schema::dropIfExists('club_accounting_cutover_execution_events');
        Schema::dropIfExists('club_accounting_cutover_authority_consumptions');
        Schema::dropIfExists('club_accounting_cutover_execution_successes');
        Schema::dropIfExists('club_accounting_cutover_decision_events');
        Schema::dropIfExists('club_accounting_cutover_decisions');
        Schema::dropIfExists('club_accounting_cutover_approval_events');
        Schema::dropIfExists('club_accounting_cutover_review_events');
        Schema::table('club_accounting_cutover_approvals', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['permission_assignment_id'] : 'cutover_approval_permission_fk');
            $table->dropColumn(['permission_assignment_id', 'authority_basis']);
        });
        Schema::table('club_accounting_cutover_reviews', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['authority_appointment_id'] : 'cutover_review_appointment_fk');
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['permission_assignment_id'] : 'cutover_review_permission_fk');
            $table->dropColumn(['authority_appointment_id', 'permission_assignment_id', 'authority_basis']);
        });
        Schema::dropIfExists('club_accounting_cutover_permission_events');
        Schema::dropIfExists('club_accounting_cutover_permission_assignments');
        Schema::dropIfExists('club_accounting_cutover_authority_events');
        Schema::dropIfExists('club_accounting_cutover_authority_appointments');
        Schema::table('club_accounting_cutover_packages', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['opening_journal_entry_id', 'club_id'] : 'cutover_package_journal_club_fk');
            $table->dropForeign(['posted_by_user_id']);
            $table->dropUnique('cutover_package_opening_journal_unique');
            $table->dropForeign(['internally_verified_by_user_id']);
            $table->dropColumn(['internally_verified_by_user_id', 'internally_verified_at', 'opening_journal_entry_id', 'posted_at', 'posted_by_user_id']);
        });
    }

    private function addChecks(): void
    {
        $checks = [
            ['club_accounting_cutover_authority_appointments', 'cutover_appointment_type_chk', "NEW.authority_type IN ('independent_ca','technical_operator')"],
            ['club_accounting_cutover_authority_events', 'cutover_authority_event_type_chk', "NEW.event_type IN ('revoked','invalidated')"],
            ['club_accounting_cutover_permission_events', 'cutover_permission_event_type_chk', "NEW.event_type IN ('revoked','invalidated')"],
            ['club_accounting_cutover_decisions', 'cutover_decision_stage_chk', "NEW.decision_stage IN ('dry_run','activation')"],
            ['club_accounting_cutover_decisions', 'cutover_decision_actor_chk', "NEW.actor_authority IN ('president','independent_ca')"],
            ['club_accounting_cutover_decisions', 'cutover_decision_result_chk', "NEW.decision_result = 'accepted'"],
            ['club_accounting_cutover_decision_events', 'cutover_decision_event_type_chk', "NEW.event_type IN ('revoked','invalidated')"],
            ['club_accounting_cutover_review_events', 'cutover_review_event_type_chk', "NEW.event_type IN ('revoked','invalidated')"],
            ['club_accounting_cutover_approval_events', 'cutover_approval_event_type_chk', "NEW.event_type IN ('revoked','invalidated')"],
            ['club_accounting_cutover_execution_events', 'cutover_execution_event_type_chk', "NEW.event_type IN ('execution_attempt','execution_success','execution_failure','execution_replay','execution_conflict')"],
        ];
        foreach ($checks as [$table, $name, $expression]) {
            if (DB::getDriverName() === 'sqlite') {
                foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $operation => $suffix) {
                    DB::unprepared("CREATE TRIGGER {$name}_{$suffix} BEFORE {$operation} ON {$table} FOR EACH ROW WHEN NOT ({$expression}) BEGIN SELECT RAISE(ABORT, '{$name}'); END");
                }
            } else {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK (".str_replace('NEW.', '', $expression).')');
            }
        }
    }

    private function addImmutabilityAndLifecycleTriggers(): void
    {
        $immutableTables = [
            'club_accounting_cutover_authority_appointments', 'club_accounting_cutover_authority_events',
            'club_accounting_cutover_permission_assignments', 'club_accounting_cutover_permission_events',
            'club_accounting_cutover_review_events', 'club_accounting_cutover_approval_events',
            'club_accounting_cutover_decisions', 'club_accounting_cutover_decision_events',
            'club_accounting_cutover_execution_successes', 'club_accounting_cutover_authority_consumptions',
            'club_accounting_cutover_execution_events',
        ];

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER cutover_package_posting_metadata_guard BEFORE UPDATE ON club_accounting_cutover_packages FOR EACH ROW WHEN (NEW.opening_journal_entry_id IS NOT OLD.opening_journal_entry_id OR NEW.posted_at IS NOT OLD.posted_at OR NEW.posted_by_user_id IS NOT OLD.posted_by_user_id) AND NOT (OLD.status = 'activation_authorized' AND NEW.status = 'posted' AND NEW.opening_journal_entry_id IS NOT NULL AND NEW.posted_at IS NOT NULL AND NEW.posted_by_user_id IS NOT NULL) BEGIN SELECT RAISE(ABORT, 'cutover_package_posting_metadata_guard'); END");
            DB::unprepared("CREATE TRIGGER cutover_package_accounting_content_guard BEFORE UPDATE ON club_accounting_cutover_packages FOR EACH ROW WHEN OLD.status <> 'draft' AND (NEW.club_id IS NOT OLD.club_id OR NEW.lions_year IS NOT OLD.lions_year OR NEW.cutover_date IS NOT OLD.cutover_date OR NEW.chart_version IS NOT OLD.chart_version OR NEW.package_version IS NOT OLD.package_version OR NEW.currency IS NOT OLD.currency OR NEW.reconciliation_difference_paise IS NOT OLD.reconciliation_difference_paise OR NEW.dr033_classification IS NOT OLD.dr033_classification) AND NOT (OLD.status = 'internally_verified' AND NEW.status = 'accounting_reviewed' AND OLD.dr033_classification IS NULL AND NEW.dr033_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')) BEGIN SELECT RAISE(ABORT, 'cutover_package_accounting_content_guard'); END");
            DB::unprepared("CREATE TRIGGER cutover_package_checksum_guard BEFORE UPDATE ON club_accounting_cutover_packages FOR EACH ROW WHEN OLD.status <> 'draft' AND NEW.canonical_checksum IS NOT OLD.canonical_checksum AND NOT (OLD.status = 'internally_verified' AND NEW.status = 'accounting_reviewed' AND NEW.canonical_checksum IS NULL) AND NOT (OLD.status = 'accounting_reviewed' AND NEW.status = 'accounting_reviewed' AND OLD.canonical_checksum IS NULL AND NEW.canonical_checksum IS NOT NULL) BEGIN SELECT RAISE(ABORT, 'cutover_package_checksum_guard'); END");
            foreach (['club_accounting_cutover_schedule_rows', 'club_accounting_cutover_evidence_references'] as $table) {
                foreach (['INSERT' => 'NEW', 'UPDATE' => 'NEW', 'DELETE' => 'OLD'] as $operation => $record) {
                    DB::unprepared("CREATE TRIGGER {$table}_prepared_immutable_".strtolower($operation)." BEFORE {$operation} ON {$table} FOR EACH ROW WHEN (SELECT status FROM club_accounting_cutover_packages WHERE id = {$record}.cutover_package_id) <> 'draft' BEGIN SELECT RAISE(ABORT, '{$table}_prepared_immutable'); END");
                }
            }
            foreach (['club_accounting_cutover_reviews', 'club_accounting_cutover_approvals'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_trusted_update_guard BEFORE UPDATE ON {$table} FOR EACH ROW WHEN OLD.authority_basis IS NOT NULL BEGIN SELECT RAISE(ABORT, '{$table}_trusted_immutable'); END");
                DB::unprepared("CREATE TRIGGER {$table}_trusted_delete_guard BEFORE DELETE ON {$table} FOR EACH ROW WHEN OLD.authority_basis IS NOT NULL BEGIN SELECT RAISE(ABORT, '{$table}_trusted_immutable'); END");
            }
            foreach ($immutableTables as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, '{$table}_immutable'); END");
                DB::unprepared("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, '{$table}_immutable'); END");
            }
            DB::unprepared("CREATE TRIGGER cutover_one_active_appointment_guard BEFORE INSERT ON club_accounting_cutover_authority_appointments FOR EACH ROW WHEN EXISTS (SELECT 1 FROM club_accounting_cutover_authority_appointments a WHERE a.cutover_package_id = NEW.cutover_package_id AND a.authority_type = NEW.authority_type AND (a.expires_at IS NULL OR a.expires_at > CURRENT_TIMESTAMP) AND NOT EXISTS (SELECT 1 FROM club_accounting_cutover_authority_events e WHERE e.authority_appointment_id = a.id AND e.event_type IN ('revoked','invalidated'))) BEGIN SELECT RAISE(ABORT, 'cutover_one_active_appointment_guard'); END");

            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION cutover_reject_mutation() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'cutover immutable record'; END; \$\$");
            DB::unprepared("CREATE OR REPLACE FUNCTION cutover_guard_posting_metadata() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF (NEW.opening_journal_entry_id, NEW.posted_at, NEW.posted_by_user_id) IS DISTINCT FROM (OLD.opening_journal_entry_id, OLD.posted_at, OLD.posted_by_user_id) AND NOT (OLD.status = 'activation_authorized' AND NEW.status = 'posted' AND NEW.opening_journal_entry_id IS NOT NULL AND NEW.posted_at IS NOT NULL AND NEW.posted_by_user_id IS NOT NULL) THEN RAISE EXCEPTION 'invalid posting metadata transition'; END IF; RETURN NEW; END; \$\$");
            DB::unprepared('CREATE TRIGGER cutover_package_posting_metadata_guard BEFORE UPDATE ON club_accounting_cutover_packages FOR EACH ROW EXECUTE FUNCTION cutover_guard_posting_metadata()');
            DB::unprepared("CREATE OR REPLACE FUNCTION cutover_guard_accounting_content() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF OLD.status <> 'draft' AND (NEW.club_id, NEW.lions_year, NEW.cutover_date, NEW.chart_version, NEW.package_version, NEW.currency, NEW.reconciliation_difference_paise, NEW.dr033_classification) IS DISTINCT FROM (OLD.club_id, OLD.lions_year, OLD.cutover_date, OLD.chart_version, OLD.package_version, OLD.currency, OLD.reconciliation_difference_paise, OLD.dr033_classification) AND NOT (OLD.status = 'internally_verified' AND NEW.status = 'accounting_reviewed' AND OLD.dr033_classification IS NULL AND NEW.dr033_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')) THEN RAISE EXCEPTION 'prepared cutover accounting content is immutable'; END IF; IF OLD.status <> 'draft' AND NEW.canonical_checksum IS DISTINCT FROM OLD.canonical_checksum AND NOT (OLD.status = 'internally_verified' AND NEW.status = 'accounting_reviewed' AND NEW.canonical_checksum IS NULL) AND NOT (OLD.status = 'accounting_reviewed' AND NEW.status = 'accounting_reviewed' AND OLD.canonical_checksum IS NULL AND NEW.canonical_checksum IS NOT NULL) THEN RAISE EXCEPTION 'cutover checksum is immutable'; END IF; RETURN NEW; END; \$\$");
            DB::unprepared('CREATE TRIGGER cutover_package_accounting_content_guard BEFORE UPDATE ON club_accounting_cutover_packages FOR EACH ROW EXECUTE FUNCTION cutover_guard_accounting_content()');
            DB::unprepared("CREATE OR REPLACE FUNCTION cutover_guard_child_accounting_content() RETURNS trigger LANGUAGE plpgsql AS \$\$ DECLARE package_id bigint; BEGIN package_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.cutover_package_id ELSE NEW.cutover_package_id END; IF (SELECT status FROM club_accounting_cutover_packages WHERE id = package_id) <> 'draft' THEN RAISE EXCEPTION 'prepared cutover accounting content is immutable'; END IF; RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END; END; \$\$");
            foreach (['club_accounting_cutover_schedule_rows', 'club_accounting_cutover_evidence_references'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_prepared_immutable BEFORE INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION cutover_guard_child_accounting_content()");
            }
            foreach ($immutableTables as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION cutover_reject_mutation()");
            }
            foreach (['club_accounting_cutover_reviews', 'club_accounting_cutover_approvals'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_trusted_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW WHEN (OLD.authority_basis IS NOT NULL) EXECUTE FUNCTION cutover_reject_mutation()");
            }
            DB::unprepared("CREATE OR REPLACE FUNCTION cutover_guard_active_appointment() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF EXISTS (SELECT 1 FROM club_accounting_cutover_authority_appointments a WHERE a.cutover_package_id = NEW.cutover_package_id AND a.authority_type = NEW.authority_type AND (a.expires_at IS NULL OR a.expires_at > CURRENT_TIMESTAMP) AND NOT EXISTS (SELECT 1 FROM club_accounting_cutover_authority_events e WHERE e.authority_appointment_id = a.id AND e.event_type IN ('revoked','invalidated'))) THEN RAISE EXCEPTION 'one current package appointment is permitted per authority type'; END IF; RETURN NEW; END; \$\$");
            DB::unprepared('CREATE TRIGGER cutover_one_active_appointment_guard BEFORE INSERT ON club_accounting_cutover_authority_appointments FOR EACH ROW EXECUTE FUNCTION cutover_guard_active_appointment()');
        }
    }

    private function restoreAlteredTableChecksForSqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        $checks = [
            ['club_accounting_cutover_packages', 'cutover_pkg_version_chk', 'NEW.package_version >= 1'],
            ['club_accounting_cutover_packages', 'cutover_pkg_lock_chk', 'NEW.lock_version >= 1'],
            ['club_accounting_cutover_packages', 'cutover_pkg_currency_chk', "NEW.currency = 'INR'"],
            ['club_accounting_cutover_packages', 'cutover_pkg_identity_chk', "NEW.cutover_date = '2027-07-01' AND NEW.lions_year = '2027-28' AND NEW.chart_version = 'CLUB-COA-2026-01'"],
            ['club_accounting_cutover_packages', 'cutover_pkg_status_chk', "NEW.status IN ('draft','prepared','internally_verified','accounting_reviewed','business_approved','dry_run_accepted','activation_authorized','posted','rejected','superseded','reversed')"],
            ['club_accounting_cutover_packages', 'cutover_pkg_dr033_chk', "NEW.dr033_classification IS NULL OR NEW.dr033_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')"],
            ['club_accounting_cutover_reviews', 'cutover_review_class_chk', "NEW.selected_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')"],
            ['club_accounting_cutover_reviews', 'cutover_review_status_chk', "NEW.status IN ('current','invalidated')"],
            ['club_accounting_cutover_approvals', 'cutover_approval_status_chk', "NEW.status IN ('current','invalidated')"],
        ];
        foreach ($checks as [$table, $name, $expression]) {
            foreach (['insert', 'update'] as $suffix) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$name}_{$suffix}");
            }
            foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $operation => $suffix) {
                DB::unprepared("CREATE TRIGGER {$name}_{$suffix} BEFORE {$operation} ON {$table} FOR EACH ROW WHEN NOT ({$expression}) BEGIN SELECT RAISE(ABORT, '{$name}'); END");
            }
        }
    }
};
