<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubAccountingCutoverExecutionEvent;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\User;
use App\Services\Access\InstanceOrganisation;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\Exceptions\CutoverExecutionException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Audit-only capability: no callback, success writer, context or active override. */
final class CutoverExecutionAuditRecorder
{
    public function __construct(private readonly DatabaseManager $database, private readonly InstanceOrganisation $instance) {}

    public function assertStandalone(): void
    {
        $connection=$this->database->connection();
        if($connection->transactionLevel()!==0 || $connection->getPdo()->inTransaction() || $connection->getDriverName()!=='pgsql') {
            throw OrganisationAccessRejected::unsupported();
        }
    }

    public function attempt(CutoverExecutionRequest $request): void
    {
        $this->persist($request,'execution_attempt','attempted',null);
    }

    public function failure(CutoverExecutionRequest $request, Throwable $failure, bool $conflict): void
    {
        $this->persist($request,$conflict?'execution_conflict':'execution_failure','not_posted',substr(class_basename($failure),0,80));
    }

    public function indeterminate(CutoverExecutionRequest $request): void
    {
        $this->persist($request,'execution_failure','outcome_unknown','CutoverExecutionOutcomeUnknown');
    }

    private function persist(CutoverExecutionRequest $request,string $type,string $result,?string $reason): void
    {
        $this->assertStandalone();
        if(!Str::isUuid($request->idempotencyKey)) { throw new CutoverExecutionException('The cutover execution request is invalid.'); }
        $connection=$this->database->connection();
        $connection->transaction(function () use($connection,$request,$type,$result,$reason): void {
            try { $assigned=$this->instance->resolve(); }
            catch(HttpExceptionInterface $exception) {
                if($exception->getStatusCode()===503){throw OrganisationAccessRejected::unavailable($exception);}throw $exception;
            }
            if($request->clubId!==$assigned->id){throw OrganisationAccessRejected::foreign();}
            $club=Club::on($connection->getName())->whereKey($assigned->id)->lockForUpdate()->first();
            if(!$club || $club->code!==$assigned->code){throw OrganisationAccessRejected::unavailable();}
            // Deliberately no active-state waiver usable by finance: this root only appends audit.
            $package=ClubAccountingCutoverPackage::on($connection->getName())->whereKey($request->packageId)->where('club_id',$club->id)->lock('FOR KEY SHARE')->first();
            if(!$package || !User::on($connection->getName())->whereKey($request->technicalOperatorUserId)->exists()) {
                throw new CutoverExecutionException('The cutover execution request is invalid.');
            }
            ClubAccountingCutoverExecutionEvent::on($connection->getName())->create([
                'club_id'=>$club->id,'cutover_package_id'=>$package->id,'event_type'=>$type,
                'event_reference'=>(string)Str::orderedUuid(),'attempted_execution_reference'=>$request->executionReference,
                'execution_success_id'=>null,'result'=>$result,'reason_code'=>$reason,
                'actor_user_id'=>$request->technicalOperatorUserId,'recorded_at'=>now(),
            ]);
        },1);
    }
}
