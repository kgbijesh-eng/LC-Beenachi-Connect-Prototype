<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Club;
use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingIntegrationProposal;
use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubMembership;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Finance\AccountingIntegrationConfigurationService as Configuration;
use App\Services\Finance\AccountingIntegrationProposalService as Proposals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Committed synthetic prerequisites; never opens an ambient wrapper. */
final class IntegrationConfigurationFixture
{
    public static function actions(): array
    {
        return ['map', 'activate', 'revokeMapping', 'revokeActivation', 'propose', 'propose-replay', 'approve', 'reject', 'withdraw'];
    }

    public static function prepare(string $action, string $suffix = 'B4'): array
    {
        $p = (new FinancialOperationFixture)->prepare('post', $suffix);
        $p['action'] = $action;
        $permission = Permission::firstOrCreate(['slug' => Proposals::PERMISSION], ['name' => 'Configure integration', 'group' => 'finance']);
        foreach (['treasurer' => $p['actor'], 'president-admin' => ($p['decider'] = User::factory()->create()->id)] as $slug => $id) {
            $role = Role::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
            ClubMembership::updateOrCreate(['club_id' => $p['club'], 'user_id' => $id], ['role_id' => $role->id, 'status' => 'active']);
        }
        $p['reference'] = 'B4-'.Str::uuid();
        $p['proposal_input'] = ['client_request_id' => (string) Str::uuid(), 'proposal_type' => 'mapping_create', 'source_type' => 'member_charge', 'source_key' => 'membership_dues', 'ledger_account_id' => $p['income'], 'mapping_version' => 'B4-V1', 'effective_date' => '2027-07-01', 'reference' => $p['reference']];
        $config = app(Configuration::class);
        if ($action === 'revokeMapping') {
            $p['mapping'] = $config->map($p['club'], 'member_charge', 'membership_dues', LedgerAccount::findOrFail($p['income']), 'B4-V1', '2027-07-01', 'INITIAL-MAP', User::findOrFail($p['actor']))->id;
        }
        if ($action === 'revokeActivation') {
            $p['activation'] = $config->activate($p['club'], 'member_charge', '2027-07-01', 'B4-V1', 'INITIAL-ACT', User::findOrFail($p['actor']))->id;
        }
        if (in_array($action, ['propose-replay', 'approve', 'reject', 'withdraw'], true)) {
            $p['proposal'] = app(Proposals::class)->propose(Club::findOrFail($p['club']), User::findOrFail($p['actor']), $p['proposal_input'])->id;
        }

        return $p;
    }

    public static function perform(array $p, ?OrganisationTransactionContext $context = null): mixed
    {
        $config = app(Configuration::class);
        $actor = User::findOrFail($p['actor']);
        $args = match ($p['action']) {
            'map' => [$p['club'], 'member_charge', 'membership_dues', LedgerAccount::findOrFail($p['income']), 'B4-V1', '2027-07-01', $p['reference'], $actor],
            'activate' => [$p['club'], 'member_charge', '2027-07-01', $p['mapping_version'] ?? 'B4-V1', $p['reference'], $actor],
            'revokeMapping' => [ClubAccountingSourceMapping::findOrFail($p['mapping']), $actor, $p['reference'], 'B4 synthetic revocation'],
            'revokeActivation' => [ClubAccountingIntegrationActivation::findOrFail($p['activation']), $actor, $p['reference'], 'B4 synthetic revocation'],
            default => null,
        };
        if ($args !== null) {
            return $context ? $config->{$p['action'].'Within'}($context, ...$args) : $config->{$p['action']}(...$args);
        }
        $proposals = app(Proposals::class);
        $club = Club::findOrFail($p['club']);
        if (in_array($p['action'], ['propose', 'propose-replay'], true)) {
            return $proposals->propose($club, $actor, $p['proposal_input']);
        }

        return $proposals->{$p['action']}($club, $p['action'] === 'withdraw' ? $actor : User::findOrFail($p['decider']), ClubAccountingIntegrationProposal::findOrFail($p['proposal']), 0, 'B4 decision');
    }

    public static function snapshot(int $club): array
    {
        $snapshot = FinancialOperationFixture::snapshot($club);
        foreach (['club_accounting_source_mappings', 'club_accounting_source_mapping_events', 'club_accounting_integration_activations', 'club_accounting_integration_activation_events', 'club_accounting_integration_current_activations', 'club_accounting_integration_proposals', 'club_accounting_integration_proposal_decisions'] as $table) {
            $snapshot[$table] = DB::table($table)->where('club_id', $club)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }

        return $snapshot;
    }
}
