<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverDecision;
use App\Models\ClubAccountingCutoverEvidenceReference;
use App\Models\ClubAccountingCutoverEvidenceVerification;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\ClubAccountingCutoverPackageService;
use App\Services\Finance\ClubCutoverEvidenceVerificationService;
use App\Services\Finance\ClubCutoverLifecycleService;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CutoverPostgresTestCase;
use Tests\Support\InteractsWithCutoverGovernance;

class CutoverLifecycleEligibilityTest extends CutoverPostgresTestCase
{
    use InteractsWithCutoverGovernance;

    private Club $club;
    private ClubAccountingCutoverPackage $package;
    private array $actors=[];

    protected function setUp(): void
    {
        parent::setUp(); $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->club=Club::create(['name'=>'D synthetic','code'=>'D-ORG','is_active'=>true]);
        foreach(['president'=>'president-admin','secretary'=>'secretary','preparer'=>'treasurer'] as $key=>$role){$this->actors[$key]=User::factory()->create();$this->cutoverMember($this->club,$this->actors[$key],$role);}
        $this->package=$this->bootstrapCutoverPackage($this->club,$this->actors['preparer'],$this->actors['president'],$this->actors['secretary'],'D-BOOTSTRAP');
    }

    public static function deniedCases(): array
    {
        $cases=[];foreach(['prepare','verify','review','approve','decide','revoke','evidence'] as $mode){foreach(['suspended','foreign','invalid'] as $state){$cases[$mode.'-'.$state]=[$mode,$state];}}return $cases;
    }

    private function invokeMutation(string $mode): mixed
    {
        $s=app(ClubCutoverLifecycleService::class);$p=$this->package;$a=$this->actors['president'];
        return match($mode){
            'prepare'=>$s->prepare($p,$a,1),'verify'=>$s->verify($p,$a,1),
            'review'=>$s->review($p,$a,1,'OPENING-BALANCE-EQUITY','D-REVIEW','2027-07-02','Synthetic accounting review rationale.'),
            'approve'=>$s->approve($p,$a,1,'2027-07-02','D-APPROVE'),
            'decide'=>$s->decide($p,$a,1,'dry_run','president','D-DECIDE','Synthetic decision.'),
            'revoke'=>$s->revoke(new ClubAccountingCutoverDecision(['id'=>999,'club_id'=>$p->club_id,'cutover_package_id'=>$p->id]),$a,'D-REVOKE','Synthetic withdrawal.'),
            'evidence'=>app(ClubCutoverEvidenceVerificationService::class)->decide($p,new ClubAccountingCutoverEvidenceReference(['id'=>999,'club_id'=>$p->club_id,'cutover_package_id'=>$p->id]),$a,'verified','D-EVIDENCE',null,1),
        };
    }

    #[DataProvider('deniedCases')]
    public function test_all_d_mutations_deny_ineligible_organisations_without_writes(string $mode,string $state): void
    {
        if($state==='suspended'){Club::whereKey($this->club->id)->update(['is_active'=>false]);}
        if($state==='foreign'){Club::create(['name'=>'Foreign synthetic','code'=>'FOREIGN','is_active'=>false]);config(['instance.organisation_code'=>'FOREIGN']);}
        if($state==='invalid'){config(['instance.organisation_code'=>null]);}
        $before=$this->package->fresh()->getAttributes();$locks=[];
        DB::listen(function(QueryExecuted $e)use(&$locks){if(str_contains(strtolower($e->sql),'for update')){$locks[]=$e->sql;}});
        try{$this->invokeMutation($mode);$this->fail('D eligibility must reject.');}catch(OrganisationAccessRejected $e){$this->assertSame(match($state){'suspended'=>'ORGANISATION_SUSPENDED','foreign'=>'ORGANISATION_FOREIGN','invalid'=>'ORGANISATION_CONFIGURATION_UNAVAILABLE'},$e->reason);}
        $this->assertSame($before,$this->package->fresh()->getAttributes());$this->assertSame(0,DB::transactionLevel());
        foreach($locks as $sql){$this->assertStringContainsString('"clubs"',$sql);}
        foreach(['club_accounting_cutover_reviews','club_accounting_cutover_approvals','club_accounting_cutover_decisions','club_accounting_cutover_evidence_verifications','club_journal_entries'] as $table){$this->assertDatabaseCount($table,0);}
    }

    public function test_unknown_ambient_transaction_is_preserved(): void
    {
        DB::beginTransaction();try{foreach(['prepare','verify','review','approve','decide','revoke','evidence'] as $mode){try{$this->invokeMutation($mode);$this->fail('Ambient D work must reject.');}catch(OrganisationAccessRejected $e){$this->assertSame('ORGANISATION_TRANSACTION_UNSUPPORTED',$e->reason);}$this->assertSame(1,DB::transactionLevel());}}finally{DB::rollBack();}
    }

    public function test_evidence_replay_remains_before_expected_lock_check_with_club_first(): void
    {
        $this->dualAssign($this->package,$this->actors['secretary'],'finance.cutover.package.verify',$this->actors['president'],$this->actors['secretary'],'D-VERIFY-GRANT');
        $p=app(ClubAccountingCutoverPackageService::class)->replaceEvidenceReferences($this->club->id,$this->package->id,$this->actors['preparer']->id,1,[['evidence_type'=>'cash_count','opaque_reference'=>'D-CASH','evidence_date'=>'2027-07-01','evidence_version'=>'V1']]);
        $evidence=$p->evidenceReferences->firstOrFail();$service=app(ClubCutoverEvidenceVerificationService::class);$locks=[];
        DB::listen(function(QueryExecuted $e)use(&$locks){if(str_contains(strtolower($e->sql),'for update')){$locks[]=['sql'=>$e->sql,'depth'=>DB::transactionLevel()];}});
        $first=$service->decide($p,$evidence,$this->actors['secretary'],'verified','D-VERIFY-REFERENCE',null,$p->lock_version);
        $replay=$service->decide($p->fresh(),$evidence->fresh(),$this->actors['secretary'],'verified','D-VERIFY-REFERENCE',null,$p->lock_version);
        $this->assertSame($first->id,$replay->id);$this->assertSame(1,ClubAccountingCutoverEvidenceVerification::count());
        $this->assertStringContainsString('"clubs"',$locks[0]['sql']);$this->assertStringContainsString('"club_id" = ?',$locks[1]['sql']);$this->assertSame([1],array_values(array_unique(array_column($locks,'depth'))));
    }
}
