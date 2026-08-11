<?php

namespace App\Services\Reminders;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubReminder;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ClubReminderWriteService
{
    private const FIELDS = ['title','description','priority','visibility','is_all_day','due_date','due_at'];

    public function create(Club $club, User $actor, array $data): ClubReminder
    {
        $this->validateSchedule($data);
        return DB::transaction(function () use ($club,$actor,$data): ClubReminder {
            $reminder=ClubReminder::create(array_merge(Arr::only($data,self::FIELDS),[
                'club_id'=>$club->id,'status'=>ClubReminder::STATUS_DRAFT,
                'created_by_user_id'=>$actor->id,'updated_by_user_id'=>$actor->id,'lock_version'=>1,
            ]));
            $this->audit($reminder,$actor,'reminders.created',null,$reminder->fresh()->toArray());
            return $reminder->fresh()->load('club');
        });
    }

    public function update(Club $club, ClubReminder $reminder, User $actor, array $data): ClubReminder
    {
        return DB::transaction(function () use ($club,$reminder,$actor,$data): ClubReminder {
            $locked=$this->locked($club,$reminder);
            $this->version($locked,(int)$data['lock_version']);
            if (in_array($locked->status,[ClubReminder::STATUS_COMPLETED,ClubReminder::STATUS_CANCELLED],true)) {
                throw new ConflictHttpException('Terminal reminders cannot be updated.');
            }
            $merged=array_merge($locked->only(self::FIELDS),Arr::only($data,self::FIELDS));
            $this->validateSchedule($merged);
            if ($locked->status===ClubReminder::STATUS_ACTIVE) $this->validateFuture($merged,$club);
            $before=$locked->toArray();
            $locked->fill(array_merge(Arr::only($data,self::FIELDS),[
                'updated_by_user_id'=>$actor->id,'lock_version'=>$locked->lock_version+1,
            ]))->save();
            $this->audit($locked,$actor,'reminders.updated',$before,$locked->fresh()->toArray());
            return $locked->fresh()->load('club');
        });
    }

    public function activate(Club $club, ClubReminder $reminder, User $actor, int $version): ClubReminder
    {
        return $this->transition($club,$reminder,$actor,$version,'reminders.activated',function(ClubReminder $locked) use($actor,$club): array {
            if($locked->status!==ClubReminder::STATUS_DRAFT) throw new ConflictHttpException('Only Draft reminders can be activated.');
            $this->validateSchedule($locked->only(self::FIELDS));
            return ['status'=>ClubReminder::STATUS_ACTIVE,'activated_at'=>now(),'activated_by_user_id'=>$actor->id];
        });
    }

    public function complete(Club $club, ClubReminder $reminder, User $actor, int $version): ClubReminder
    {
        return $this->transition($club,$reminder,$actor,$version,'reminders.completed',function(ClubReminder $locked) use($actor): array {
            if($locked->status!==ClubReminder::STATUS_ACTIVE) throw new ConflictHttpException('Only Active reminders can be completed.');
            return ['status'=>ClubReminder::STATUS_COMPLETED,'completed_at'=>now(),'completed_by_user_id'=>$actor->id];
        });
    }

    public function cancel(Club $club, ClubReminder $reminder, User $actor, int $version, ?string $reason): ClubReminder
    {
        if($reason===null||trim($reason)==='') throw ValidationException::withMessages(['cancellation_reason'=>'The cancellation reason field is required.']);
        return $this->transition($club,$reminder,$actor,$version,'reminders.cancelled',function(ClubReminder $locked) use($actor,$reason): array {
            if(!in_array($locked->status,[ClubReminder::STATUS_DRAFT,ClubReminder::STATUS_ACTIVE],true)) throw new ConflictHttpException('Only Draft or Active reminders can be cancelled.');
            return ['status'=>ClubReminder::STATUS_CANCELLED,'cancelled_at'=>now(),'cancelled_by_user_id'=>$actor->id,'cancellation_reason'=>trim($reason)];
        });
    }

    private function transition(Club $club,ClubReminder $reminder,User $actor,int $version,string $action,callable $changes): ClubReminder
    {
        return DB::transaction(function() use($club,$reminder,$actor,$version,$action,$changes): ClubReminder {
            $locked=$this->locked($club,$reminder); $this->version($locked,$version); $before=$locked->toArray();
            $locked->fill(array_merge($changes($locked),['updated_by_user_id'=>$actor->id,'lock_version'=>$locked->lock_version+1]))->save();
            $this->audit($locked,$actor,$action,$before,$locked->fresh()->toArray());
            return $locked->fresh()->load('club');
        });
    }

    private function locked(Club $club,ClubReminder $reminder): ClubReminder
    {
        $locked=ClubReminder::where('club_id',$club->id)->whereKey($reminder->id)->lockForUpdate()->first();
        if(!$locked) abort(404,'Reminder not found.');
        return $locked;
    }

    private function version(ClubReminder $reminder,int $version): void
    {
        if((int)$reminder->lock_version!==$version) throw new ConflictHttpException('The reminder has changed. Refresh and try again.');
    }

    private function validateSchedule(array $data): void
    {
        $allDay=(bool)($data['is_all_day']??false);
        if($allDay && (empty($data['due_date'])||!empty($data['due_at']))) throw ValidationException::withMessages(['due_date'=>'All-day reminders require only due_date.']);
        if(!$allDay && (empty($data['due_at'])||!empty($data['due_date']))) throw ValidationException::withMessages(['due_at'=>'Timed reminders require only due_at.']);
    }

    private function validateFuture(array $data,Club $club): void
    {
        $future=(bool)$data['is_all_day']
            ? Carbon::parse($data['due_date'],$club->timezone)->endOfDay()->utc()->isFuture()
            : Carbon::parse($data['due_at'])->utc()->isFuture();
        if(!$future) throw ValidationException::withMessages(['due_date'=>'Active reminder due corrections must remain in the future.']);
    }

    private function audit(ClubReminder $reminder,User $actor,string $action,?array $before,array $after): void
    {
        AuditLog::create(['club_id'=>$reminder->club_id,'user_id'=>$actor->id,'action'=>$action,
            'auditable_type'=>ClubReminder::class,'auditable_id'=>$reminder->id,'metadata'=>['before'=>$before,'after'=>$after]]);
    }
}
