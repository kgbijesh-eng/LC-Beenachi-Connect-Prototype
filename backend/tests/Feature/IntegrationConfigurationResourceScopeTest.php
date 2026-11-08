<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingIntegrationProposal;
use App\Models\ClubAccountingSourceMapping;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\AccountingIntegrationConfigurationService as Configuration;
use App\Services\Finance\AccountingIntegrationProposalService as Proposals;
use App\Services\Finance\Exceptions\AccountingIntegrationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FinancialPostgresTestCase;
use Tests\Support\IntegrationConfigurationFixture as Fixture;

class IntegrationConfigurationResourceScopeTest extends FinancialPostgresTestCase
{
    public static function resources(): array
    {
        return [['map', 'ledger_accounts'], ['revokeMapping', 'club_accounting_source_mappings'], ['revokeActivation', 'club_accounting_integration_activations'], ['approve', 'club_accounting_integration_proposals']];
    }

    #[DataProvider('resources')]
    public function test_foreign_rows_are_rejected_without_acquiring_their_locks(string $action, string $table): void
    {
        $p = Fixture::prepare($action, 'B4A');
        $foreign = Fixture::prepare($action, 'B4B');
        config(['instance.organisation_code' => $p['code']]);
        $id = $foreign[match ($action) {
            'map' => 'income', 'revokeMapping' => 'mapping', 'revokeActivation' => 'activation', default => 'proposal'
        }];
        $before = [Fixture::snapshot($p['club']), Fixture::snapshot($foreign['club'])];
        config(['database.connections.b4_foreign_lock' => config('database.connections.'.DB::getDefaultConnection())]);
        $peer = DB::connection('b4_foreign_lock');
        $peer->beginTransaction();
        $peer->table($table)->where('id', $id)->lockForUpdate()->first();
        DB::unprepared("SET lock_timeout='250ms'");
        try {
            $config = app(Configuration::class);
            $actor = User::findOrFail($p['actor']);
            try {
                if ($action === 'map') {
                    $config->map($p['club'], 'member_charge', 'membership_dues', LedgerAccount::findOrFail($id), 'B4-V1', '2027-07-01', 'FOREIGN-MAP', $actor);
                } elseif ($action === 'approve') {
                    app(Proposals::class)->approve(Club::findOrFail($p['club']), User::findOrFail($p['decider']), ClubAccountingIntegrationProposal::findOrFail($id), 0, 'Foreign');
                } else {
                    $row = $action === 'revokeMapping' ? ClubAccountingSourceMapping::findOrFail($id) : ClubAccountingIntegrationActivation::findOrFail($id);
                    $row->club_id = $p['club'];
                    $config->$action($row, $actor, 'FOREIGN', 'Foreign resource');
                }
                $this->fail('Foreign resource must not be accepted.');
            } catch (\Throwable $e) {
                $this->assertInstanceOf($action === 'map' ? AccountingIntegrationException::class : ModelNotFoundException::class, $e, 'Lock timeout is a failure, never an accepted rejection.');
            }
            $this->assertSame($before, [Fixture::snapshot($p['club']), Fixture::snapshot($foreign['club'])]);
        } finally {
            DB::unprepared('SET lock_timeout=DEFAULT');
            $peer->rollBack();
            DB::purge('b4_foreign_lock');
        }
    }
}
