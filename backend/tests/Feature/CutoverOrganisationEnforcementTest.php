<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingCutoverAdminOperation;
use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverPermissionAssignment;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\ClubAccountingCutoverPackageService;
use App\Services\Finance\ClubCutoverAuthorityAdministrationService;
use App\Services\Finance\ClubCutoverAuthorityResolver;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\CutoverPostgresTestCase;
use Tests\Support\InteractsWithCutoverGovernance;

class CutoverOrganisationEnforcementTest extends CutoverPostgresTestCase
{
    use InteractsWithCutoverGovernance;

    private Club $club;
    private ClubAccountingCutoverPackage $package;
    private ClubAccountingCutoverAdminOperation $pending;
    private ClubAccountingCutoverAuthorityAppointment $appointment;
    private ClubAccountingCutoverPermissionAssignment $permission;
    private LedgerAccount $account;
    private array $actors;
    private ClubAccountingCutoverPackageService $packages;
    private ClubCutoverAuthorityAdministrationService $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->club = Club::create(['name' => 'C synthetic', 'code' => 'C-ORG', 'is_active' => true]);
        foreach (['president', 'secretary', 'treasurer', 'ca', 'operator'] as $key) { $this->actors[$key] = User::factory()->create(); }
        foreach (['president' => 'president-admin', 'secretary' => 'secretary', 'treasurer' => 'treasurer'] as $key => $role) { $this->cutoverMember($this->club, $this->actors[$key], $role); }
        $this->package = $this->bootstrapCutoverPackage($this->club, $this->actors['treasurer'], $this->actors['president'], $this->actors['secretary'], 'C-BOOTSTRAP');
        $this->packages = app(ClubAccountingCutoverPackageService::class);
        $this->admin = app(ClubCutoverAuthorityAdministrationService::class);
        $this->permission = $this->package->permissionAssignments()->firstOrFail();
        $this->appointment = $this->dualAppoint($this->package, $this->actors['ca'], 'independent_ca', $this->actors['president'], $this->actors['secretary'], 'C-APPOINTMENT');
        $this->pending = $this->admin->requestPermission($this->package, $this->actors['treasurer'], 'finance.cutover.package.validate', 'C-PENDING', $this->actors['secretary']);
        $this->account = LedgerAccount::create(['club_id' => $this->club->id, 'code' => 'C-CASH', 'name' => 'Cash', 'account_type' => 'asset', 'normal_balance' => 'debit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $this->actors['treasurer']->id]);
    }

    public static function operations(): array
    {
        return array_map(fn ($mode) => [$mode], ['schedules','evidence','validation','permission','appointment','revoke-appointment-request','revoke-permission-request','eligibility','approve','self-appointment','self-permission']);
    }

    public static function deniedOperations(): array
    {
        $cases = [];
        foreach (self::operations() as [$mode]) { foreach (['suspended','foreign','invalid'] as $state) { $cases[$mode.'-'.$state] = [$mode,$state]; } }
        return $cases;
    }

    private function invoke(string $mode): mixed
    {
        $p = $this->package; $a = $this->actors;
        return match ($mode) {
            'schedules' => $this->packages->replaceSchedules($p->club_id, $p->id, $a['treasurer']->id, $p->lock_version, [['schedule_type'=>'cash_on_hand','row_key'=>'CASH','non_sensitive_label'=>'Cash','ledger_account_id'=>$this->account->id,'fund_code'=>'GENERAL_CLUB_FUND','direction'=>'debit','amount_paise'=>100]]),
            'evidence' => $this->packages->replaceEvidenceReferences($p->club_id, $p->id, $a['treasurer']->id, $p->lock_version, $this->evidence()),
            'validation' => $this->packages->refreshValidation($p->club_id, $p->id, $a['treasurer']->id, $p->lock_version),
            'permission' => $this->admin->requestPermission($p, $a['treasurer'], 'finance.cutover.package.validate', 'C-NEW-PERMISSION', $a['secretary']),
            'appointment' => $this->admin->requestAppointment($p, $a['operator'], 'technical_operator', 'C-NEW-APPOINTMENT', $a['secretary']),
            'revoke-appointment-request' => $this->admin->requestAppointmentRevocation($this->appointment, 'C-REQUEST-REVOKE-A', 'Synthetic withdrawal.', $a['secretary']),
            'revoke-permission-request' => $this->admin->requestPermissionRevocation($this->permission, 'C-REQUEST-REVOKE-P', 'Synthetic withdrawal.', $a['secretary']),
            'eligibility' => $this->admin->requestCaEligibility($this->appointment, array_fill_keys(['professional_qualification','identity','professional_standing','engagement','independence'], 'OPAQUE-REFERENCE-001'), '2027-07-01', 'confirmed', 'C-ELIGIBILITY', $a['secretary']),
            'approve' => $this->admin->approve($this->pending, $a['president'], 'C-PENDING-P'),
            'self-appointment' => $this->admin->selfRevokeAppointment($this->appointment, $a['ca'], 'C-SELF-A', 'Synthetic holder withdrawal.'),
            'self-permission' => $this->admin->selfRevokePermission($this->permission, $a['treasurer'], 'C-SELF-P', 'Synthetic holder withdrawal.'),
        };
    }

    #[DataProvider('operations')]
    public function test_active_operations_lock_verified_club_before_scoped_resources(string $mode): void
    {
        if ($mode === 'validation') { $this->dualAssign($this->package, $this->actors['treasurer'], 'finance.cutover.package.validate', $this->actors['president'], $this->actors['secretary'], 'C-VALIDATE-GRANT'); }
        $locks = [];
        DB::listen(function (QueryExecuted $event) use (&$locks) { if (str_contains(strtolower($event->sql), 'for update')) { $locks[] = ['sql'=>$event->sql,'depth'=>DB::transactionLevel()]; } });
        $this->assertNotNull($this->invoke($mode));
        $this->assertStringContainsString('"clubs"', $locks[0]['sql']);
        foreach (array_slice($locks, 1) as $lock) { $this->assertStringContainsString('"club_id" = ?', $lock['sql']); }
        $this->assertSame([1], array_values(array_unique(array_column($locks,'depth'))));
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseCount('club_journal_entries', 0);
        fwrite(STDOUT, "\nC_LOCK_EVIDENCE ".$mode.' '.json_encode($locks, JSON_THROW_ON_ERROR)."\n");
    }

    #[DataProvider('deniedOperations')]
    public function test_all_supported_mutations_fail_closed_without_side_effects(string $mode, string $state): void
    {
        if ($state === 'suspended') { Club::whereKey($this->club->id)->update(['is_active'=>false]); }
        if ($state === 'foreign') { Club::create(['name'=>'Foreign instance','code'=>'FOREIGN','is_active'=>false]); config(['instance.organisation_code'=>'FOREIGN']); }
        if ($state === 'invalid') { config(['instance.organisation_code'=>null]); }
        $before = $this->snapshot(); $locks = [];
        DB::listen(function (QueryExecuted $event) use (&$locks) { if (str_contains(strtolower($event->sql), 'for update')) { $locks[]=$event->sql; } });
        $this->rejected(fn () => $this->invoke($mode), match($state) {'suspended'=>'ORGANISATION_SUSPENDED','foreign'=>'ORGANISATION_FOREIGN','invalid'=>'ORGANISATION_CONFIGURATION_UNAVAILABLE'});
        $this->assertSame($before, $this->snapshot());
        foreach ($locks as $sql) { $this->assertStringContainsString('"clubs"', $sql); }
    }

    public function test_request_digest_retains_supplied_versions_and_exact_stale_replay(): void
    {
        $digest = $this->pending->request_digest;
        $this->invoke('evidence');
        $this->assertSame($this->pending->id, $this->admin->requestPermission($this->package, $this->actors['treasurer'], 'finance.cutover.package.validate', 'C-PENDING', $this->actors['secretary'])->id);
        $this->assertSame($digest, $this->pending->fresh()->request_digest);
        $this->assertSame(1, $this->pending->fresh()->package_lock_version);
        $this->authorizationDenied(fn () => $this->admin->requestPermission($this->package->fresh(), $this->actors['treasurer'], 'finance.cutover.package.validate', 'C-PENDING', $this->actors['secretary']));
        $this->authorizationDenied(fn () => $this->admin->approve($this->pending, $this->actors['president'], 'STALE-P'));
        $staleNew = $this->admin->requestPermission($this->package, $this->actors['treasurer'], 'finance.cutover.package.validate', 'C-STALE-NEW', $this->actors['secretary']);
        $this->assertSame(1, $staleNew->package_lock_version);
        $this->authorizationDenied(fn () => $this->admin->approve($staleNew, $this->actors['president'], 'STALE-NEW-P'));
    }

    public function test_applied_replay_requires_eligibility_without_rebinding_or_duplicate_grants(): void
    {
        $this->admin->approve($this->pending, $this->actors['president'], 'REPLAY-P');
        $applied = $this->admin->approve($this->pending, $this->actors['secretary'], 'REPLAY-S');
        $this->invoke('evidence');
        $this->assertSame($applied->id, $this->admin->approve($applied, $this->actors['secretary'], 'IGNORED-REPLAY')->id);
        Club::whereKey($this->club->id)->update(['is_active'=>false]);
        $before=$this->snapshot();
        $this->rejected(fn () => $this->admin->approve($applied, $this->actors['secretary'], 'DENIED-REPLAY'), 'ORGANISATION_SUSPENDED');
        $this->rejected(fn () => $this->admin->requestPermission($this->package, $this->actors['treasurer'], 'finance.cutover.package.validate', 'C-PENDING', $this->actors['secretary']), 'ORGANISATION_SUSPENDED');
        $this->assertSame($before,$this->snapshot());
    }

    public function test_reactivation_does_not_restore_revoked_permission_or_appointment(): void
    {
        $this->invoke('self-permission'); $this->invoke('self-appointment');
        Club::whereKey($this->club->id)->update(['is_active'=>false]);
        $this->rejected(fn () => $this->invoke('evidence'), 'ORGANISATION_SUSPENDED');
        Club::whereKey($this->club->id)->update(['is_active'=>true]);
        $this->authorizationDenied(fn () => app(ClubCutoverAuthorityResolver::class)->permission($this->package,$this->actors['treasurer'],'finance.cutover.package.prepare'));
        $this->authorizationDenied(fn () => app(ClubCutoverAuthorityResolver::class)->appointment($this->package,$this->actors['ca'],'independent_ca'));
        $this->assertSame(1,$this->permission->events()->count());
        $this->assertSame(1,$this->appointment->events()->count());
    }

    public function test_ambient_transactions_are_rejected_without_rollback_or_audit(): void
    {
        $before=$this->snapshot(); DB::beginTransaction();
        try {
            foreach (self::operations() as [$mode]) { $this->rejected(fn () => $this->invoke($mode),'ORGANISATION_TRANSACTION_UNSUPPORTED',1); }
            $this->assertSame($before,$this->snapshot());
        } finally { DB::rollBack(); }
    }

    public function test_forged_package_operation_and_target_scope_cannot_lock_foreign_resources(): void
    {
        $foreign=Club::create(['name'=>'Foreign','code'=>'FOREIGN','is_active'=>true]);
        $other=ClubAccountingCutoverPackage::create(['club_id'=>$foreign->id,'lions_year'=>'2027-28','cutover_date'=>'2027-07-01','chart_version'=>$this->package->chart_version,'package_version'=>1,'status'=>'draft','currency'=>'INR','reconciliation_difference_paise'=>0,'activation_ready'=>false,'lock_version'=>1,'created_by_user_id'=>$this->actors['treasurer']->id,'updated_by_user_id'=>$this->actors['treasurer']->id]);
        $forged=clone $other; $forged->club_id=$this->club->id;
        foreach ([fn () => $this->admin->requestPermission($forged,$this->actors['treasurer'],'finance.cutover.package.validate','FORGED-PACKAGE',$this->actors['secretary']),fn () => $this->packages->replaceEvidenceReferences($this->club->id,$other->id,$this->actors['treasurer']->id,1,$this->evidence())] as $call) {
            $before=$this->snapshot();
            try { $call(); $this->fail('Foreign package must reject.'); }
            catch (\Illuminate\Database\Eloquent\ModelNotFoundException|\App\Services\Finance\Exceptions\CutoverPackageException) { $this->assertSame($before,$this->snapshot()); }
        }
        $target=clone $this->appointment; $target->cutover_package_id=$other->id;
        try { $this->admin->selfRevokeAppointment($target,$this->actors['ca'],'FORGED-TARGET','Synthetic.'); $this->fail('Foreign target binding must reject.'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { $this->assertSame(0,$this->appointment->events()->count()); }
    }

    public function test_package_audit_failure_rolls_back_content_versions_and_checksum(): void
    {
        $before=$this->snapshot();
        AuditLog::creating(fn () => throw new RuntimeException('Synthetic C audit failure'));
        try { $this->invoke('evidence'); $this->fail('Audit failure must abort.'); }
        catch (RuntimeException $exception) { $this->assertSame('Synthetic C audit failure',$exception->getMessage()); }
        $this->assertSame($before,$this->snapshot());
    }

    public function test_request_serialization_failure_has_one_attempt_and_no_pending_record(): void
    {
        $before=$this->snapshot(); $attempts=0;
        ClubAccountingCutoverAdminOperation::created(function () use (&$attempts) { $attempts++; DB::unprepared("DO \$\$ BEGIN RAISE EXCEPTION 'Synthetic C serialization' USING ERRCODE='40001'; END \$\$"); });
        try { $this->invoke('permission'); $this->fail('Serialization must escape.'); }
        catch (\Illuminate\Database\QueryException $exception) { $this->assertSame('40001',(string)$exception->getCode()); }
        $this->assertSame(1,$attempts); $this->assertSame($before,$this->snapshot());
    }

    public function test_second_approval_target_failure_rolls_back_approval_and_application(): void
    {
        $this->admin->approve($this->pending,$this->actors['president'],'ATOMIC-P'); $before=$this->snapshot();
        ClubAccountingCutoverPermissionAssignment::created(fn () => throw new RuntimeException('Synthetic C target failure'));
        try { $this->admin->approve($this->pending,$this->actors['secretary'],'ATOMIC-S'); $this->fail('Target failure must escape.'); }
        catch (RuntimeException $exception) { $this->assertSame('Synthetic C target failure',$exception->getMessage()); }
        $this->assertSame($before,$this->snapshot()); $this->assertSame('pending',$this->pending->fresh()->status);
    }

    #[DataProvider('raceOrders')]
    public function test_authoring_and_revocation_respect_first_owned_club_lock(string $first): void
    {
        $second=$first==='author'?'revoke':'author';
        [$one,$d1]=$this->worker($first,true); [$two,$d2]=$this->worker($second);
        $results=$this->race($one,$d1,$two,$d2);
        $this->assertSame($first==='author'?'authored':'revoked',$results[0]['outcome']);
        $this->assertSame($first==='author'?'revoked':'rejected',$results[1]['outcome']);
        $this->assertSame($first==='author'?1:0,$this->package->evidenceReferences()->count());
        $this->assertSame(1,$this->permission->events()->count());
    }
    public static function raceOrders(): array { return [['author'],['revoke']]; }

    #[DataProvider('suspensionOrders')]
    public function test_suspension_and_mutation_are_serialized_at_club(string $first): void
    {
        [$one,$d1]=$this->worker($first,true); [$two,$d2]=$this->worker($first==='suspend'?'author':'suspend');
        $results=$this->race($one,$d1,$two,$d2);
        $this->assertSame($first==='suspend'?'suspended':'authored',$results[0]['outcome']);
        $this->assertSame($first==='suspend'?'rejected':'suspended',$results[1]['outcome']);
        if ($first==='suspend') { $this->assertSame('ORGANISATION_SUSPENDED',$results[1]['reason']); }
        $this->assertFalse($this->club->fresh()->is_active);
        $this->assertSame($first==='suspend'?0:1,$this->package->evidenceReferences()->count());
    }
    public static function suspensionOrders(): array { return [['suspend'],['author']]; }

    public function test_concurrent_identical_requests_return_one_operation(): void
    {
        [$one,$d1]=$this->worker('request',true); [$two,$d2]=$this->worker('request');
        $results=$this->race($one,$d1,$two,$d2);
        $this->assertSame(['requested','requested'],array_column($results,'outcome'));
        $this->assertSame($results[0]['id'],$results[1]['id']);
        $this->assertSame(1,ClubAccountingCutoverAdminOperation::where('operation_reference','C-CONCURRENT')->count());
    }

    public function test_concurrent_distinct_approvals_apply_exactly_once(): void
    {
        [$one,$d1]=$this->worker('approve',true,$this->actors['president']->id); [$two,$d2]=$this->worker('approve',false,$this->actors['secretary']->id);
        $results=$this->race($one,$d1,$two,$d2);
        $this->assertSame(['pending','applied'],array_column($results,'outcome'));
        $this->assertSame(2,$this->pending->approvals()->count());
        $this->assertSame(1,$this->package->permissionAssignments()->whereHas('permission',fn($q)=>$q->where('slug','finance.cutover.package.validate'))->count());
    }

    private function evidence(): array { return [['evidence_type'=>'cash_count','opaque_reference'=>'C-EVIDENCE','evidence_date'=>'2027-07-01','evidence_version'=>'V1']]; }

    private function snapshot(): array
    {
        $tables=['club_accounting_cutover_admin_operations','club_accounting_cutover_admin_approvals','club_accounting_cutover_authority_appointments','club_accounting_cutover_authority_events','club_accounting_cutover_permission_assignments','club_accounting_cutover_permission_events','club_accounting_cutover_ca_eligibilities','club_accounting_cutover_schedule_rows','club_accounting_cutover_evidence_references','audit_logs','club_journal_entries'];
        $counts=[]; foreach($tables as $table){$counts[$table]=DB::table($table)->count();}
        return [$counts,$this->package->fresh()->getAttributes()];
    }

    private function rejected(callable $call,string $reason,int $depth=0): void
    {
        try { $call(); $this->fail('Eligibility must reject.'); }
        catch(OrganisationAccessRejected $exception){$this->assertSame($reason,$exception->reason);}
        $this->assertSame($depth,DB::transactionLevel());
    }
    private function authorizationDenied(callable $call): void
    {
        try{$call();$this->fail('Authority/binding must reject.');}catch(CutoverAuthorizationException){$this->assertTrue(true);}
    }

    private function worker(string $mode,bool $hold=false,?int $actor=null): array
    {
        $directory=sys_get_temp_dir().'/p02b2-c-'.bin2hex(random_bytes(8)); mkdir($directory,0700,true);
        file_put_contents($directory.'/request.json',json_encode(['mode'=>$mode,'hold'=>$hold,'club_id'=>$this->club->id,'package_id'=>$this->package->id,'operation_id'=>$this->pending->id,'target_id'=>$this->permission->id,'actor_id'=>$actor??$this->actors['treasurer']->id,'requester_id'=>$this->actors['secretary']->id,'evidence'=>$this->evidence()],JSON_THROW_ON_ERROR));
        return [new Process([PHP_BINARY,base_path('tests/Support/CutoverOrganisationRaceWorker.php'),$directory],base_path(),env:['INSTANCE_ORGANISATION_CODE'=>'C-ORG'],timeout:40),$directory];
    }
    private function race(Process $one,string $d1,Process $two,string $d2): array
    {
        try{
            $one->start();$this->awaitFile($d1.'/locked');$two->start();$this->awaitFile($d2.'/started');
            $c=DB::connection()->getConfig();$pdo=new PDO("pgsql:host={$c['host']};port={$c['port']};dbname={$c['database']}",$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $deadline=microtime(true)+15;
            do{$pdo->query('SELECT pg_stat_clear_snapshot()')->fetchColumn();$waiting=(int)$pdo->query("SELECT count(*) FROM pg_stat_activity WHERE datname=current_database() AND application_name LIKE 'p02b2-c-%' AND wait_event_type='Lock' AND cardinality(pg_blocking_pids(pid))>0")->fetchColumn();if($waiting>0){break;}usleep(5_000);}while(microtime(true)<$deadline);
            $this->assertGreaterThan(0,$waiting,'An independent PostgreSQL worker must really wait.');
            file_put_contents($d1.'/release','1');$one->wait();$two->wait();
            $results=[json_decode(file_get_contents($d1.'/result.json'),true,flags:JSON_THROW_ON_ERROR),json_decode(file_get_contents($d2.'/result.json'),true,flags:JSON_THROW_ON_ERROR)];
            foreach($results as $result){$this->assertStringContainsString('"clubs"',$result['locks'][0]);}
            fwrite(STDOUT,"\nC_RACE_EVIDENCE ".json_encode($results,JSON_THROW_ON_ERROR)."\n");
            return $results;
        }finally{file_put_contents($d1.'/release','1');if($one->isRunning()){$one->stop();}if($two->isRunning()){$two->stop();}}
    }
    private function awaitFile(string $path): void
    {
        $deadline=microtime(true)+20;while(!is_file($path)){if(microtime(true)>$deadline){throw new RuntimeException('C worker barrier timeout: '.$path);}usleep(5_000);}
    }
}
