<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverExecutionEvent;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\CutoverExecutionAuditRecorder;
use App\Services\Finance\CutoverExecutionRequest;
use App\Services\Finance\Exceptions\CutoverExecutionException;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CutoverPostgresTestCase;
use Tests\Support\InteractsWithCutoverGovernance;

class CutoverExecutionAuditRecorderTest extends CutoverPostgresTestCase
{
    use InteractsWithCutoverGovernance;

    private Club $club;
    private ClubAccountingCutoverPackage $package;
    private User $actor;
    private CutoverExecutionAuditRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();$this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->club=Club::create(['name'=>'Audit synthetic','code'=>'AUDIT-ORG','is_active'=>true]);
        $president=User::factory()->create();$secretary=User::factory()->create();$this->actor=User::factory()->create();
        foreach([[$president,'president-admin'],[$secretary,'secretary'],[$this->actor,'treasurer']] as [$actor,$role]){$this->cutoverMember($this->club,$actor,$role);}
        $this->package=$this->bootstrapCutoverPackage($this->club,$this->actor,$president,$secretary,'AUDIT-BOOTSTRAP');
        $this->recorder=app(CutoverExecutionAuditRecorder::class);
    }

    private function request(?int $club=null,?int $package=null): CutoverExecutionRequest
    {
        return new CutoverExecutionRequest($club??$this->club->id,$package??$this->package->id,1,str_repeat('a',64),'AUDIT-INVOCATION',(string)Str::uuid(),$this->actor->id);
    }

    public static function activities(): array{return [[true],[false]];}

    #[DataProvider('activities')]
    public function test_owned_active_and_suspended_audit_has_no_financial_capability(bool $active): void
    {
        Club::whereKey($this->club->id)->update(['is_active'=>$active]);$before=$this->package->fresh()->getAttributes();$locks=[];
        DB::listen(function(QueryExecuted $e)use(&$locks){if(str_contains(strtolower($e->sql),'for update')||str_contains(strtolower($e->sql),'for key share')){$locks[]=['sql'=>$e->sql,'depth'=>DB::transactionLevel()];}});
        $r=$this->request();$this->recorder->attempt($r);$this->recorder->failure($r,new RuntimeException('Synthetic failure'),false);$this->recorder->failure($r,new RuntimeException('Synthetic conflict'),true);$this->recorder->indeterminate($r);
        $this->assertSame(['execution_attempt','execution_failure','execution_conflict','execution_failure'],ClubAccountingCutoverExecutionEvent::orderBy('id')->pluck('event_type')->all());
        $this->assertSame(['attempted','not_posted','not_posted','outcome_unknown'],ClubAccountingCutoverExecutionEvent::orderBy('id')->pluck('result')->all());
        $this->assertStringContainsString('"clubs"',$locks[0]['sql']);$this->assertStringContainsString('FOR KEY SHARE',$locks[1]['sql']);$this->assertSame([1],array_values(array_unique(array_column($locks,'depth'))));
        $this->assertSame($before,$this->package->fresh()->getAttributes());
        foreach(['club_journal_entries','club_accounting_cutover_execution_successes','club_accounting_cutover_authority_consumptions'] as $table){$this->assertDatabaseCount($table,0);}
        $this->assertFalse(method_exists($this->recorder,'run'));$this->assertFalse(method_exists($this->recorder,'success'));
    }

    public function test_foreign_and_invalid_identity_write_no_evidence(): void
    {
        $foreign=Club::create(['name'=>'Foreign','code'=>'FOREIGN','is_active'=>false]);
        try{$this->recorder->attempt($this->request($foreign->id));$this->fail('Foreign audit must reject.');}catch(OrganisationAccessRejected $e){$this->assertSame('ORGANISATION_FOREIGN',$e->reason);$this->assertSame(404,$e->status);}
        config(['instance.organisation_code'=>null]);
        try{$this->recorder->attempt($this->request());$this->fail('Invalid identity must reject.');}catch(OrganisationAccessRejected $e){$this->assertSame(503,$e->status);}
        $this->assertDatabaseCount('club_accounting_cutover_execution_events',0);
    }

    public function test_missing_package_binding_and_invalid_uuid_do_not_create_attempts(): void
    {
        try{$this->recorder->attempt($this->request(package:999999));$this->fail('Missing owned package must reject.');}catch(CutoverExecutionException){$this->assertTrue(true);}
        $r=$this->request();$bad=new CutoverExecutionRequest($r->clubId,$r->packageId,1,$r->canonicalChecksum,$r->executionReference,'not-a-uuid',$r->technicalOperatorUserId);
        try{$this->recorder->attempt($bad);$this->fail('Invalid UUID must reject.');}catch(CutoverExecutionException){$this->assertTrue(true);}
        $this->assertDatabaseCount('club_accounting_cutover_execution_events',0);
    }

    public function test_laravel_and_native_ambient_transactions_are_never_adopted(): void
    {
        DB::beginTransaction();try{try{$this->recorder->attempt($this->request());$this->fail('Ambient root must reject.');}catch(OrganisationAccessRejected $e){$this->assertSame('ORGANISATION_TRANSACTION_UNSUPPORTED',$e->reason);}$this->assertSame(1,DB::transactionLevel());}finally{DB::rollBack();}
        $pdo=DB::connection()->getPdo();$pdo->beginTransaction();try{try{$this->recorder->attempt($this->request());$this->fail('Native root must reject.');}catch(OrganisationAccessRejected $e){$this->assertSame('ORGANISATION_TRANSACTION_UNSUPPORTED',$e->reason);}$this->assertTrue($pdo->inTransaction());}finally{$pdo->rollBack();}
        $this->assertDatabaseCount('club_accounting_cutover_execution_events',0);
    }

    public function test_failed_audit_insert_rolls_back_and_is_not_retried(): void
    {
        $calls=0;ClubAccountingCutoverExecutionEvent::creating(function()use(&$calls){$calls++;throw new RuntimeException('Synthetic audit persistence failure');});
        try{$this->recorder->attempt($this->request());$this->fail('Audit failure must escape.');}catch(RuntimeException $e){$this->assertSame('Synthetic audit persistence failure',$e->getMessage());}
        $this->assertSame(1,$calls);$this->assertSame(0,DB::transactionLevel());$this->assertDatabaseCount('club_accounting_cutover_execution_events',0);
    }
}
