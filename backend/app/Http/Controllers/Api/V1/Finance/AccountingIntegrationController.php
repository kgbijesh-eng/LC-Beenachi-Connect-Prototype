<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\DecideAccountingIntegrationProposalRequest;
use App\Http\Requests\Finance\StoreAccountingActivationProposalRequest;
use App\Http\Requests\Finance\StoreAccountingMappingProposalRequest;
use App\Http\Requests\Finance\StoreAccountingRevocationProposalRequest;
use App\Http\Resources\Finance\AccountingIntegrationProposalResource;
use App\Models\Club;
use App\Models\ClubAccountingIntegrationProposal;
use App\Services\Finance\AccountingIntegrationProposalService;
use App\Services\Finance\Exceptions\AccountingIntegrationConflictException;
use App\Services\Finance\Exceptions\AccountingIntegrationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingIntegrationController extends Controller
{
    public function readiness(Request $request, Club $club, AccountingIntegrationProposalService $service): JsonResponse { return $this->respond(fn () => $service->readiness($club, $request->user())); }
    public function configuration(Request $request, Club $club, AccountingIntegrationProposalService $service): JsonResponse { return $this->respond(fn () => $service->currentConfiguration($club, $request->user())); }
    public function mapping(StoreAccountingMappingProposalRequest $request, Club $club, AccountingIntegrationProposalService $service): JsonResponse { return $this->created(fn () => $service->propose($club, $request->user(), $request->validated())); }
    public function activation(StoreAccountingActivationProposalRequest $request, Club $club, AccountingIntegrationProposalService $service): JsonResponse { return $this->created(fn () => $service->propose($club, $request->user(), $request->validated())); }
    public function revocation(StoreAccountingRevocationProposalRequest $request, Club $club, AccountingIntegrationProposalService $service): JsonResponse { return $this->created(fn () => $service->propose($club, $request->user(), $request->validated())); }
    public function approve(DecideAccountingIntegrationProposalRequest $request, Club $club, ClubAccountingIntegrationProposal $proposal, AccountingIntegrationProposalService $service): JsonResponse { return $this->proposal(fn () => $service->approve($club,$request->user(),$proposal,$request->integer('lock_version'),$request->input('reason'))); }
    public function reject(DecideAccountingIntegrationProposalRequest $request, Club $club, ClubAccountingIntegrationProposal $proposal, AccountingIntegrationProposalService $service): JsonResponse { return $this->proposal(fn () => $service->reject($club,$request->user(),$proposal,$request->integer('lock_version'),$request->input('reason'))); }
    public function withdraw(DecideAccountingIntegrationProposalRequest $request, Club $club, ClubAccountingIntegrationProposal $proposal, AccountingIntegrationProposalService $service): JsonResponse { return $this->proposal(fn () => $service->withdraw($club,$request->user(),$proposal,$request->integer('lock_version'),$request->input('reason'))); }

    private function created(callable $action): JsonResponse { return $this->proposal($action, 201); }
    private function proposal(callable $action, int $status=200): JsonResponse { return $this->respond(fn () => (new AccountingIntegrationProposalResource($action()))->resolve(), $status); }
    private function respond(callable $action, int $status=200): JsonResponse
    {
        try { return response()->json(['data'=>$action()], $status); }
        catch (AuthorizationException $e) { return response()->json(['message'=>$e->getMessage()],403); }
        catch (ModelNotFoundException) { return response()->json(['message'=>'Resource not found.'],404); }
        catch (AccountingIntegrationConflictException $e) { return response()->json(['message'=>$e->getMessage()],409); }
        catch (AccountingIntegrationException $e) { return response()->json(['message'=>$e->getMessage()],422); }
    }
}
