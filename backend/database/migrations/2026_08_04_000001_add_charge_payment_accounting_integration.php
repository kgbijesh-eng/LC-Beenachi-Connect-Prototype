<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_charges', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable();
        });
        Schema::table('member_payments', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable();
        });
        $this->backfillSourcePublicIds('club_charges');
        $this->backfillSourcePublicIds('member_payments');
        Schema::table('club_charges', fn (Blueprint $table) => $table->unique('public_id', 'club_charges_public_id_unique'));
        Schema::table('member_payments', fn (Blueprint $table) => $table->unique('public_id', 'member_payments_public_id_unique'));
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE club_charges ALTER COLUMN public_id SET NOT NULL');
            DB::statement('ALTER TABLE member_payments ALTER COLUMN public_id SET NOT NULL');
        }

        Schema::create('club_accounting_integration_activations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('source_type', 30);
            $table->date('coverage_start_date');
            $table->string('mapping_version', 40);
            $table->unsignedBigInteger('opening_package_id');
            $table->unsignedBigInteger('opening_journal_entry_id');
            $table->string('activation_reference', 120)->unique();
            $table->foreignId('activated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at');
            $table->timestamps();
            $table->unique(['club_id', 'source_type'], 'accounting_integration_activation_unique');
            $table->unique(['id', 'club_id']);
            $table->unique(['id', 'club_id', 'source_type'], 'integration_activation_club_source_unique');
            $table->foreign(['opening_package_id', 'club_id'], 'integration_activation_package_club_fk')->references(['id', 'club_id'])->on('club_accounting_cutover_packages')->restrictOnDelete();
            $table->foreign(['opening_journal_entry_id', 'club_id'], 'integration_activation_journal_club_fk')->references(['id', 'club_id'])->on('club_journal_entries')->restrictOnDelete();
        });

        Schema::create('club_accounting_integration_activation_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('integration_activation_id');
            $table->string('event_type', 20);
            $table->string('event_reference', 120)->unique();
            $table->text('reason');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique('integration_activation_id', 'integration_activation_terminal_event_unique');
            $table->unique(['id', 'club_id']);
            $table->unique(['id', 'integration_activation_id', 'club_id'], 'activation_event_activation_club_unique');
            $table->foreign(['integration_activation_id', 'club_id'], 'integration_activation_event_club_fk')->references(['id', 'club_id'])->on('club_accounting_integration_activations')->restrictOnDelete();
        });

        Schema::create('club_accounting_source_coverage_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('source_type', 30);
            $table->uuid('source_identifier');
            $table->date('accounting_date');
            $table->boolean('integration_required');
            $table->unsignedBigInteger('integration_activation_id')->nullable();
            $table->unsignedBigInteger('terminal_activation_event_id')->nullable();
            $table->string('decision_reason', 30);
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(['club_id', 'source_type', 'source_identifier'], 'source_coverage_decision_unique');
            $table->unique(['id', 'club_id']);
            $table->foreign(['integration_activation_id', 'club_id', 'source_type'], 'coverage_decision_activation_source_fk')->references(['id', 'club_id', 'source_type'])->on('club_accounting_integration_activations')->restrictOnDelete();
            $table->foreign(['terminal_activation_event_id', 'integration_activation_id', 'club_id'], 'coverage_decision_terminal_activation_fk')->references(['id', 'integration_activation_id', 'club_id'])->on('club_accounting_integration_activation_events')->restrictOnDelete();
        });

        Schema::create('club_accounting_source_mappings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('source_type', 30);
            $table->string('canonical_source_key', 80);
            $table->unsignedBigInteger('ledger_account_id');
            $table->string('mapping_version', 40);
            $table->date('effective_date');
            $table->date('expires_at')->nullable();
            $table->string('approval_reference', 120)->unique();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(['id', 'club_id']);
            $table->index(['club_id', 'source_type', 'canonical_source_key', 'mapping_version'], 'accounting_source_mapping_resolve_idx');
            $table->foreign(['ledger_account_id', 'club_id'], 'accounting_source_mapping_account_club_fk')->references(['id', 'club_id'])->on('ledger_accounts')->restrictOnDelete();
        });

        Schema::create('club_accounting_source_mapping_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('source_mapping_id');
            $table->string('event_type', 20);
            $table->string('event_reference', 120)->unique();
            $table->text('reason');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique('source_mapping_id', 'source_mapping_terminal_event_unique');
            $table->foreign(['source_mapping_id', 'club_id'], 'source_mapping_event_club_fk')->references(['id', 'club_id'])->on('club_accounting_source_mappings')->restrictOnDelete();
        });

        Schema::create('club_accounting_source_integrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('source_type', 30);
            $table->uuid('source_identifier');
            $table->unsignedBigInteger('integration_activation_id');
            $table->unsignedBigInteger('source_mapping_id');
            $table->string('canonical_source_key', 80);
            $table->unsignedBigInteger('source_amount_paise');
            $table->json('source_binding');
            $table->unsignedInteger('source_version');
            $table->date('accounting_date');
            $table->string('mapping_version', 40);
            $table->unsignedBigInteger('journal_entry_id');
            $table->string('journal_reference', 80);
            $table->uuid('source_request_idempotency_key')->nullable();
            $table->uuid('journal_idempotency_key');
            $table->char('integration_checksum', 64);
            $table->foreignId('committed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('committed_at');
            $table->timestamps();
            $table->unique(['club_id', 'source_type', 'source_identifier'], 'accounting_source_integration_source_unique');
            $table->unique(['journal_entry_id'], 'accounting_source_integration_journal_unique');
            $table->unique(['club_id', 'journal_idempotency_key'], 'accounting_source_integration_journal_key_unique');
            $table->unique(['id', 'club_id']);
            $table->foreign(['journal_entry_id', 'club_id'], 'accounting_source_integration_journal_club_fk')->references(['id', 'club_id'])->on('club_journal_entries')->restrictOnDelete();
            $table->foreign(['integration_activation_id', 'club_id'], 'source_integration_activation_club_fk')->references(['id', 'club_id'])->on('club_accounting_integration_activations')->restrictOnDelete();
            $table->foreign(['source_mapping_id', 'club_id'], 'source_integration_mapping_club_fk')->references(['id', 'club_id'])->on('club_accounting_source_mappings')->restrictOnDelete();
        });

        Schema::create('club_accounting_source_integration_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('source_integration_id');
            $table->string('event_type', 30);
            $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
            $table->string('reversal_reference', 80)->nullable();
            $table->date('reversal_effective_date')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->json('payload');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['source_integration_id', 'event_type'], 'source_integration_stage_event_unique');
            $table->foreign(['source_integration_id', 'club_id'], 'source_integration_event_club_fk')->references(['id', 'club_id'])->on('club_accounting_source_integrations')->restrictOnDelete();
            $table->foreign(['reversal_journal_entry_id', 'club_id'], 'source_integration_event_reversal_club_fk')->references(['id', 'club_id'])->on('club_journal_entries')->restrictOnDelete();
        });

        $this->addGuards();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (['club_charges', 'member_payments'] as $table) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_public_id_insert");
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_public_id_update");
            }
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS accounting_integration_reject_mutation() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS accounting_source_public_id_guard() CASCADE');
        }
        Schema::dropIfExists('club_accounting_source_integration_events');
        Schema::dropIfExists('club_accounting_source_integrations');
        Schema::dropIfExists('club_accounting_source_mapping_events');
        Schema::dropIfExists('club_accounting_source_mappings');
        Schema::dropIfExists('club_accounting_source_coverage_decisions');
        Schema::dropIfExists('club_accounting_integration_activation_events');
        Schema::dropIfExists('club_accounting_integration_activations');
        Schema::table('member_payments', function (Blueprint $table): void {
            $table->dropUnique('member_payments_public_id_unique');
            $table->dropColumn('public_id');
        });
        Schema::table('club_charges', function (Blueprint $table): void {
            $table->dropUnique('club_charges_public_id_unique');
            $table->dropColumn('public_id');
        });
    }

    private function addGuards(): void
    {
        $immutable = ['club_accounting_integration_activations', 'club_accounting_integration_activation_events', 'club_accounting_source_coverage_decisions', 'club_accounting_source_mappings', 'club_accounting_source_mapping_events', 'club_accounting_source_integrations', 'club_accounting_source_integration_events'];
        if (DB::getDriverName() === 'sqlite') {
            foreach ($immutable as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, '{$table}_immutable'); END");
                DB::unprepared("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, '{$table}_immutable'); END");
            }
            foreach ([['club_accounting_integration_activations', 'source_type'], ['club_accounting_source_mappings', 'source_type'], ['club_accounting_source_integrations', 'source_type']] as [$table, $column]) {
                DB::unprepared("CREATE TRIGGER {$table}_source_check_insert BEFORE INSERT ON {$table} FOR EACH ROW WHEN NEW.{$column} NOT IN ('member_charge','member_payment') BEGIN SELECT RAISE(ABORT, '{$table}_source_check'); END");
            }
            DB::unprepared("CREATE TRIGGER source_coverage_decision_check BEFORE INSERT ON club_accounting_source_coverage_decisions FOR EACH ROW WHEN NEW.source_type NOT IN ('member_charge','member_payment') OR NOT ((NEW.decision_reason = 'not_activated' AND NEW.integration_required = 0 AND NEW.integration_activation_id IS NULL AND NEW.terminal_activation_event_id IS NULL) OR (NEW.decision_reason = 'before_coverage' AND NEW.integration_required = 0 AND NEW.integration_activation_id IS NOT NULL AND NEW.terminal_activation_event_id IS NULL) OR (NEW.decision_reason = 'integration_required' AND NEW.integration_required = 1 AND NEW.integration_activation_id IS NOT NULL AND NEW.terminal_activation_event_id IS NULL) OR (NEW.decision_reason = 'after_terminal_event' AND NEW.integration_required = 0 AND NEW.integration_activation_id IS NOT NULL AND NEW.terminal_activation_event_id IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'source_coverage_decision_check'); END");
            DB::unprepared("CREATE TRIGGER integration_activation_event_type_check BEFORE INSERT ON club_accounting_integration_activation_events FOR EACH ROW WHEN NEW.event_type NOT IN ('revoked','invalidated') BEGIN SELECT RAISE(ABORT, 'integration_activation_event_type_check'); END");
            DB::unprepared("CREATE TRIGGER integration_mapping_event_type_check BEFORE INSERT ON club_accounting_source_mapping_events FOR EACH ROW WHEN NEW.event_type NOT IN ('revoked','invalidated') BEGIN SELECT RAISE(ABORT, 'integration_mapping_event_type_check'); END");
            DB::unprepared("CREATE TRIGGER source_integration_event_type_check BEFORE INSERT ON club_accounting_source_integration_events FOR EACH ROW WHEN NEW.event_type NOT IN ('integration_success','integration_reversal') BEGIN SELECT RAISE(ABORT, 'source_integration_event_type_check'); END");
            DB::unprepared("CREATE TRIGGER source_integration_value_check BEFORE INSERT ON club_accounting_source_integrations FOR EACH ROW WHEN NEW.source_amount_paise < 1 OR length(NEW.integration_checksum) <> 64 OR NEW.integration_checksum GLOB '*[^0-9a-f]*' BEGIN SELECT RAISE(ABORT, 'source_integration_value_check'); END");
            DB::unprepared("CREATE TRIGGER source_mapping_interval_check BEFORE INSERT ON club_accounting_source_mappings FOR EACH ROW WHEN NEW.expires_at IS NOT NULL AND NEW.expires_at < NEW.effective_date BEGIN SELECT RAISE(ABORT, 'source_mapping_interval_check'); END");
            foreach (['club_charges', 'member_payments'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_public_id_insert BEFORE INSERT ON {$table} FOR EACH ROW WHEN NEW.public_id IS NULL OR length(NEW.public_id) <> 36 OR substr(NEW.public_id,9,1) <> '-' OR substr(NEW.public_id,14,1) <> '-' OR substr(NEW.public_id,19,1) <> '-' OR substr(NEW.public_id,24,1) <> '-' OR substr(NEW.public_id,1,8) GLOB '*[^0-9a-f]*' OR substr(NEW.public_id,10,4) GLOB '*[^0-9a-f]*' OR substr(NEW.public_id,15,4) GLOB '*[^0-9a-f]*' OR substr(NEW.public_id,20,4) GLOB '*[^0-9a-f]*' OR substr(NEW.public_id,25,12) GLOB '*[^0-9a-f]*' BEGIN SELECT RAISE(ABORT, '{$table}_public_id_required'); END");
                DB::unprepared("CREATE TRIGGER {$table}_public_id_update BEFORE UPDATE OF public_id ON {$table} FOR EACH ROW WHEN NEW.public_id IS NULL OR NEW.public_id IS NOT OLD.public_id BEGIN SELECT RAISE(ABORT, '{$table}_public_id_immutable'); END");
            }

            return;
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION accounting_integration_reject_mutation() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'accounting integration records are immutable'; END; \$\$");
            foreach ($immutable as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION accounting_integration_reject_mutation()");
            }
            DB::statement("ALTER TABLE club_accounting_integration_activations ADD CONSTRAINT integration_activation_source_check CHECK (source_type IN ('member_charge','member_payment'))");
            DB::statement("ALTER TABLE club_accounting_source_mappings ADD CONSTRAINT integration_mapping_source_check CHECK (source_type IN ('member_charge','member_payment'))");
            DB::statement("ALTER TABLE club_accounting_source_integrations ADD CONSTRAINT source_integration_source_check CHECK (source_type IN ('member_charge','member_payment'))");
            DB::statement("ALTER TABLE club_accounting_source_coverage_decisions ADD CONSTRAINT coverage_decision_source_check CHECK (source_type IN ('member_charge','member_payment'))");
            DB::statement("ALTER TABLE club_accounting_source_coverage_decisions ADD CONSTRAINT coverage_decision_reason_check CHECK (decision_reason IN ('not_activated','before_coverage','integration_required','after_terminal_event'))");
            DB::statement("ALTER TABLE club_accounting_source_coverage_decisions ADD CONSTRAINT coverage_decision_binding_check CHECK ((decision_reason = 'not_activated' AND NOT integration_required AND integration_activation_id IS NULL AND terminal_activation_event_id IS NULL) OR (decision_reason = 'before_coverage' AND NOT integration_required AND integration_activation_id IS NOT NULL AND terminal_activation_event_id IS NULL) OR (decision_reason = 'integration_required' AND integration_required AND integration_activation_id IS NOT NULL AND terminal_activation_event_id IS NULL) OR (decision_reason = 'after_terminal_event' AND NOT integration_required AND integration_activation_id IS NOT NULL AND terminal_activation_event_id IS NOT NULL))");
            DB::statement("ALTER TABLE club_accounting_integration_activation_events ADD CONSTRAINT activation_event_type_check CHECK (event_type IN ('revoked','invalidated'))");
            DB::statement("ALTER TABLE club_accounting_source_mapping_events ADD CONSTRAINT mapping_event_type_check CHECK (event_type IN ('revoked','invalidated'))");
            DB::statement("ALTER TABLE club_accounting_source_integration_events ADD CONSTRAINT integration_event_type_check CHECK (event_type IN ('integration_success','integration_reversal'))");
            DB::statement('ALTER TABLE club_accounting_source_integrations ADD CONSTRAINT source_integration_amount_check CHECK (source_amount_paise > 0)');
            DB::statement("ALTER TABLE club_accounting_source_integrations ADD CONSTRAINT source_integration_checksum_check CHECK (integration_checksum ~ '^[0-9a-f]{64}$')");
            DB::statement('ALTER TABLE club_accounting_source_mappings ADD CONSTRAINT source_mapping_interval_check CHECK (expires_at IS NULL OR expires_at >= effective_date)');
            DB::unprepared("CREATE OR REPLACE FUNCTION accounting_source_public_id_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.public_id IS NULL OR NEW.public_id IS DISTINCT FROM OLD.public_id THEN RAISE EXCEPTION 'source public identity is required and immutable'; END IF; RETURN NEW; END; \$\$");
            foreach (['club_charges', 'member_payments'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_public_id_guard BEFORE UPDATE OF public_id ON {$table} FOR EACH ROW EXECUTE FUNCTION accounting_source_public_id_guard()");
            }
        }
    }

    private function backfillSourcePublicIds(string $table): void
    {
        DB::table($table)->orderBy('id')->get(['id', 'club_id'])->each(function ($row) use ($table): void {
            $hash = hash('sha256', "3iizh|{$table}|{$row->club_id}|{$row->id}");
            $uuid = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-8'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
            DB::table($table)->where('id', $row->id)->update(['public_id' => $uuid]);
        });
    }
};
