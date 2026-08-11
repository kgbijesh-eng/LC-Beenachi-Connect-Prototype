<?php

namespace App\Services\Reports;

use App\Models\{Club,ClubCharge,MemberCharge,MemberPayment,PaymentReceipt};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FinancialReportService
{
    public function run(Club $club,array $definition,array $filters): array
    {
        $invalid=array_diff(array_keys($filters),$definition['allowed_filters']);
        if($invalid) throw ValidationException::withMessages(['filters'=>'Unsupported filters: '.implode(', ',$invalid)]);
        return match($definition['key']) {
            'collections-summary'=>$this->collections($club,$definition,$filters),
            'member-outstanding-balances'=>$this->members($club,$definition,$filters),
            'charge-collection-status'=>$this->charges($club,$definition,$filters),
            'payment-register'=>$this->payments($club,$definition,$filters),
            'receipt-register'=>$this->receipts($club,$definition,$filters),
        };
    }

    private function obligations(Club $club,array $filters): Collection
    {
        return MemberCharge::with(['member','charge','allocations.payment'])
            ->where('club_id',$club->id)
            ->whereHas('charge',function($query) use($filters): void {
                $query->whereIn('status',['issued','closed']);
                if(isset($filters['date_from'])) $query->whereDate('due_date','>=',$filters['date_from']);
                if(isset($filters['date_to'])) $query->whereDate('due_date','<=',$filters['date_to']);
                if(isset($filters['category'])) $query->where('category',$filters['category']);
            })->orderBy('id')->get();
    }

    private function paid(MemberCharge $obligation,array $filters): int
    {
        return (int)$obligation->allocations->filter(function($allocation) use($filters): bool {
            $payment=$allocation->payment;
            if(!$payment || $payment->status!=='recorded') return false;
            if(isset($filters['date_from']) && $payment->payment_date->format('Y-m-d')<$filters['date_from']) return false;
            if(isset($filters['date_to']) && $payment->payment_date->format('Y-m-d')>$filters['date_to']) return false;
            return true;
        })->sum('amount_paise');
    }

    private function collections(Club $club,array $definition,array $filters): array
    {
        $obligations=$this->obligations($club,$filters);
        $members=$obligations->groupBy('member_id')->map(function(Collection $rows) use($filters): array {
            $assessed=(int)$rows->sum('original_amount_paise'); $paid=(int)$rows->sum(fn(MemberCharge $row)=>$this->paid($row,$filters));
            return ['assessed'=>$assessed,'paid'=>$paid,'outstanding'=>max(0,$assessed-$paid)];
        });
        $assessed=(int)$members->sum('assessed');$collected=(int)$members->sum('paid');$outstanding=(int)$members->sum('outstanding');
        $voidedQuery=$this->paymentQuery($club,$filters)->where('status','voided');
        if(isset($filters['category'])) {
            $voidedQuery->whereHas('allocations.memberCharge.charge',fn($query)=>$query->where('category',$filters['category']));
        }
        $voided=(int)$voidedQuery->sum('amount_paise');
        return $this->payload($club,$definition,$filters,[
            'currency'=>'INR','assessed_paise'=>$assessed,'assessed_display'=>$this->money($assessed),
            'collected_paise'=>$collected,'collected_display'=>$this->money($collected),
            'outstanding_paise'=>$outstanding,'outstanding_display'=>$this->money($outstanding),
            'voided_payment_paise'=>$voided,'voided_payment_display'=>$this->money($voided),
            'charged_member_count'=>$members->count(),
            'fully_paid_count'=>$members->filter(fn($row)=>$row['assessed']>0&&$row['outstanding']===0)->count(),
            'partially_paid_count'=>$members->filter(fn($row)=>$row['paid']>0&&$row['outstanding']>0)->count(),
            'unpaid_count'=>$members->filter(fn($row)=>$row['assessed']>0&&$row['paid']===0)->count(),
        ],[],null,'charge due_date and payment payment_date, inclusive');
    }

    private function members(Club $club,array $definition,array $filters): array
    {
        $rows=$this->obligations($club,$filters)->groupBy('member_id')->map(function(Collection $items) use($filters): array {
            $member=$items->first()->member;$assessed=(int)$items->sum('original_amount_paise');$paid=(int)$items->sum(fn(MemberCharge $item)=>$this->paid($item,$filters));$outstanding=max(0,$assessed-$paid);
            return ['id'=>$member->id,'member_code'=>$member->member_code,'display_name'=>$member->full_name,'assessed_paise'=>$assessed,'assessed_display'=>$this->money($assessed),'paid_paise'=>$paid,'paid_display'=>$this->money($paid),'outstanding_paise'=>$outstanding,'outstanding_display'=>$this->money($outstanding),'obligation_count'=>$items->count()];
        })->filter(function(array $row) use($filters): bool {if(!isset($filters['member_search']))return true;$needle=mb_strtolower(trim($filters['member_search']));return str_contains(mb_strtolower($row['display_name']),$needle)||str_contains(mb_strtolower($row['member_code']),$needle);})->sortBy(fn($row)=>mb_strtolower($row['display_name']).sprintf('%020d',$row['id']))->values();
        $summary=['currency'=>'INR','assessed_paise'=>(int)$rows->sum('assessed_paise'),'paid_paise'=>(int)$rows->sum('paid_paise'),'outstanding_paise'=>(int)$rows->sum('outstanding_paise'),'member_count'=>$rows->count()];
        $page=$this->paginate($rows,$filters);
        return $this->payload($club,$definition,$filters,$summary,$page->items(),$page,'charge due_date and active allocations, inclusive');
    }

    private function charges(Club $club,array $definition,array $filters): array
    {
        $query=ClubCharge::with(['memberCharges.allocations.payment'])->where('club_id',$club->id);
        isset($filters['charge_status'])?$query->where('status',$filters['charge_status']):$query->whereIn('status',['issued','closed']);
        if(isset($filters['date_from']))$query->whereDate('due_date','>=',$filters['date_from']);if(isset($filters['date_to']))$query->whereDate('due_date','<=',$filters['date_to']);if(isset($filters['category']))$query->where('category',$filters['category']);if(isset($filters['charge_search']))$query->where('title','like','%'.trim($filters['charge_search']).'%');
        $rows=$query->orderBy('due_date')->orderBy('id')->get()->map(function(ClubCharge $charge) use($filters): array {$assessed=(int)$charge->memberCharges->sum('original_amount_paise');$collected=(int)$charge->memberCharges->sum(fn(MemberCharge $row)=>$this->paid($row,$filters));return ['id'=>$charge->id,'title'=>$charge->title,'category'=>$charge->category,'status'=>$charge->status,'member_count'=>$charge->memberCharges->count(),'assessed_paise'=>$assessed,'assessed_display'=>$this->money($assessed),'collected_paise'=>$collected,'collected_display'=>$this->money($collected),'outstanding_paise'=>max(0,$assessed-$collected),'outstanding_display'=>$this->money(max(0,$assessed-$collected)),'due_date'=>$charge->due_date->format('Y-m-d')];});
        $summary=['currency'=>'INR','assessed_paise'=>(int)$rows->sum('assessed_paise'),'collected_paise'=>(int)$rows->sum('collected_paise'),'outstanding_paise'=>(int)$rows->sum('outstanding_paise'),'charge_count'=>$rows->count()];$page=$this->paginate($rows,$filters);
        return $this->payload($club,$definition,$filters,$summary,$page->items(),$page,'charge due_date, inclusive');
    }

    private function payments(Club $club,array $definition,array $filters): array
    {
        $query=$this->paymentQuery($club,$filters)->with(['member','receipt']);if(isset($filters['payment_status']))$query->where('status',$filters['payment_status']);if(isset($filters['payment_mode']))$query->where('payment_mode',$filters['payment_mode']);if(isset($filters['member_search']))$query->whereHas('member',fn($q)=>$q->where('full_name','like','%'.trim($filters['member_search']).'%')->orWhere('member_code','like','%'.trim($filters['member_search']).'%'));
        $all=$query->orderBy('payment_date')->orderBy('id')->get();$rows=$all->map(fn(MemberPayment $payment)=>['id'=>$payment->id,'member_id'=>$payment->member_id,'member_name'=>$payment->member->full_name,'payment_date'=>$payment->payment_date->format('Y-m-d'),'mode'=>$payment->payment_mode,'amount_paise'=>$payment->amount_paise,'amount_display'=>$this->money($payment->amount_paise),'status'=>$payment->status,'external_reference'=>$payment->external_reference,'receipt_id'=>$payment->receipt?->id]);$page=$this->paginate($rows,$filters);
        return $this->payload($club,$definition,$filters,['currency'=>'INR','recorded_paise'=>(int)$all->where('status','recorded')->sum('amount_paise'),'voided_paise'=>(int)$all->where('status','voided')->sum('amount_paise'),'payment_count'=>$all->count()],$page->items(),$page,'payment_date, inclusive');
    }

    private function receipts(Club $club,array $definition,array $filters): array
    {
        $query=PaymentReceipt::with(['payment.member'])->where('club_id',$club->id);if(isset($filters['date_from']))$query->whereDate('receipt_date','>=',$filters['date_from']);if(isset($filters['date_to']))$query->whereDate('receipt_date','<=',$filters['date_to']);if(isset($filters['payment_status']))$query->whereHas('payment',fn($q)=>$q->where('status',$filters['payment_status']));if(isset($filters['member_search']))$query->whereHas('payment.member',fn($q)=>$q->where('full_name','like','%'.trim($filters['member_search']).'%')->orWhere('member_code','like','%'.trim($filters['member_search']).'%'));
        $all=$query->orderBy('receipt_date')->orderBy('id')->get();$rows=$all->map(fn(PaymentReceipt $receipt)=>['id'=>$receipt->id,'receipt_number'=>$receipt->receipt_number,'payment_id'=>$receipt->member_payment_id,'member_id'=>$receipt->payment->member_id,'receipt_date'=>$receipt->receipt_date->format('Y-m-d'),'amount_paise'=>$receipt->payment->amount_paise,'amount_display'=>$this->money($receipt->payment->amount_paise),'state'=>$receipt->payment->status==='voided'?'voided':'issued']);$page=$this->paginate($rows,$filters);
        return $this->payload($club,$definition,$filters,['currency'=>'INR','issued_paise'=>(int)$all->filter(fn($r)=>$r->payment->status==='recorded')->sum(fn($r)=>$r->payment->amount_paise),'voided_paise'=>(int)$all->filter(fn($r)=>$r->payment->status==='voided')->sum(fn($r)=>$r->payment->amount_paise),'receipt_count'=>$all->count()],$page->items(),$page,'receipt_date, inclusive');
    }

    private function paymentQuery(Club $club,array $filters)
    {
        return MemberPayment::where('club_id',$club->id)->when(isset($filters['date_from']),fn($q)=>$q->whereDate('payment_date','>=',$filters['date_from']))->when(isset($filters['date_to']),fn($q)=>$q->whereDate('payment_date','<=',$filters['date_to']));
    }

    private function paginate(Collection $rows,array $filters): LengthAwarePaginator
    {
        $per=(int)($filters['per_page']??25);$page=(int)($filters['page']??1);return new LengthAwarePaginator($rows->slice(($page-1)*$per,$per)->values()->all(),$rows->count(),$per,$page);
    }

    private function payload(Club $club,array $definition,array $filters,array $summary,array $rows,?LengthAwarePaginator $page,string $basis): array
    {
        return ['data'=>['key'=>$definition['key'],'title'=>$definition['title'],'description'=>$definition['description'],'generated_at'=>now()->utc()->toISOString(),'club_timezone'=>$club->timezone,'date_basis'=>$basis,'applied_filters'=>$filters,'summary'=>$summary,'rows'=>$rows],'meta'=>$page?['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'per_page'=>$page->perPage(),'total'=>$page->total()]:null];
    }

    private function money(int $paise): string { return number_format($paise/100,2,'.',''); }
}
