<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingIntegrationProposal;
use App\Models\ClubAccountingIntegrationProposalDecision;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubCharge;
use App\Models\ClubMembership;
use App\Models\LedgerAccount;
use App\Models\MemberPayment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\AccountingIntegrationConfigurationService;
use App\Services\Finance\AccountingIntegrationProposalService;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\FinancialPostgresTestCase;

class Milestone3iizjProductionAccountingIntegrationConfigurationAndActivationNarrowImplementationTest extends FinancialPostgresTestCase
{

    private Club $club;

    private Permission $permission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->club = Club::create(['name' => 'Beenachi', 'code' => 'LCB']);
        config(['instance.organisation_code' => $this->club->code]);
        $this->permission = Permission::where('slug', AccountingIntegrationProposalService::PERMISSION)->firstOrFail();
    }

    public function test_permission_is_catalogued_but_not_granted_to_any_default_role(): void
    {
        $this->assertSame('finance.accounting.integration.configure', $this->permission->slug);
        $this->assertCount(0, $this->permission->roles);
    }

    public function test_only_active_same_club_president_or_treasurer_with_exact_permission_can_read(): void
    {
        $withoutPermission = $this->officer('president-admin', false);
        config(['instance.organisation_code' => $this->club->code]);
        $this->actingAs($withoutPermission)->getJson($this->base().'/readiness')->assertForbidden();
        foreach (['president-admin', 'treasurer'] as $slug) {
            $actor = $this->officer($slug, true);
            config(['instance.organisation_code' => $this->club->code]);
            $this->actingAs($actor)->getJson($this->base().'/readiness')->assertOk()->assertJsonPath('data.club.id', $this->club->id)->assertJsonMissing(['member_name' => 'x', 'payment' => 'x', 'receipt' => 'x']);
        }
        foreach (['secretary', 'member'] as $slug) {
            $actor = $this->officer($slug, true);
            config(['instance.organisation_code' => $this->club->code]);
            $this->actingAs($actor)->getJson($this->base().'/readiness')->assertForbidden();
        }
        $inactive = $this->officer('treasurer', true, 'inactive');
        config(['instance.organisation_code' => $this->club->code]);
        $this->actingAs($inactive)->getJson($this->base().'/readiness')->assertForbidden();
    }

    public function test_readiness_and_configuration_are_side_effect_free_and_publish_stable_status_vocabulary(): void
    {
        $actor = $this->officer('president-admin', true);
        $before = [ClubAccountingIntegrationProposal::count(), AuditLog::count()];
        config(['instance.organisation_code' => $this->club->code]);
        $response = $this->actingAs($actor)->getJson($this->base().'/readiness')->assertOk()
            ->assertJsonPath('data.readiness_code', 'Not ready')->assertJsonPath('data.ready', false);
        foreach (['Ready', 'Not ready', 'Already active', 'Revoked', 'Missing mappings', 'Invalid mappings', 'Unintegrated source inside coverage', 'Reconciliation defect', 'Closed or missing accounting period', 'Opening package not posted'] as $code) {
            $this->assertContains($code, $response->json('data.readiness_codes'));
        }
        config(['instance.organisation_code' => $this->club->code]);
        $this->actingAs($actor)->getJson($this->base().'/configuration')->assertOk()->assertJsonMissingPath('data.statement');
        $this->assertSame($before, [ClubAccountingIntegrationProposal::count(), AuditLog::count()]);
    }

    public function test_mapping_proposal_is_club_scoped_validated_audited_and_idempotent(): void
    {
        $actor = $this->officer('treasurer', true);
        $account = $this->account($this->club, 'DUES-INCOME', LedgerAccount::TYPE_INCOME);
        $payload = $this->mappingPayload($account);
        config(['instance.organisation_code' => $this->club->code]);
        $first = $this->actingAs($actor)->postJson($this->base().'/mapping-proposals', $payload)->assertCreated();
        $second = $this->actingAs($actor)->postJson($this->base().'/mapping-proposals', $payload)->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('club_accounting_integration_proposals', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $changed = $payload;
        $changed['mapping_version'] = 'v2';
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals', $changed)->assertConflict();
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals', $payload + ['status' => 'approved'])->assertUnprocessable();
    }

    public function test_invalid_and_cross_club_mapping_inputs_fail_closed(): void
    {
        $actor = $this->officer('president-admin', true);
        $other = Club::create(['name' => 'Other', 'code' => 'OTH']);
        $foreign = $this->account($other, 'OTHER-INCOME', LedgerAccount::TYPE_INCOME);
        config(['instance.organisation_code' => $this->club->code]);
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals', $this->mappingPayload($foreign))->assertUnprocessable();
        $bad = $this->account($this->club, 'BAD-ASSET', LedgerAccount::TYPE_ASSET);
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals', $this->mappingPayload($bad, (string) Str::uuid()))->assertUnprocessable();
        $payload = $this->mappingPayload($this->account($this->club, 'VALID-INCOME', LedgerAccount::TYPE_INCOME), (string) Str::uuid());
        $payload['source_key'] = 'unsupported';
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals', $payload)->assertUnprocessable();
    }

    public function test_proposer_cannot_decide_but_independent_officer_can_reject_once(): void
    {
        $proposer = $this->officer('president-admin', true);
        $approver = $this->officer('treasurer', true);
        $account = $this->account($this->club, 'DUES-INCOME', LedgerAccount::TYPE_INCOME);
        config(['instance.organisation_code' => $this->club->code]);
        $created = $this->actingAs($proposer)->postJson($this->base().'/mapping-proposals', $this->mappingPayload($account))->assertCreated();
        $url = $this->base().'/proposals/'.$created->json('data.id');
        $this->actingAs($proposer)->postJson($url.'/approve', ['lock_version' => 0])->assertForbidden();
        $this->actingAs($approver)->postJson($url.'/reject', ['lock_version' => 0, 'reason' => 'Independent review rejected the mapping.'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->actingAs($approver)->postJson($url.'/reject', ['lock_version' => 1, 'reason' => 'again'])->assertConflict();
        $this->assertDatabaseCount('club_accounting_integration_proposal_decisions', 1);
        $decision = ClubAccountingIntegrationProposalDecision::firstOrFail();
        $this->assertSame('rejected', $decision->decision);
        $this->expectException(\LogicException::class);
        $decision->update(['reason' => 'changed']);
    }

    public function test_only_proposer_can_withdraw_and_terminal_proposal_is_immutable(): void
    {
        $proposer = $this->officer('treasurer', true);
        $other = $this->officer('president-admin', true);
        config(['instance.organisation_code' => $this->club->code]);
        $created = $this->actingAs($proposer)->postJson($this->base().'/mapping-proposals', $this->mappingPayload($this->account($this->club, 'DUES-INCOME', LedgerAccount::TYPE_INCOME)))->assertCreated();
        $url = $this->base().'/proposals/'.$created->json('data.id').'/withdraw';
        $this->actingAs($other)->postJson($url, ['lock_version' => 0])->assertForbidden();
        $this->actingAs($proposer)->postJson($url, ['lock_version' => 0, 'reason' => 'No longer required.'])->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->actingAs($proposer)->postJson($url, ['lock_version' => 1])->assertConflict();
    }

    public function test_independent_approval_uses_existing_mapping_service_and_commits_audit_atomically(): void
    {
        $proposer = $this->officer('president-admin', true);
        $approver = $this->officer('treasurer', true);
        config(['instance.organisation_code' => $this->club->code]);
        $created = $this->actingAs($proposer)->postJson($this->base().'/mapping-proposals', $this->mappingPayload($this->account($this->club, 'DUES-INCOME', LedgerAccount::TYPE_INCOME)))->assertCreated();
        $this->actingAs($approver)->postJson($this->base().'/proposals/'.$created->json('data.id').'/approve', ['lock_version' => 0])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseCount('club_accounting_source_mappings', 1);
        $this->assertDatabaseCount('club_accounting_integration_proposal_decisions', 1);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertSame('member_charge', ClubAccountingIntegrationProposal::firstOrFail()->source_type);
    }

    public function test_routes_are_narrow_and_no_statement_or_direct_mutation_surface_exists(): void
    {
        $uris = collect(app('router')->getRoutes()->getRoutes())->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())->filter(fn ($uri) => str_contains($uri, 'finance/accounting/integration'))->values();
        $this->assertCount(8, $uris);
        foreach (['readiness', 'configuration', 'mapping-proposals', 'activation-proposals', 'revocation-proposals', 'approve', 'reject', 'withdraw'] as $part) {
            $this->assertTrue($uris->contains(fn ($uri) => str_contains($uri, $part)));
        }
        $this->assertFalse($uris->contains(fn ($uri) => str_contains($uri, 'statement') || str_contains($uri, 'journal') || str_contains($uri, 'delete') || str_contains($uri, 'restore')));
    }

    public function test_exact_payload_shapes_reject_irrelevant_targets(): void
    {
        $actor = $this->officer('president-admin', true);
        $account = $this->account($this->club, 'SHAPE-INCOME', LedgerAccount::TYPE_INCOME);
        $mapping = $this->mappingPayload($account);
        $mapping['mapping_id'] = 999;
        config(['instance.organisation_code' => $this->club->code]);
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals', $mapping)->assertUnprocessable();
        $activation = ['client_request_id' => (string) Str::uuid(), 'proposal_type' => 'charge_activation', 'source_type' => 'member_charge', 'mapping_version' => 'v1', 'coverage_start_date' => '2027-07-01', 'activation_id' => 1, 'reference' => 'SHAPE-ACT'];
        $this->actingAs($actor)->postJson($this->base().'/activation-proposals', $activation)->assertUnprocessable();
        $revocation = ['client_request_id' => (string) Str::uuid(), 'proposal_type' => 'mapping_revocation', 'source_type' => 'member_charge', 'mapping_id' => 1, 'activation_id' => 1, 'reference' => 'SHAPE-REV', 'reason' => 'shape'];
        $this->actingAs($actor)->postJson($this->base().'/revocation-proposals', $revocation)->assertUnprocessable();
    }

    public function test_target_snapshot_blocks_stale_approval_but_allows_rejection(): void
    {
        $proposer = $this->officer('president-admin', true);
        $approver = $this->officer('treasurer', true);
        $account = $this->account($this->club, 'TARGET-INCOME', LedgerAccount::TYPE_INCOME);
        $mapping = app(AccountingIntegrationConfigurationService::class)->map($this->club->id, 'member_charge', 'membership_dues', $account, 'v1', '2027-07-01', 'TARGET-OLD', $proposer);
        $payload = $this->mappingPayload($account);
        $payload['proposal_type'] = 'mapping_replace';
        $payload['mapping_id'] = $mapping->id;
        $payload['reference'] = 'TARGET-NEW';
        config(['instance.organisation_code' => $this->club->code]);
        $created = $this->actingAs($proposer)->postJson($this->base().'/mapping-proposals', $payload)->assertCreated();
        $this->assertSame('current', ClubAccountingIntegrationProposal::firstOrFail()->target_snapshot['lifecycle']);
        app(AccountingIntegrationConfigurationService::class)->revokeMapping($mapping, $proposer, 'TARGET-REVOKE', 'Changed after proposal.');
        $url = $this->base().'/proposals/'.$created->json('data.id');
        $this->actingAs($approver)->postJson($url.'/approve', ['lock_version' => 0])->assertConflict();
        $this->actingAs($approver)->postJson($url.'/reject', ['lock_version' => 0, 'reason' => 'Stale target rejected.'])->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_authority_removed_before_transactional_decision_is_denied(): void
    {
        $proposer = $this->officer('president-admin', true);
        $approver = $this->officer('treasurer', true);
        $account = $this->account($this->club, 'AUTH-INCOME', LedgerAccount::TYPE_INCOME);
        config(['instance.organisation_code' => $this->club->code]);
        $this->actingAs($proposer)->postJson($this->base().'/mapping-proposals', $this->mappingPayload($account))->assertCreated();
        Role::where('slug', 'treasurer')->firstOrFail()->permissions()->detach($this->permission->id);
        $this->expectException(AuthorizationException::class);
        app(AccountingIntegrationProposalService::class)->approve($this->club, $approver, ClubAccountingIntegrationProposal::firstOrFail(), 0, null);
    }

    public function test_database_current_activation_slot_is_unique_terminal_bound_and_reactivatable(): void
    {
        $actor = $this->officer('president-admin', true);
        $foundation = $this->openingFoundation($actor);
        $configuration = app(AccountingIntegrationConfigurationService::class);
        $activation = $configuration->activate($this->club->id, 'member_charge', '2027-07-01', 'v1', 'SLOT-A', $actor);
        $this->assertDatabaseHas('club_accounting_integration_current_activations', ['club_id' => $this->club->id, 'source_type' => 'member_charge', 'integration_activation_id' => $activation->id]);
        $duplicate = $activation->replicate();
        $duplicate->public_id = (string) Str::uuid();
        $duplicate->activation_reference = 'SLOT-DUP-HISTORY';
        $duplicate->save();
        try {
            DB::table('club_accounting_integration_current_activations')->insert(['club_id' => $this->club->id, 'source_type' => 'member_charge', 'integration_activation_id' => $duplicate->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->fail('Duplicate current slot should fail.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $slot = DB::table('club_accounting_integration_current_activations')->where('integration_activation_id', $activation->id)->first();
        foreach ([
            ['integration_activation_id' => $duplicate->id],
            ['club_id' => Club::create(['name' => 'Other Club', 'code' => 'OTH'])->id],
            ['source_type' => 'member_payment'],
        ] as $mutation) {
            try {
                DB::table('club_accounting_integration_current_activations')->where('id', $slot->id)->update($mutation);
                $this->fail('Current activation slot update should fail.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('accounting_current_activation_immutable', $exception->getMessage());
            }
            $this->assertEquals($slot, DB::table('club_accounting_integration_current_activations')->where('id', $slot->id)->first());
        }
        $configuration->revokeActivation($activation, $actor, 'SLOT-REVOKE', 'Controlled revocation.');
        $this->assertDatabaseHas('club_accounting_integration_activation_events', ['integration_activation_id' => $activation->id, 'event_type' => 'revoked']);
        $this->assertDatabaseMissing('club_accounting_integration_current_activations', ['integration_activation_id' => $activation->id]);
        $reactivated = $configuration->activate($this->club->id, 'member_charge', '2027-07-01', 'v1', 'SLOT-B', $actor);
        $this->assertSame(1, DB::table('club_accounting_integration_current_activations')->where('club_id', $this->club->id)->where('source_type', 'member_charge')->count());
        $this->assertDatabaseHas('club_accounting_integration_current_activations', ['integration_activation_id' => $reactivated->id]);
        $this->assertDatabaseCount('club_accounting_integration_activations', 3);
        $this->assertDatabaseHas('club_accounting_integration_activations', ['id' => $activation->id]);
        $this->assertDatabaseHas('club_accounting_integration_activations', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('club_accounting_integration_activations', ['id' => $reactivated->id]);
    }

    public function test_readiness_reports_actual_missing_ready_invalid_active_and_revoked_states(): void
    {
        $actor = $this->officer('president-admin', true);
        $this->openingFoundation($actor);
        $configuration = app(AccountingIntegrationConfigurationService::class);
        config(['instance.organisation_code' => $this->club->code]);
        $missing = $this->actingAs($actor)->getJson($this->base().'/readiness')->assertOk();
        $this->assertSame(['Missing mappings', 'Missing mappings'], collect($missing->json('data.sources'))->pluck('readiness_code')->all());
        $income = $this->account($this->club, 'READY-INCOME', LedgerAccount::TYPE_INCOME);
        $bank = $this->account($this->club, 'BANK-READY', LedgerAccount::TYPE_ASSET);
        foreach (ClubCharge::CATEGORIES as $i => $key) {
            $configuration->map($this->club->id, 'member_charge', $key, $income, 'v1', '2027-07-01', 'READY-C-'.$i, $actor);
        }
        foreach (MemberPayment::MODES as $i => $key) {
            $configuration->map($this->club->id, 'member_payment', $key, $bank, 'v1', '2027-07-01', 'READY-P-'.$i, $actor);
        }
        config(['instance.organisation_code' => $this->club->code]);
        $ready = $this->actingAs($actor)->getJson($this->base().'/readiness')->assertOk();
        $ready->assertJsonPath('data.readiness_code', 'Ready')->assertJsonPath('data.ready', true);
        $this->assertSame(['Ready', 'Ready'], collect($ready->json('data.sources'))->pluck('readiness_code')->all());
        $income->is_active = false;
        $income->save();
        $invalid = $this->actingAs($actor)->getJson($this->base().'/readiness')->assertOk();
        $this->assertSame('Invalid mappings', $invalid->json('data.sources.0.readiness_code'));
        $income->is_active = true;
        $income->save();
        $activation = $configuration->activate($this->club->id, 'member_charge', '2027-07-01', 'v1', 'READY-ACT', $actor);
        $this->assertSame('Already active', $this->actingAs($actor)->getJson($this->base().'/readiness')->json('data.sources.0.readiness_code'));
        $configuration->revokeActivation($activation, $actor, 'READY-REV', 'Readiness revocation.');
        $this->assertSame('Revoked', $this->actingAs($actor)->getJson($this->base().'/readiness')->json('data.sources.0.readiness_code'));
    }

    private function base(): string
    {
        return '/api/v1/clubs/'.$this->club->id.'/finance/accounting/integration';
    }

    private function officer(string $roleSlug, bool $grant, string $status = 'active'): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        if ($grant) {
            $role->permissions()->syncWithoutDetaching([$this->permission->id]);
        } $user = User::factory()->create();
        ClubMembership::create(['club_id' => $this->club->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => $status, 'joined_at' => now()]);

        return $user;
    }

    private function account(Club $club, string $code, string $type): LedgerAccount
    {
        return LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $type === LedgerAccount::TYPE_INCOME ? 'credit' : 'debit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => User::factory()->create()->id]);
    }

    private function openingFoundation(User $actor): array
    {
        $receivable = LedgerAccount::create(['club_id' => $this->club->id, 'code' => 'MEMBER-RECEIVABLES', 'name' => 'Receivables', 'account_type' => 'asset', 'normal_balance' => 'debit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id]);
        $equity = LedgerAccount::create(['club_id' => $this->club->id, 'code' => 'OPENING-EQUITY', 'name' => 'Equity', 'account_type' => 'equity', 'normal_balance' => 'credit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id]);
        $period = ClubAccountingPeriod::create(['club_id' => $this->club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]);
        $journal = app(ClubJournalPostingService::class)->post(new JournalPostingRequest($this->club->id, $period->id, '2027-07-01', 'Opening', 'INR', 'accounting_opening_package', 'slot-opening', 'activated', (string) Str::uuid(), $actor->id, [new JournalLineInput($receivable->id, 'debit', 1, fundCode: 'GENERAL_CLUB_FUND'), new JournalLineInput($equity->id, 'credit', 1, fundCode: 'GENERAL_CLUB_FUND')]));
        ClubAccountingCutoverPackage::create(['club_id' => $this->club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01', 'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'posted', 'currency' => 'INR', 'canonical_checksum' => str_repeat('a', 64), 'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => true, 'opening_journal_entry_id' => $journal->id, 'posted_at' => now(), 'posted_by_user_id' => $actor->id, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]);

        return compact('receivable', 'equity', 'period', 'journal');
    }

    private function mappingPayload(LedgerAccount $account, ?string $requestId = null): array
    {
        return ['client_request_id' => $requestId ?? (string) Str::uuid(), 'proposal_type' => 'mapping_create', 'source_type' => 'member_charge', 'source_key' => 'membership_dues', 'ledger_account_id' => $account->id, 'mapping_version' => 'v1', 'effective_date' => '2027-07-01', 'reference' => 'MAP-'.Str::upper(Str::random(12))];
    }
}
