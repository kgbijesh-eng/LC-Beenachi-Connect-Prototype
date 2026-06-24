<?php
namespace App\Services\FinanceCollections;
use App\Models\{Club,ClubCharge,Member,MemberCharge,MemberPayment,PaymentReceipt};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class FinanceReadService {
    public function summary(Club $club,Member $member):array {
        $this->owned($club,$member); $charges=MemberCharge::where('club_id',$club->id)->where('member_id',$member->id)->get();
        $assessed=(int)$charges->sum('original_amount_paise'); $paid=(int)$charges->sum(fn($c)=>$c->allocatedPaise()); $out=max(0,$assessed-$paid);
        return ['member_id'=>$member->id,'currency'=>'INR','assessed_paise'=>$assessed,'assessed_display'=>$this->money($assessed),'paid_paise'=>$paid,'paid_display'=>$this->money($paid),'outstanding_paise'=>$out,'outstanding_display'=>$this->money($out),'open_obligations'=>$charges->filter(fn($c)=>$c->outstandingPaise()>0)->count()];
    }
    public function charges(Club $club,array $f):array {
        $this->filters($f,['status','category','financial_year','due_from','due_to','q','page','per_page']);
        $q=ClubCharge::where('club_id',$club->id); foreach(['status','category','financial_year'] as $x)if(isset($f[$x]))$q->where($x,$f[$x]);
        if(isset($f['due_from']))$q->whereDate('due_date','>=',$f['due_from']); if(isset($f['due_to']))$q->whereDate('due_date','<=',$f['due_to']);
        if(isset($f['q']))$q->where('title','like','%'.trim($f['q']).'%'); $p=$q->orderBy('due_date')->orderBy('id')->paginate($f['per_page']??25,['*'],'page',$f['page']??1);
        return $this->page($p,fn($r)=>$this->chargeRow($r));
    }
    public function charge(Club $club,ClubCharge $charge):array { if($charge->club_id!==$club->id)abort(404); return $this->chargeRow($charge,true); }
    public function memberCharges(Club $club,Member $member,array $f):array {
        $this->owned($club,$member); $this->filters($f,['state','status','category','financial_year','due_from','due_to','page','per_page']);
        $q=MemberCharge::with('charge')->where('club_id',$club->id)->where('member_id',$member->id);
        foreach(['status','category','financial_year'] as $x)if(isset($f[$x]))$q->whereHas('charge',fn($z)=>$z->where($x,$f[$x]));
        if(isset($f['due_from']))$q->whereHas('charge',fn($z)=>$z->whereDate('due_date','>=',$f['due_from']));
        if(isset($f['due_to']))$q->whereHas('charge',fn($z)=>$z->whereDate('due_date','<=',$f['due_to']));
        $rows=$q->orderBy('id')->get()->map(fn($r)=>$this->memberChargeRow($r))->when(isset($f['state']),fn($c)=>$c->where('payment_state',$f['state']))->values();
        return $this->manualPage($rows,(int)($f['page']??1),(int)($f['per_page']??25));
    }
    public function payments(Club $club,Member $member,array $f,bool $management=false):array {
        $this->owned($club,$member); $this->filters($f,['status','mode','date_from','date_to','page','per_page']);
        $q=MemberPayment::with(['allocations','receipt'])->where('club_id',$club->id)->where('member_id',$member->id);
        if(isset($f['status']))$q->where('status',$f['status']); if(isset($f['mode']))$q->where('payment_mode',$f['mode']);
        if(isset($f['date_from']))$q->whereDate('payment_date','>=',$f['date_from']); if(isset($f['date_to']))$q->whereDate('payment_date','<=',$f['date_to']);
        $p=$q->orderByDesc('payment_date')->orderByDesc('id')->paginate($f['per_page']??25,['*'],'page',$f['page']??1);
        return $this->page($p,fn($r)=>$this->paymentRow($r,$management));
    }
    public function payment(Club $club,MemberPayment $payment):array {if($payment->club_id!==$club->id)abort(404);return $this->paymentRow($payment->load(['allocations','receipt']),true);}
    public function receipt(Club $club,PaymentReceipt $receipt):array {if($receipt->club_id!==$club->id)abort(404);$receipt->load('payment');return ['id'=>$receipt->id,'payment_id'=>$receipt->member_payment_id,'member_id'=>$receipt->payment->member_id,'receipt_number'=>$receipt->receipt_number,'receipt_date'=>$receipt->receipt_date->format('Y-m-d'),'financial_year'=>$receipt->financial_year,'status'=>$receipt->status,'amount_paise'=>$receipt->payment->amount_paise,'amount_display'=>$this->money($receipt->payment->amount_paise),'currency'=>'INR'];}
    private function chargeRow(ClubCharge $r,bool $detail=false):array { $a=['id'=>$r->id,'title'=>$r->title,'category'=>$r->category,'amount_paise'=>$r->amount_paise,'amount_display'=>$this->money($r->amount_paise),'currency'=>'INR','due_date'=>$r->due_date->format('Y-m-d'),'financial_year'=>$r->financial_year,'status'=>$r->status,'lock_version'=>$r->lock_version]; if($detail)$a+=['description'=>$r->description,'member_ids'=>$r->assignment_member_ids];return $a;}
    private function memberChargeRow(MemberCharge $r):array{$paid=$r->allocatedPaise();$out=$r->outstandingPaise();return ['id'=>$r->id,'club_charge_id'=>$r->club_charge_id,'member_id'=>$r->member_id,'title'=>$r->charge->title,'category'=>$r->charge->category,'status'=>$r->charge->status,'financial_year'=>$r->charge->financial_year,'due_date'=>$r->charge->due_date->format('Y-m-d'),'original_amount_paise'=>$r->original_amount_paise,'allocated_paise'=>$paid,'outstanding_paise'=>$out,'outstanding_display'=>$this->money($out),'currency'=>'INR','payment_state'=>$r->paymentState(),'lock_version'=>$r->lock_version];}
    private function paymentRow(MemberPayment $r,bool $management):array{$a=['id'=>$r->id,'member_id'=>$r->member_id,'amount_paise'=>$r->amount_paise,'amount_display'=>$this->money($r->amount_paise),'currency'=>'INR','payment_date'=>$r->payment_date->format('Y-m-d'),'payment_mode'=>$r->payment_mode,'external_reference'=>$r->external_reference,'status'=>$r->status,'allocations'=>$r->allocations->map(fn($x)=>['id'=>$x->id,'member_charge_id'=>$x->member_charge_id,'amount_paise'=>$x->amount_paise])->values(),'receipt'=>$r->receipt?['id'=>$r->receipt->id,'receipt_number'=>$r->receipt->receipt_number,'status'=>$r->receipt->status]:null,'replacement_payment_id'=>$r->replacement_payment_id,'lock_version'=>$r->lock_version];if($management)$a+=['idempotency_key'=>$r->idempotency_key,'internal_note'=>$r->internal_note,'void_reason'=>$r->void_reason,'recorded_by_user_id'=>$r->recorded_by_user_id];return $a;}
    private function owned(Club $c,Member $m):void{if($m->club_id!==$c->id)abort(404);}
    private function filters(array $f,array $allowed):void{$bad=array_diff(array_keys($f),$allowed);if($bad)throw ValidationException::withMessages(['filters'=>'Unsupported filters: '.implode(', ',$bad)]);if(($f['per_page']??25)>100)throw ValidationException::withMessages(['per_page'=>'Maximum is 100.']);}
    private function page($p,callable $map):array{return ['data'=>collect($p->items())->map($map)->values(),'meta'=>['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'per_page'=>$p->perPage(),'total'=>$p->total()]];}
    private function manualPage($rows,int $page,int $per):array{$per=min(100,max(1,$per));$total=$rows->count();return ['data'=>$rows->slice(($page-1)*$per,$per)->values(),'meta'=>['current_page'=>$page,'last_page'=>max(1,(int)ceil($total/$per)),'per_page'=>$per,'total'=>$total]];}
    private function money(int $p):string{return sprintf('%d.%02d',intdiv($p,100),abs($p%100));}
}
