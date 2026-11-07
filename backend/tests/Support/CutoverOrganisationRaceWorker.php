<?php

declare(strict_types=1);

use App\Models\Club;
use App\Models\ClubAccountingCutoverAdminOperation;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverPermissionAssignment;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\ClubAccountingCutoverPackageService;
use App\Services\Finance\ClubCutoverAuthorityAdministrationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Kernel::class)->bootstrap();
$directory=$argv[1];$r=json_decode(file_get_contents($directory.'/request.json'),true,flags:JSON_THROW_ON_ERROR);$locks=[];
try{
    if(env('RUN_P02B2_CUTOVER_POSTGRES')!=='1'||DB::getDriverName()!=='pgsql'||DB::connection()->getDatabaseName()!=='lcb_p02b2_cutover_test'||config('instance.organisation_code')!=='C-ORG'){throw new RuntimeException('C worker identity is not allowlisted.');}
    $safe=DB::selectOne("SELECT current_user='lc_beenachi_app' AND current_user=pg_get_userbyid(d.datdba) AND NOT r.rolsuper AND NOT r.rolcreatedb AND NOT r.rolcreaterole AND NOT r.rolreplication AND NOT r.rolbypassrls AND NOT EXISTS (SELECT 1 FROM pg_auth_members WHERE member=r.oid) AND NOT EXISTS (SELECT 1 FROM pg_database WHERE datname NOT IN ('lcb_p02b2_cutover_test','postgres','template0','template1')) AS safe FROM pg_database d JOIN pg_roles r ON r.rolname=current_user WHERE d.datname=current_database()");
    if(!in_array($safe->safe,[true,1,'1','t'],true)){throw new RuntimeException('Unsafe C worker role/inventory.');}
    DB::select("SELECT set_config('application_name',?,false)",['p02b2-c-'.$r['mode']]);
    DB::listen(function(QueryExecuted $e)use(&$locks,$directory,$r){
        if(!str_contains(strtolower($e->sql),'for update')){return;}$locks[]=$e->sql;
        if(count($locks)!==1){return;}file_put_contents($directory.'/locked','1');
        if(!$r['hold']){return;}$deadline=microtime(true)+25;
        while(!is_file($directory.'/release')){if(microtime(true)>$deadline){throw new RuntimeException('C owned lock hold timeout.');}usleep(5_000);}
    });
    file_put_contents($directory.'/started','1');$admin=$app->make(ClubCutoverAuthorityAdministrationService::class);
    if($r['mode']==='suspend'){
        DB::transaction(fn()=>Club::whereKey($r['club_id'])->where('code','C-ORG')->lockForUpdate()->firstOrFail()->update(['is_active'=>false]));$result=['outcome'=>'suspended'];
    }elseif($r['mode']==='author'){
        $record=$app->make(ClubAccountingCutoverPackageService::class)->replaceEvidenceReferences($r['club_id'],$r['package_id'],$r['actor_id'],1,$r['evidence']);$result=['outcome'=>'authored','id'=>$record->id];
    }elseif($r['mode']==='revoke'){
        $record=$admin->selfRevokePermission(ClubAccountingCutoverPermissionAssignment::findOrFail($r['target_id']),User::findOrFail($r['actor_id']),'C-RACE-REVOKE','Synthetic holder withdrawal.');$result=['outcome'=>'revoked','id'=>$record->id];
    }elseif($r['mode']==='request'){
        $record=$admin->requestPermission(ClubAccountingCutoverPackage::findOrFail($r['package_id']),User::findOrFail($r['actor_id']),'finance.cutover.package.validate','C-CONCURRENT',User::findOrFail($r['requester_id']));$result=['outcome'=>'requested','id'=>$record->id];
    }elseif($r['mode']==='approve'){
        $record=$admin->approve(ClubAccountingCutoverAdminOperation::findOrFail($r['operation_id']),User::findOrFail($r['actor_id']),'C-CONCURRENT-APPROVAL-'.$r['actor_id']);$result=['outcome'=>$record->status,'id'=>$record->id];
    }else{throw new RuntimeException('Unknown C worker mode.');}
}catch(Throwable $exception){$result=['outcome'=>'rejected','exception'=>$exception::class,'reason'=>$exception instanceof OrganisationAccessRejected?$exception->reason:$exception->getMessage()];}
$result['locks']=$locks;file_put_contents($directory.'/result.json',json_encode($result,JSON_THROW_ON_ERROR));
