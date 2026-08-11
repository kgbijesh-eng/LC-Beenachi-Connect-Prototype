<?php
namespace App\Http\Controllers\Api\V1\ProfileUpdates;
use App\Http\Controllers\Controller;use App\Http\Requests\ProfileUpdates\{ListProfileUpdateRequestsRequest,RejectProfileUpdateRequest,StoreProfileUpdateRequest,TransitionProfileUpdateRequest};use App\Models\{Club,Member,ProfileUpdateRequest};use App\Services\ProfileUpdates\{ProfileUpdateReadService,ProfileUpdateWriteService};use Illuminate\Http\JsonResponse;use Illuminate\Http\Request;
class ProfileUpdateRequestController extends Controller{
 public function ownIndex(ListProfileUpdateRequestsRequest $q,Club $club,ProfileUpdateReadService $s):JsonResponse{return response()->json($s->list($club,$q->safe()->except(['member_search','changed_field']),$this->own($q,$club)));}
 public function ownShow(Request $q,Club $club,ProfileUpdateRequest $profileUpdateRequest,ProfileUpdateReadService $s):JsonResponse{return response()->json($s->detail($club,$profileUpdateRequest,$this->own($q,$club)));}
 public function index(ListProfileUpdateRequestsRequest $q,Club $club,ProfileUpdateReadService $s):JsonResponse{return response()->json($s->list($club,$q->validated()));}
 public function show(Club $club,ProfileUpdateRequest $profileUpdateRequest,ProfileUpdateReadService $s):JsonResponse{return response()->json($s->detail($club,$profileUpdateRequest));}

 public function store(StoreProfileUpdateRequest $q,Club $club,ProfileUpdateWriteService $w,ProfileUpdateReadService $r):JsonResponse{$created=$w->submit($club,$q->user(),$q->validated());return response()->json($r->detail($club,$created,$created->member),201);}
 public function withdraw(TransitionProfileUpdateRequest $q,Club $club,ProfileUpdateRequest $profileUpdateRequest,ProfileUpdateWriteService $w,ProfileUpdateReadService $r):JsonResponse{$updated=$w->withdraw($club,$q->user(),$profileUpdateRequest,$q->integer('lock_version'));return response()->json($r->detail($club,$updated,$updated->member));}
 public function approve(TransitionProfileUpdateRequest $q,Club $club,ProfileUpdateRequest $profileUpdateRequest,ProfileUpdateWriteService $w,ProfileUpdateReadService $r):JsonResponse{return response()->json($r->detail($club,$w->approve($club,$q->user(),$profileUpdateRequest,$q->integer('lock_version'))));}
 public function reject(RejectProfileUpdateRequest $q,Club $club,ProfileUpdateRequest $profileUpdateRequest,ProfileUpdateWriteService $w,ProfileUpdateReadService $r):JsonResponse{return response()->json($r->detail($club,$w->reject($club,$q->user(),$profileUpdateRequest,$q->integer('lock_version'),$q->string('reason')->toString())));}
 private function own(Request $q,Club $club):Member{$m=Member::where('club_id',$club->id)->where('user_id',$q->user()->id)->first();abort_unless($m,403,'A linked member profile is required.');return $m;}}
