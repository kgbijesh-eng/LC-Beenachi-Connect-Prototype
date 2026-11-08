<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingIntegrationProposal;
use App\Models\ClubAccountingIntegrationProposalDecision;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubCharge;
use App\Models\ClubMembership;
use App\Models\LedgerAccount;
use App\Models\MemberPayment;
use App\Models\User;
use App\Services\Finance\Exceptions\AccountingIntegrationConflictException;
use App\Services\Finance\Exceptions\AccountingIntegrationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class AccountingIntegrationProposalService
{
    public const PERMISSION = 'finance.accounting.integration.configure';

    public function __construct(
        private AccountingIntegrationConfigurationService $configuration,
        private AccountingIntegrationReconciliationService $reconciliation,
    ) {}

    public function readiness(Club $club, User $actor): array
    {
        $this->authority($club, $actor);
        $package = ClubAccountingCutoverPackage::query()->where('club_id', $club->id)->where('status', ClubAccountingCutoverPackage::STATUS_POSTED)->whereNotNull('opening_journal_entry_id')->first();
        $defects = $this->reconciliation->defects($club->id);
        $sources = collect([AccountingIntegrationConfigurationService::SOURCE_CHARGE, AccountingIntegrationConfigurationService::SOURCE_PAYMENT])
            ->map(fn (string $source) => $this->sourceReadiness($club->id, $source, $package, $defects))->values();
        $ready = $sources->every(fn (array $source): bool => $source['ready']);

        return [
            'club' => ['id' => $club->id, 'name' => $club->name],
            'readiness_code' => $ready ? 'Ready' : 'Not ready',
            'ready' => $ready,
            'readiness_codes' => ['Ready','Not ready','Already active','Revoked','Missing mappings','Invalid mappings','Unintegrated source inside coverage','Reconciliation defect','Closed or missing accounting period','Opening package not posted'],
            'sources' => $sources->all(),
        ];
    }

    public function currentConfiguration(Club $club, User $actor): array
    {
        $this->authority($club, $actor);
        $mappings = ClubAccountingSourceMapping::query()->with(['ledgerAccount', 'events'])->where('club_id', $club->id)->get();
        $activations = ClubAccountingIntegrationActivation::query()->with('events')->where('club_id', $club->id)->get();

        return [
            'club' => ['id' => $club->id, 'name' => $club->name],
            'mappings' => $mappings->map(fn ($mapping) => [
                'id' => $mapping->public_id, 'source_type' => $mapping->source_type, 'source_key' => $mapping->canonical_source_key,
                'mapping_version' => $mapping->mapping_version, 'effective_date' => $mapping->effective_date->format('Y-m-d'),
                'expires_at' => $mapping->expires_at?->format('Y-m-d'), 'revoked' => $mapping->events->isNotEmpty(),
                'account' => ['id' => $mapping->ledgerAccount->public_id, 'code' => $mapping->ledgerAccount->code, 'name' => $mapping->ledgerAccount->name, 'type' => $mapping->ledgerAccount->account_type, 'currency' => $mapping->ledgerAccount->currency, 'active' => $mapping->ledgerAccount->is_active],
            ])->values()->all(),
            'activations' => $activations->map(fn ($activation) => [
                'id' => $activation->public_id, 'source_type' => $activation->source_type, 'mapping_version' => $activation->mapping_version,
                'coverage_start_date' => $activation->coverage_start_date->format('Y-m-d'), 'status' => $activation->events->isEmpty() ? 'active' : 'revoked',
            ])->values()->all(),
            'pending_proposals' => ClubAccountingIntegrationProposal::query()->where('club_id', $club->id)->where('status', 'pending')->pluck('public_id')->all(),
        ];
    }

    public function propose(Club $club, User $actor, array $data): ClubAccountingIntegrationProposal
    {
        return app(OrganisationTransactionGuard::class)->run($club, function (OrganisationTransactionContext $context) use ($club, $actor, $data): ClubAccountingIntegrationProposal {
            $role = $this->authority($club, $actor);
            $material = $this->normalizedMaterial($club, $data);
            $checksum = $this->checksum(array_merge($material, ['club_id'=>$club->id,'proposer_user_id'=>$actor->id,'proposer_role_slug'=>$role]));
            $replay = ClubAccountingIntegrationProposal::query()->where('club_id', $club->id)->where('client_request_id', strtolower($data['client_request_id']))->lockForUpdate()->first();
            if ($replay) {
                if (! hash_equals($replay->canonical_checksum, $checksum)) {
                    throw new AccountingIntegrationConflictException('The client request UUID was reused with a changed payload.');
                }
                return $replay;
            }

            $proposal = ClubAccountingIntegrationProposal::create(array_merge($material, [
                'club_id' => $club->id, 'proposer_user_id' => $actor->id, 'proposer_role_slug' => $role,
                'client_request_id' => strtolower($data['client_request_id']), 'canonical_checksum' => $checksum,
                'status' => 'pending', 'lock_version' => 0,
            ]));
            $this->audit($proposal, $actor, 'proposed', null, [], $this->proposalState($proposal));
            return $proposal;
        }, 1);
    }

    public function approve(Club $club, User $actor, ClubAccountingIntegrationProposal $proposal, int $lockVersion, ?string $reason): ClubAccountingIntegrationProposal
    {
        return $this->terminal($club, $actor, $proposal, $lockVersion, 'approved', $reason);
    }

    public function reject(Club $club, User $actor, ClubAccountingIntegrationProposal $proposal, int $lockVersion, ?string $reason): ClubAccountingIntegrationProposal
    {
        if (! $reason || trim($reason) === '') throw new AccountingIntegrationException('A rejection reason is required.');
        return $this->terminal($club, $actor, $proposal, $lockVersion, 'rejected', $reason);
    }

    public function withdraw(Club $club, User $actor, ClubAccountingIntegrationProposal $proposal, int $lockVersion, ?string $reason): ClubAccountingIntegrationProposal
    {
        return $this->terminal($club, $actor, $proposal, $lockVersion, 'withdrawn', $reason);
    }

    private function terminal(Club $club, User $actor, ClubAccountingIntegrationProposal $proposal, int $lockVersion, string $decision, ?string $reason): ClubAccountingIntegrationProposal
    {
        return app(OrganisationTransactionGuard::class)->run($club, function (OrganisationTransactionContext $context) use ($club, $actor, $proposal, $lockVersion, $decision, $reason): ClubAccountingIntegrationProposal {
            $locked = ClubAccountingIntegrationProposal::query()->whereKey($proposal->id)->where('club_id', $club->id)->lockForUpdate()->firstOrFail();
            $role = $this->authority($club, $actor);
            if ($locked->status !== 'pending' || $locked->lock_version !== $lockVersion || $locked->decision()->exists()) throw new AccountingIntegrationConflictException('The proposal is terminal or its lock version is stale.');
            if ($decision === 'withdrawn' && $locked->proposer_user_id !== $actor->id) throw new AuthorizationException('Only the proposer may withdraw a proposal.');
            if ($decision !== 'withdrawn' && $locked->proposer_user_id === $actor->id) throw new AuthorizationException('The proposer cannot decide the same proposal.');
            $this->assertIntegrity($locked);
            if ($decision === 'approved') $this->assertApprovalFresh($locked);
            $before = ['proposal'=>$this->proposalState($locked),'configuration'=>$this->configurationSnapshot($locked->club_id)];
            try { $result = $decision === 'approved' ? $this->apply($context, $locked, $actor) : null; }
            catch (AccountingIntegrationConflictException $exception) { throw $exception; }
            catch (AccountingIntegrationException $exception) { throw new AccountingIntegrationConflictException($exception->getMessage(), previous: $exception); }
            $locked->status = $decision; $locked->lock_version++; $locked->decided_at = now(); $locked->save();
            $after = ['proposal'=>$this->proposalState($locked),'configuration'=>$this->configurationSnapshot($locked->club_id)];
            ClubAccountingIntegrationProposalDecision::create([
                'club_id'=>$club->id,'proposal_id'=>$locked->id,'decision'=>$decision,'reason'=>$reason,'decided_by_user_id'=>$actor->id,
                'decider_role_slug'=>$role,'proposal_checksum'=>$locked->canonical_checksum,'result_type'=>$result?->getMorphClass(),'result_id'=>$result?->getKey(),
                'before_state'=>$before,'after_state'=>$after,'decided_at'=>now(),
            ]);
            $this->audit($locked, $actor, $decision, $reason, $before, $after, $result);
            return $locked->fresh(['decision']);
        }, 1);
    }

    private function apply(OrganisationTransactionContext $context, ClubAccountingIntegrationProposal $proposal, User $actor): Model
    {
        if (in_array($proposal->proposal_type, ['mapping_create', 'mapping_replace'], true)) {
            $account = LedgerAccount::query()->whereKey($proposal->ledger_account_id)->where('club_id', $proposal->club_id)->lockForUpdate()->firstOrFail();
            if ($proposal->proposal_type === 'mapping_replace' && $proposal->mapping_id) {
                $old = ClubAccountingSourceMapping::query()->whereKey($proposal->mapping_id)->where('club_id', $proposal->club_id)->lockForUpdate()->firstOrFail();
                if (! $old->events()->exists()) $this->configuration->revokeMappingWithin($context, $old, $actor, $proposal->reference.'-replace', 'Approved mapping replacement.');
            }
            return $this->configuration->mapWithin($context, $proposal->club_id, $proposal->source_type, $proposal->source_key, $account, $proposal->mapping_version, $proposal->effective_date->format('Y-m-d'), $proposal->reference, $actor, $proposal->expires_at?->format('Y-m-d'));
        }
        if ($proposal->proposal_type === 'mapping_revocation') {
            $mapping = ClubAccountingSourceMapping::query()->whereKey($proposal->mapping_id)->where('club_id', $proposal->club_id)->lockForUpdate()->firstOrFail();
            return $this->configuration->revokeMappingWithin($context, $mapping, $actor, $proposal->reference, (string) $proposal->reason);
        }
        if ($proposal->proposal_type === 'activation_revocation') {
            $activation = ClubAccountingIntegrationActivation::query()->whereKey($proposal->activation_id)->where('club_id', $proposal->club_id)->lockForUpdate()->firstOrFail();
            return $this->configuration->revokeActivationWithin($context, $activation, $actor, $proposal->reference, (string) $proposal->reason);
        }

        if ($proposal->activation_id) {
            $old = ClubAccountingIntegrationActivation::query()->whereKey($proposal->activation_id)->where('club_id', $proposal->club_id)->lockForUpdate()->firstOrFail();
            if (! $old->events()->exists()) $this->configuration->revokeActivationWithin($context, $old, $actor, $proposal->reference.'-supersede', 'Approved activation replacement.');
        }
        $this->assertActivationReady($proposal);
        return $this->configuration->activateWithin($context, $proposal->club_id, $proposal->source_type, $proposal->coverage_start_date->format('Y-m-d'), $proposal->mapping_version, $proposal->reference, $actor);
    }

    private function authority(Club $club, User $user): string
    {
        $membership = ClubMembership::query()->with('role.permissions')->where('club_id', $club->id)->where('user_id', $user->id)->where('status', 'active')->first();
        if (! $membership || ! $membership->role || ! in_array($membership->role->slug, ['president-admin', 'treasurer'], true) || ! $membership->role->permissions->contains('slug', self::PERMISSION)) {
            throw new AuthorizationException('An active same-club President or Treasurer appointment with the accounting integration permission is required.');
        }
        return $membership->role->slug;
    }

    private function normalizedMaterial(Club $club, array $data): array
    {
        $type = $data['proposal_type'];
        if (! in_array($type, ClubAccountingIntegrationProposal::TYPES, true)) throw new AccountingIntegrationException('Unsupported proposal type.');
        $keys = ['proposal_type','source_type','source_key','ledger_account_id','mapping_version','effective_date','expires_at','coverage_start_date','activation_id','mapping_id','reference','reason'];
        $result = array_replace(array_fill_keys($keys, null), Arr::only($data, $keys));
        foreach (['ledger_account_id'=>'ledger_accounts','mapping_id'=>'club_accounting_source_mappings','activation_id'=>'club_accounting_integration_activations'] as $field=>$table) {
            if (isset($result[$field]) && ! DB::table($table)->where('id', $result[$field])->where('club_id', $club->id)->exists()) throw new AccountingIntegrationException('A referenced accounting resource is invalid for this club.');
        }
        if (in_array($type, ['mapping_create','mapping_replace'], true)) {
            $allowed = $result['source_type'] === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? ClubCharge::CATEGORIES : MemberPayment::MODES;
            if (! in_array($result['source_key'] ?? null, $allowed, true)) throw new AccountingIntegrationException('The source key is unsupported.');
            $account = LedgerAccount::query()->whereKey($result['ledger_account_id'])->first();
            $valid = $account && $account->club_id === $club->id && $account->is_active && $account->currency === 'INR'
                && (($result['source_type'] === AccountingIntegrationConfigurationService::SOURCE_CHARGE && $account->account_type === LedgerAccount::TYPE_INCOME)
                    || ($result['source_type'] === AccountingIntegrationConfigurationService::SOURCE_PAYMENT && $account->account_type === LedgerAccount::TYPE_ASSET && preg_match('/\A(?:CASH|BANK)-/', $account->code)));
            if (! $valid) throw new AccountingIntegrationException('The mapped account is invalid for the source.');
        }
        if ($type === 'charge_activation' && $result['source_type'] !== AccountingIntegrationConfigurationService::SOURCE_CHARGE) throw new AccountingIntegrationException('Charge activation requires member_charge.');
        if ($type === 'payment_activation' && $result['source_type'] !== AccountingIntegrationConfigurationService::SOURCE_PAYMENT) throw new AccountingIntegrationException('Payment activation requires member_payment.');
        $result['terminal_event_id'] = $this->terminalIdentity($result);
        $result['target_snapshot'] = $this->targetSnapshot($club->id, $result, true);
        return $result;
    }

    private function assertIntegrity(ClubAccountingIntegrationProposal $proposal): void
    {
        $material = Arr::only($proposal->getAttributes(), ['proposal_type','source_type','source_key','ledger_account_id','mapping_version','effective_date','expires_at','coverage_start_date','activation_id','mapping_id','reference','reason','terminal_event_id']);
        $material['target_snapshot'] = $proposal->target_snapshot;
        foreach (['effective_date','expires_at','coverage_start_date'] as $date) if ($proposal->{$date}) $material[$date] = $proposal->{$date}->format('Y-m-d');
        $checksumMaterial = array_merge($material, ['club_id'=>$proposal->club_id,'proposer_user_id'=>$proposal->proposer_user_id,'proposer_role_slug'=>$proposal->proposer_role_slug]);
        if (! hash_equals($proposal->canonical_checksum, $this->checksum($checksumMaterial))) throw new AccountingIntegrationConflictException('The proposal integrity checksum is invalid.');
    }

    private function assertApprovalFresh(ClubAccountingIntegrationProposal $proposal): void
    {
        $material = Arr::only($proposal->getAttributes(), ['proposal_type','source_type','source_key','ledger_account_id','mapping_version','effective_date','expires_at','coverage_start_date','activation_id','mapping_id','reference','reason','terminal_event_id']);
        if ($proposal->target_snapshot !== $this->targetSnapshot($proposal->club_id, $material, true)) throw new AccountingIntegrationConflictException('The authoritative proposal target is stale.');
        try { $currentRole = $this->authority(Club::findOrFail($proposal->club_id), $proposal->proposer); }
        catch (AuthorizationException) { throw new AccountingIntegrationConflictException('The proposer appointment context changed.'); }
        if ($proposal->proposer_role_slug !== $currentRole) throw new AccountingIntegrationConflictException('The proposer appointment context changed.');
    }

    private function terminalIdentity(array $data): ?int
    {
        if (! empty($data['mapping_id'])) return DB::table('club_accounting_source_mapping_events')->where('source_mapping_id', $data['mapping_id'])->value('id');
        if (! empty($data['activation_id'])) return DB::table('club_accounting_integration_activation_events')->where('integration_activation_id', $data['activation_id'])->value('id');
        return null;
    }

    private function targetSnapshot(int $clubId, array $data, bool $lock): ?array
    {
        if (! empty($data['mapping_id'])) {
            $query=ClubAccountingSourceMapping::query()->with('events')->whereKey($data['mapping_id'])->where('club_id',$clubId); if($lock)$query->lockForUpdate();
            $mapping=$query->firstOrFail(); $terminal=$mapping->events->sortByDesc('id')->first(); $current=$terminal===null;
            if ($mapping->source_type!==$data['source_type']) throw new AccountingIntegrationException('The mapping target source does not match the proposal.');
            if ($data['proposal_type']==='mapping_replace' && $mapping->canonical_source_key!==$data['source_key']) throw new AccountingIntegrationException('The replacement target source key does not match the proposal.');
            if ($data['proposal_type']==='mapping_revocation' && !$current) throw new AccountingIntegrationException('The mapping target must be current.');
            return ['kind'=>'mapping','id'=>$mapping->id,'club_id'=>$mapping->club_id,'source_type'=>$mapping->source_type,'source_key'=>$mapping->canonical_source_key,'mapping_version'=>$mapping->mapping_version,'effective_date'=>$mapping->effective_date->format('Y-m-d'),'expires_at'=>$mapping->expires_at?->format('Y-m-d'),'ledger_account_id'=>$mapping->ledger_account_id,'terminal_event_id'=>$terminal?->id,'lifecycle'=>$current?'current':'terminal'];
        }
        if (! empty($data['activation_id'])) {
            $query=ClubAccountingIntegrationActivation::query()->with('events')->whereKey($data['activation_id'])->where('club_id',$clubId); if($lock)$query->lockForUpdate();
            $activation=$query->firstOrFail(); $terminal=$activation->events->sortByDesc('id')->first(); $current=$terminal===null;
            if ($activation->source_type!==$data['source_type']) throw new AccountingIntegrationException('The activation target source does not match the proposal.');
            if ($data['proposal_type']==='reactivation' && $current) throw new AccountingIntegrationException('Reactivation requires a terminal activation.');
            if (in_array($data['proposal_type'],['coverage_start_date_change','mapping_version_change','activation_revocation'],true) && !$current) throw new AccountingIntegrationException('The activation target must be current.');
            return ['kind'=>'activation','id'=>$activation->id,'club_id'=>$activation->club_id,'source_type'=>$activation->source_type,'mapping_version'=>$activation->mapping_version,'coverage_start_date'=>$activation->coverage_start_date->format('Y-m-d'),'opening_package_id'=>$activation->opening_package_id,'opening_journal_entry_id'=>$activation->opening_journal_entry_id,'terminal_event_id'=>$terminal?->id,'lifecycle'=>$current?'current':'terminal'];
        }
        return null;
    }

    private function assertActivationReady(ClubAccountingIntegrationProposal $proposal): void
    {
        $date = $proposal->coverage_start_date->format('Y-m-d');
        $package = ClubAccountingCutoverPackage::query()->where('club_id',$proposal->club_id)->where('status',ClubAccountingCutoverPackage::STATUS_POSTED)->whereNotNull('opening_journal_entry_id')->first();
        if (! $package || ! $package->openingJournal()->where('status','posted')->exists()) throw new AccountingIntegrationConflictException('Opening package not posted or opening journal invalid.');
        if (ClubAccountingPeriod::query()->where('club_id',$proposal->club_id)->where('status','open')->where('start_date','<=',$date)->where('end_date','>=',$date)->count() !== 1) throw new AccountingIntegrationConflictException('Closed or missing accounting period.');
        if (LedgerAccount::query()->where('club_id',$proposal->club_id)->where('code','MEMBER-RECEIVABLES')->where('is_active',true)->count() !== 1) throw new AccountingIntegrationConflictException('MEMBER-RECEIVABLES is missing or inactive.');
        foreach ([[AccountingIntegrationConfigurationService::SOURCE_CHARGE, ClubCharge::CATEGORIES],[AccountingIntegrationConfigurationService::SOURCE_PAYMENT, MemberPayment::MODES]] as [$source,$keys]) {
            foreach ($keys as $key) {
                $valid = ClubAccountingSourceMapping::query()->with(['events','ledgerAccount'])->where('club_id',$proposal->club_id)->where('source_type',$source)->where('canonical_source_key',$key)->where('mapping_version',$proposal->mapping_version)->where('effective_date','<=',$date)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>=',$date))->get()->filter(fn($m)=>$m->events->isEmpty() && $m->ledgerAccount && $m->ledgerAccount->is_active && $m->ledgerAccount->club_id===$proposal->club_id && $m->ledgerAccount->currency==='INR' && ($source===AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $m->ledgerAccount->account_type===LedgerAccount::TYPE_INCOME : $m->ledgerAccount->account_type===LedgerAccount::TYPE_ASSET && preg_match('/\A(?:CASH|BANK)-/',$m->ledgerAccount->code)))->count();
                if ($valid !== 1) throw new AccountingIntegrationConflictException('Missing or invalid required mappings.');
            }
        }
        if ($this->reconciliation->defects($proposal->club_id) !== []) throw new AccountingIntegrationConflictException('Accounting reconciliation reports a blocking defect.');
    }

    private function sourceReadiness(int $clubId, string $source, ?ClubAccountingCutoverPackage $package, array $defects): array
    {
        $slot=DB::table('club_accounting_integration_current_activations')->where('club_id',$clubId)->where('source_type',$source)->first();
        $active=$slot?ClubAccountingIntegrationActivation::query()->with('events')->find($slot->integration_activation_id):ClubAccountingIntegrationActivation::query()->with('events')->where('club_id',$clubId)->where('source_type',$source)->latest('id')->first();
        $reasons=[]; $code='Ready'; $version=$active?->mapping_version;
        if (! $package) { $code='Opening package not posted'; $reasons[]=$code; }
        elseif ($slot && $active) $code='Already active';
        elseif ($active && $active->events->isNotEmpty()) $code='Revoked';
        else {
            $version ??= ClubAccountingSourceMapping::query()->where('club_id',$clubId)->where('source_type',$source)->latest('id')->value('mapping_version');
            $date=$package->cutover_date->format('Y-m-d');
            $expected=$source===AccountingIntegrationConfigurationService::SOURCE_CHARGE?ClubCharge::CATEGORIES:MemberPayment::MODES;
            $rows=ClubAccountingSourceMapping::query()->with(['events','ledgerAccount'])->where('club_id',$clubId)->where('source_type',$source)->where('mapping_version',$version)->get();
            $current=$rows->filter(fn($mapping)=>$mapping->events->isEmpty());
            if (ClubAccountingPeriod::query()->where('club_id',$clubId)->where('status','open')->where('start_date','<=',$date)->where('end_date','>=',$date)->count()!==1) { $code='Closed or missing accounting period'; $reasons[]=$code; }
            elseif (! $version || collect($expected)->contains(fn($key)=>$current->where('canonical_source_key',$key)->count()!==1)) { $code='Missing mappings'; $reasons[]=$code; }
            elseif (LedgerAccount::query()->where('club_id',$clubId)->where('code','MEMBER-RECEIVABLES')->where('is_active',true)->where('currency','INR')->count()!==1 || $current->contains(fn($m)=>!$m->ledgerAccount || $m->ledgerAccount->club_id!==$clubId || !$m->ledgerAccount->is_active || $m->ledgerAccount->currency!=='INR' || ($source===AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $m->ledgerAccount->account_type!==LedgerAccount::TYPE_INCOME : $m->ledgerAccount->account_type!==LedgerAccount::TYPE_ASSET || !preg_match('/\A(?:CASH|BANK)-/',$m->ledgerAccount->code)))) { $code='Invalid mappings'; $reasons[]=$code; }
            elseif ($this->hasUnintegratedSource($clubId,$source,$date)) { $code='Unintegrated source inside coverage'; $reasons[]=$code; }
            elseif ($defects!==[]) { $code='Reconciliation defect'; $reasons[]=$code; }
        }
        return ['source_type'=>$source,'readiness_code'=>$code,'ready'=>$code==='Ready','blocking_reasons'=>$reasons,'mapping_version'=>$version,'coverage_start_date'=>$active?->coverage_start_date?->format('Y-m-d'),'activation_status'=>$active ? ($active->events->isEmpty()?'active':'revoked'):'not_active','configuration_id'=>$active?->public_id,'lock_version'=>0];
    }

    private function proposalState(ClubAccountingIntegrationProposal $proposal): array { return ['id'=>$proposal->public_id,'status'=>$proposal->status,'lock_version'=>$proposal->lock_version,'checksum'=>$proposal->canonical_checksum]; }
    private function configurationSnapshot(int $clubId): array { return ['mapping_ids'=>ClubAccountingSourceMapping::query()->where('club_id',$clubId)->orderBy('id')->pluck('public_id')->all(),'mapping_terminal_event_ids'=>DB::table('club_accounting_source_mapping_events')->where('club_id',$clubId)->orderBy('id')->pluck('public_id')->all(),'activation_ids'=>ClubAccountingIntegrationActivation::query()->where('club_id',$clubId)->orderBy('id')->pluck('public_id')->all(),'activation_terminal_event_ids'=>DB::table('club_accounting_integration_activation_events')->where('club_id',$clubId)->orderBy('id')->pluck('public_id')->all()]; }
    private function hasUnintegratedSource(int $clubId,string $source,string $date): bool { $table=$source===AccountingIntegrationConfigurationService::SOURCE_CHARGE?'club_charges':'member_payments'; $dateColumn=$source===AccountingIntegrationConfigurationService::SOURCE_CHARGE?'issued_at':'payment_date'; $statuses=$source===AccountingIntegrationConfigurationService::SOURCE_CHARGE?['issued','closed','cancelled']:['recorded','voided']; return DB::table("{$table} as s")->leftJoin('club_accounting_source_integrations as i',fn($join)=>$join->on('i.club_id','=','s.club_id')->on('i.source_identifier','=','s.public_id')->where('i.source_type',$source))->where('s.club_id',$clubId)->whereIn('s.status',$statuses)->whereDate("s.{$dateColumn}",'>=',$date)->whereNull('i.id')->exists(); }
    private function checksum(array $material): string { ksort($material); return hash('sha256', json_encode($material, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
    private function audit(ClubAccountingIntegrationProposal $proposal, User $actor, string $decision, ?string $reason, array $before, array $after, ?Model $result=null): void { AuditLog::create(['club_id'=>$proposal->club_id,'user_id'=>$actor->id,'action'=>'accounting_integration.'.$decision,'auditable_type'=>$proposal->getMorphClass(),'auditable_id'=>$proposal->id,'metadata'=>['proposal_id'=>$proposal->public_id,'proposal_type'=>$proposal->proposal_type,'source_type'=>$proposal->source_type,'source_key'=>$proposal->source_key,'proposed_configuration'=>Arr::only($proposal->getAttributes(),['ledger_account_id','mapping_version','effective_date','expires_at','coverage_start_date','activation_id','mapping_id','terminal_event_id','reference']),'proposer_user_id'=>$proposal->proposer_user_id,'decision_actor_user_id'=>$actor->id,'decision'=>$decision,'reason'=>$reason,'before'=>$before,'after'=>$after,'checksum'=>$proposal->canonical_checksum,'client_request_id'=>$proposal->client_request_id,'result_type'=>$result?->getMorphClass(),'result_id'=>$result?->getKey()]]); }
}
