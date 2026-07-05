<?php

namespace App\Services\Reports;

use App\Models\Club;
use App\Models\ClubEvent;
use App\Models\ClubProject;
use App\Models\ClubReminder;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ClubReportService
{
    public function run(Club $club, array $definition, array $filters): array
    {
        $invalid = array_diff(array_keys($filters), $definition['allowed_filters']);
        if ($invalid) throw ValidationException::withMessages(['filters' => 'Unsupported filters: '.implode(', ', $invalid)]);
        return match ($definition['key']) {
            'membership-summary' => $this->membership($club,$definition,$filters),
            'events-register' => $this->events($club,$definition,$filters),
            'projects-register' => $this->projects($club,$definition,$filters),
            'reminders-register' => $this->reminders($club,$definition,$filters),
        };
    }

    private function membership(Club $club,array $d,array $f): array
    {
        $q=Member::where('club_id',$club->id); if(isset($f['status'])) $q->where('status',$f['status']);
        $dist=(clone $q)->selectRaw('status,count(*) total')->groupBy('status')->pluck('total','status')->map(fn($v)=>(int)$v)->all();
        return $this->payload($club,$d,$f,['total_members'=>(clone $q)->count(),'active_members'=>$dist['active']??0,'inactive_members'=>$dist['inactive']??0,'status_distribution'=>$dist],[],null,'member status stored in members');
    }

    private function events(Club $club,array $d,array $f): array
    {
        $q=ClubEvent::where('club_id',$club->id); foreach(['status','type'] as $x) if(isset($f[$x])) $q->where($x,$f[$x]); if(isset($f['scope'])) $q->period($f['scope'], Carbon::now('UTC'));
        if(isset($f['q'])) $q->where('title','like','%'.trim($f['q']).'%'); $this->dates($q,$club,$f,'starts_at');
        $p=$q->orderBy('starts_at')->orderBy('id')->paginate($f['per_page']??25,['*'],'page',$f['page']??1);
        $rows=collect($p->items())->map(fn(ClubEvent $e)=>['id'=>$e->id,'type'=>$e->type,'title'=>$e->title,'status'=>$e->status,'visibility'=>$e->visibility,'is_all_day'=>$e->is_all_day,'scheduled_at'=>$e->starts_at?->toISOString(),'scheduled_date'=>$e->start_date?->format('Y-m-d'),'venue'=>$e->venue])->all();
        return $this->payload($club,$d,$f,['total_records'=>$p->total()],$rows,$p,'effective end (ends_at or starts_at) UTC period; club-timezone inclusive start-date filters');
    }

    private function projects(Club $club,array $d,array $f): array
    {
        $q=ClubProject::where('club_id',$club->id); foreach(['status','record_type','service_category'] as $x) if(isset($f[$x])) $q->where($x,$f[$x]);
        if(isset($f['q'])) $q->where('title','like','%'.trim($f['q']).'%'); $q->effectiveStartBetween($f['date_from']??null,$f['date_to']??null);
        $p=$q->orderByRaw('coalesce(actual_start_date, planned_start_date)')->orderBy('id')->paginate($f['per_page']??25,['*'],'page',$f['page']??1);
        $rows=collect($p->items())->map(fn(ClubProject $r)=>['id'=>$r->id,'record_type'=>$r->record_type,'title'=>$r->title,'status'=>$r->status,'service_category'=>$r->service_category,'planned_start_date'=>$r->planned_start_date?->format('Y-m-d'),'planned_end_date'=>$r->planned_end_date?->format('Y-m-d'),'actual_start_date'=>$r->actual_start_date?->format('Y-m-d'),'actual_end_date'=>$r->actual_end_date?->format('Y-m-d'),'expected_beneficiary_count'=>$r->expected_beneficiary_count,'actual_beneficiary_count'=>$r->actual_beneficiary_count])->all();
        return $this->payload($club,$d,$f,['total_records'=>$p->total()],$rows,$p,'effective start date (actual_start_date or planned_start_date) inclusive date-only basis');
    }

    private function reminders(Club $club,array $d,array $f): array
    {
        $q=ClubReminder::with('club')->where('club_id',$club->id); foreach(['status','priority','is_all_day'] as $x) if(array_key_exists($x,$f)) $q->where($x,$f[$x]);
        if(isset($f['q'])) $q->where('title','like','%'.trim($f['q']).'%'); $this->reminderDates($q,$club,$f);
        $p=$q->orderByRaw('coalesce(due_at,due_date) asc')->orderBy('id')->paginate($f['per_page']??25,['*'],'page',$f['page']??1);
        $rows=collect($p->items())->map(fn(ClubReminder $r)=>['id'=>$r->id,'title'=>$r->title,'priority'=>$r->priority,'status'=>$r->status,'visibility'=>$r->visibility,'is_all_day'=>$r->is_all_day,'due_date'=>$r->due_date?->format('Y-m-d'),'due_at'=>$r->due_at?->toISOString(),'overdue'=>$r->isOverdue()])->all();
        return $this->payload($club,$d,$f,['total_records'=>$p->total(),'overdue_records'=>collect($rows)->where('overdue',true)->count()],$rows,$p,'all-day club date or timed UTC due_at');
    }

    private function payload(Club $c,array $d,array $f,array $s,array $rows,mixed $p,string $basis): array
    {
        return ['data'=>['key'=>$d['key'],'title'=>$d['title'],'description'=>$d['description'],'generated_at'=>now()->utc()->toISOString(),'club_timezone'=>$c->timezone,'date_basis'=>$basis,'applied_filters'=>$f,'summary'=>$s,'rows'=>$rows],'meta'=>$p?['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'per_page'=>$p->perPage(),'total'=>$p->total()]:null];
    }

    private function dates(Builder $q,Club $c,array $f,string $column): void
    {
        $tz=$c->timezone?:'UTC'; if(isset($f['date_from'])) $q->where($column,'>=',Carbon::createFromFormat('Y-m-d',$f['date_from'],$tz)->startOfDay()->utc()); if(isset($f['date_to'])) $q->where($column,'<=',Carbon::createFromFormat('Y-m-d',$f['date_to'],$tz)->endOfDay()->utc());
    }

    private function reminderDates(Builder $q,Club $c,array $f): void
    {
        $tz=$c->timezone?:'UTC';
        if(isset($f['date_from'])) { $u=Carbon::createFromFormat('Y-m-d',$f['date_from'],$tz)->startOfDay()->utc(); $q->where(fn(Builder $x)=>$x->whereDate('due_date','>=',$f['date_from'])->orWhere('due_at','>=',$u)); }
        if(isset($f['date_to'])) { $u=Carbon::createFromFormat('Y-m-d',$f['date_to'],$tz)->endOfDay()->utc(); $q->where(fn(Builder $x)=>$x->whereDate('due_date','<=',$f['date_to'])->orWhere('due_at','<=',$u)); }
    }
}
