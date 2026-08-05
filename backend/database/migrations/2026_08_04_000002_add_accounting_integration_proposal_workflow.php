<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $current = DB::table('club_accounting_integration_activations as a')
            ->leftJoin('club_accounting_integration_activation_events as e', 'e.integration_activation_id', '=', 'a.id')
            ->whereNull('e.id')->get(['a.id', 'a.club_id', 'a.source_type']);
        if ($current->groupBy(fn ($row) => $row->club_id.'|'.$row->source_type)->contains(fn ($rows) => $rows->count() !== 1)) {
            throw new RuntimeException('Existing accounting activations do not have one representable current state.');
        }

        Schema::create('club_accounting_integration_current_activations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('integration_activation_id');
            $table->timestamps();
            $table->unique(['club_id', 'source_type'], 'accounting_current_activation_club_source_unique');
            $table->unique('integration_activation_id', 'accounting_current_activation_identity_unique');
            $table->foreign(['integration_activation_id', 'club_id', 'source_type'], 'accounting_current_activation_binding_fk')
                ->references(['id', 'club_id', 'source_type'])->on('club_accounting_integration_activations')->restrictOnDelete();
        });

        foreach ($current as $activation) {
            DB::table('club_accounting_integration_current_activations')->insert([
                'club_id' => $activation->club_id,
                'source_type' => $activation->source_type,
                'integration_activation_id' => $activation->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER accounting_current_activation_insert_guard BEFORE INSERT ON club_accounting_integration_current_activations FOR EACH ROW WHEN EXISTS (SELECT 1 FROM club_accounting_integration_activation_events e WHERE e.integration_activation_id = NEW.integration_activation_id AND e.event_type IN ('revoked','invalidated')) BEGIN SELECT RAISE(ABORT, 'terminal_activation_cannot_be_current'); END");
            DB::unprepared("CREATE TRIGGER accounting_current_activation_update_guard BEFORE UPDATE ON club_accounting_integration_current_activations FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'accounting_current_activation_immutable'); END");
            DB::unprepared("CREATE TRIGGER accounting_current_activation_delete_guard BEFORE DELETE ON club_accounting_integration_current_activations FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM club_accounting_integration_activation_events e WHERE e.integration_activation_id = OLD.integration_activation_id AND e.event_type IN ('revoked','invalidated')) BEGIN SELECT RAISE(ABORT, 'current_activation_requires_terminal_event'); END");
            DB::unprepared("CREATE TRIGGER accounting_activation_terminal_clears_current AFTER INSERT ON club_accounting_integration_activation_events FOR EACH ROW WHEN NEW.event_type IN ('revoked','invalidated') BEGIN DELETE FROM club_accounting_integration_current_activations WHERE integration_activation_id = NEW.integration_activation_id AND club_id = NEW.club_id; END");
        }

        Schema::table('club_accounting_integration_activations', function (Blueprint $table): void {
            $table->dropUnique('accounting_integration_activation_unique');
            $table->index(['club_id', 'source_type'], 'accounting_integration_activation_lookup');
        });

        Schema::create('club_accounting_integration_proposals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('proposal_type', 40);
            $table->string('source_type', 30);
            $table->string('source_key', 80)->nullable();
            $table->unsignedBigInteger('ledger_account_id')->nullable();
            $table->string('mapping_version', 40)->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expires_at')->nullable();
            $table->date('coverage_start_date')->nullable();
            $table->unsignedBigInteger('activation_id')->nullable();
            $table->unsignedBigInteger('mapping_id')->nullable();
            $table->unsignedBigInteger('terminal_event_id')->nullable();
            $table->json('target_snapshot')->nullable();
            $table->foreignId('proposer_user_id')->constrained('users')->restrictOnDelete();
            $table->string('proposer_role_slug', 40);
            $table->uuid('client_request_id');
            $table->char('canonical_checksum', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('reference', 120);
            $table->text('reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'client_request_id'], 'accounting_proposal_request_unique');
            $table->unique(['id', 'club_id'], 'accounting_proposal_club_unique');
            $table->index(['club_id', 'status', 'source_type'], 'accounting_proposal_queue_idx');
            $table->foreign(['ledger_account_id', 'club_id'], 'accounting_proposal_account_club_fk')->references(['id', 'club_id'])->on('ledger_accounts')->restrictOnDelete();
            $table->foreign(['activation_id', 'club_id'], 'accounting_proposal_activation_club_fk')->references(['id', 'club_id'])->on('club_accounting_integration_activations')->restrictOnDelete();
            $table->foreign(['mapping_id', 'club_id'], 'accounting_proposal_mapping_club_fk')->references(['id', 'club_id'])->on('club_accounting_source_mappings')->restrictOnDelete();
        });

        Schema::create('club_accounting_integration_proposal_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('proposal_id');
            $table->string('decision', 20);
            $table->text('reason')->nullable();
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('decider_role_slug', 40);
            $table->char('proposal_checksum', 64);
            $table->string('result_type', 80)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->json('before_state');
            $table->json('after_state');
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique('proposal_id', 'accounting_proposal_terminal_decision_unique');
            $table->unique(['id', 'club_id'], 'accounting_proposal_decision_club_unique');
            $table->foreign(['proposal_id', 'club_id'], 'accounting_proposal_decision_proposal_fk')->references(['id', 'club_id'])->on('club_accounting_integration_proposals')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION accounting_current_activation_insert_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF EXISTS (SELECT 1 FROM club_accounting_integration_activation_events e WHERE e.integration_activation_id = NEW.integration_activation_id AND e.event_type IN ('revoked','invalidated')) THEN RAISE EXCEPTION 'terminal_activation_cannot_be_current'; END IF; RETURN NEW; END; \$\$");
            DB::unprepared("CREATE TRIGGER accounting_current_activation_insert_guard BEFORE INSERT ON club_accounting_integration_current_activations FOR EACH ROW EXECUTE FUNCTION accounting_current_activation_insert_guard()");
            DB::unprepared("CREATE OR REPLACE FUNCTION accounting_current_activation_update_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'accounting_current_activation_immutable'; END; \$\$");
            DB::unprepared("CREATE TRIGGER accounting_current_activation_update_guard BEFORE UPDATE ON club_accounting_integration_current_activations FOR EACH ROW EXECUTE FUNCTION accounting_current_activation_update_guard()");
            DB::unprepared("CREATE OR REPLACE FUNCTION accounting_current_activation_delete_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NOT EXISTS (SELECT 1 FROM club_accounting_integration_activation_events e WHERE e.integration_activation_id = OLD.integration_activation_id AND e.event_type IN ('revoked','invalidated')) THEN RAISE EXCEPTION 'current_activation_requires_terminal_event'; END IF; RETURN OLD; END; \$\$");
            DB::unprepared("CREATE TRIGGER accounting_current_activation_delete_guard BEFORE DELETE ON club_accounting_integration_current_activations FOR EACH ROW EXECUTE FUNCTION accounting_current_activation_delete_guard()");
            DB::unprepared("CREATE OR REPLACE FUNCTION accounting_activation_terminal_clears_current() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.event_type IN ('revoked','invalidated') THEN DELETE FROM club_accounting_integration_current_activations WHERE integration_activation_id = NEW.integration_activation_id AND club_id = NEW.club_id; END IF; RETURN NEW; END; \$\$");
            DB::unprepared("CREATE TRIGGER accounting_activation_terminal_clears_current AFTER INSERT ON club_accounting_integration_activation_events FOR EACH ROW EXECUTE FUNCTION accounting_activation_terminal_clears_current()");
            DB::statement("ALTER TABLE club_accounting_integration_current_activations ADD CONSTRAINT accounting_current_activation_source_check CHECK (source_type IN ('member_charge','member_payment'))");
            DB::statement("ALTER TABLE club_accounting_integration_proposals ADD CONSTRAINT accounting_proposal_type_check CHECK (proposal_type IN ('mapping_create','mapping_replace','charge_activation','payment_activation','mapping_revocation','activation_revocation','reactivation','coverage_start_date_change','mapping_version_change'))");
            DB::statement("ALTER TABLE club_accounting_integration_proposals ADD CONSTRAINT accounting_proposal_status_check CHECK (status IN ('pending','approved','rejected','withdrawn'))");
            DB::statement("ALTER TABLE club_accounting_integration_proposals ADD CONSTRAINT accounting_proposal_source_check CHECK (source_type IN ('member_charge','member_payment'))");
            DB::statement("ALTER TABLE club_accounting_integration_proposal_decisions ADD CONSTRAINT accounting_proposal_decision_check CHECK (decision IN ('approved','rejected','withdrawn'))");
        }
    }

    public function down(): void
    {
        $reactivated = DB::table('club_accounting_integration_activations')
            ->select('club_id', 'source_type')
            ->groupBy('club_id', 'source_type')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($reactivated) {
            throw new RuntimeException('Cannot roll back the proposal workflow after a source has historical reactivation records.');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS accounting_current_activation_insert_guard() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS accounting_current_activation_update_guard() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS accounting_activation_terminal_clears_current() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS accounting_current_activation_delete_guard() CASCADE');
        }
        Schema::dropIfExists('club_accounting_integration_proposal_decisions');
        Schema::dropIfExists('club_accounting_integration_proposals');
        Schema::dropIfExists('club_accounting_integration_current_activations');
        Schema::table('club_accounting_integration_activations', function (Blueprint $table): void {
            $table->dropIndex('accounting_integration_activation_lookup');
            $table->unique(['club_id', 'source_type'], 'accounting_integration_activation_unique');
        });
    }
};
