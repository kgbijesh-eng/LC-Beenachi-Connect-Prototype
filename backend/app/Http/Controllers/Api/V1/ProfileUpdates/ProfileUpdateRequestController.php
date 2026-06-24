<?php
namespace App\Http\Controllers\Api\V1\ProfileUpdates;
use App\Http\Controllers\Controller;use App\Http\Requests\ProfileUpdates\ListProfileUpdateRequestsRequest;use App\Models\{Club,Member,ProfileUpdateRequest};use App\Services\ProfileUpdates\ProfileUpdateReadService;use Illuminate\Http\JsonResponse;use Illuminate\Http\Request;
class ProfileUpdateRequestController extends Controller{
 public function ownIndex(ListProfileUpdateRequestsRequest $q,Club $club,ProfileUpdateReadService $s):JsonResponse{return response()->json($s->list($club,$q->safe()->except(['member_search','changed_field']),$this->own($q,$club)));}
 public function ownShow(Request $q,Club $club,ProfileUpdateRequest $profileUpdateRequest,ProfileUpdateReadService $s):JsonResponse{return response()->json($s->detail($club,$profileUpdateRequest,$this->own($q,$club)));}
 public function index(ListProfileUpdateRequestsRequest $q,Club $club,ProfileUpdateReadService $s):JsonResponse{return response()->json($s->list($club,$q->validated()));}
 public function show(Club $club,ProfileUpdateRequest $profileUpdateRequest,ProfileUpdateReadService $s):JsonResponse{return response()->json($s->detail($club,$profileUpdateRequest));}
 private function own(Request $q,Club $club):Member{$m=Member::where('club_id',$club->id)->where('user_id',$q->user()->id)->first();abort_unless($m,403,'A linked member profile is required.');return $m;}}
