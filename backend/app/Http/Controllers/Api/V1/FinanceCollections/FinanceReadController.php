<?php
namespace App\Http\Controllers\Api\V1\FinanceCollections;
use App\Http\Controllers\Controller;
use App\Models\{Club,ClubCharge,Member,MemberPayment,PaymentReceipt};
use App\Services\FinanceCollections\FinanceReadService;
use Illuminate\Http\{JsonResponse,Request};
class FinanceReadController extends Controller {
    public function __construct(private FinanceReadService $service){}
    public function charges(Request $r,Club $club):JsonResponse{return response()->json($this->service->charges($club,$r->query()));}
    public function charge(Club $club,ClubCharge $charge):JsonResponse{return response()->json(['data'=>$this->service->charge($club,$charge)]);}
    public function summary(Club $club,Member $member):JsonResponse{return response()->json(['data'=>$this->service->summary($club,$member)]);}
    public function memberCharges(Request $r,Club $club,Member $member):JsonResponse{return response()->json($this->service->memberCharges($club,$member,$r->query()));}
    public function memberPayments(Request $r,Club $club,Member $member):JsonResponse{return response()->json($this->service->payments($club,$member,$r->query(),true));}
    public function payment(Club $club,MemberPayment $payment):JsonResponse{return response()->json(['data'=>$this->service->payment($club,$payment)]);}
    public function receipt(Club $club,PaymentReceipt $receipt):JsonResponse{return response()->json(['data'=>$this->service->receipt($club,$receipt)]);}
    public function ownSummary(Request $r,Club $club):JsonResponse{return response()->json(['data'=>$this->service->summary($club,$this->own($r,$club))]);}
    public function ownCharges(Request $r,Club $club):JsonResponse{return response()->json($this->service->memberCharges($club,$this->own($r,$club),$r->query()));}
    public function ownPayments(Request $r,Club $club):JsonResponse{return response()->json($this->service->payments($club,$this->own($r,$club),$r->query(),false));}
    private function own(Request $r,Club $club):Member{return Member::where('club_id',$club->id)->where('user_id',$r->user()->id)->firstOrFail();}
}
