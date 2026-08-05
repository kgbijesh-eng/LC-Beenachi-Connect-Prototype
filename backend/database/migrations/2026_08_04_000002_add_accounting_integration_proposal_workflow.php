<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        Schema::dropIfExists('club_accounting_integration_proposal_decisions');
        Schema::dropIfExists('club_accounting_integration_proposals');
        Schema::table('club_accounting_integration_activations', function (Blueprint $table): void {
            $table->dropIndex('accounting_integration_activation_lookup');
            $table->unique(['club_id', 'source_type'], 'accounting_integration_activation_unique');
        });
    }
};
