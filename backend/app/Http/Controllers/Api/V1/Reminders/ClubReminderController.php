<?php

namespace App\Http\Controllers\Api\V1\Reminders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reminders\ListClubRemindersRequest;
use App\Http\Requests\Reminders\StoreClubReminderRequest;
use App\Http\Requests\Reminders\TransitionClubReminderRequest;
use App\Http\Requests\Reminders\UpdateClubReminderRequest;
use App\Http\Resources\Reminders\ClubReminderResource;
use App\Models\Club;
use App\Models\ClubReminder;
use App\Models\User;
use App\Services\Reminders\ClubReminderReadService;
use App\Services\Reminders\ClubReminderWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubReminderController extends Controller
{
    public function index(ListClubRemindersRequest $request, Club $club, ClubReminderReadService $service): JsonResponse
    {
        $management=$this->canManage($request->user(),$club);
        if(!$management&&array_key_exists('visibility',$request->validated())) return response()->json(['message'=>'This action is unauthorized.'],403);
        $request->attributes->set('reminders_management_reader',$management);
        $page=$service->listForClub($club,$request->validated(),$management);
        return response()->json(['data'=>ClubReminderResource::collection($page->items())->resolve($request),'meta'=>[
            'current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'per_page'=>$page->perPage(),'total'=>$page->total(),
        ]]);
    }

    public function show(Request $request,Club $club,ClubReminder $reminder,ClubReminderReadService $service): JsonResponse
    {
        if((int)$reminder->club_id!==(int)$club->id) return response()->json(['message'=>'Reminder not found.'],404);
        $reminder->load('club'); $management=$this->canManage($request->user(),$club);
        if(!$service->canRead($reminder,$management)) return response()->json(['message'=>'Reminder not found.'],404);
        return $this->resource($request,$reminder,$management);
    }

    public function store(StoreClubReminderRequest $request,Club $club,ClubReminderWriteService $service): JsonResponse
    {
        $reminder=$service->create($club,$request->user(),$request->validated());
        return $this->resource($request,$reminder,true,201);
    }

    public function update(UpdateClubReminderRequest $request,Club $club,ClubReminder $reminder,ClubReminderWriteService $service): JsonResponse
    {
        $reminder=$service->update($club,$reminder,$request->user(),$request->validated());
        return $this->resource($request,$reminder,true);
    }

    public function activate(TransitionClubReminderRequest $request,Club $club,ClubReminder $reminder,ClubReminderWriteService $service): JsonResponse
    {
        $reminder=$service->activate($club,$reminder,$request->user(),(int)$request->validated('lock_version'));
        return $this->resource($request,$reminder,true);
    }

    public function complete(TransitionClubReminderRequest $request,Club $club,ClubReminder $reminder,ClubReminderWriteService $service): JsonResponse
    {
        $reminder=$service->complete($club,$reminder,$request->user(),(int)$request->validated('lock_version'));
        return $this->resource($request,$reminder,true);
    }

    public function cancel(TransitionClubReminderRequest $request,Club $club,ClubReminder $reminder,ClubReminderWriteService $service): JsonResponse
    {
        $data=$request->validated();
        $reminder=$service->cancel($club,$reminder,$request->user(),(int)$data['lock_version'],$data['cancellation_reason']??null);
        return $this->resource($request,$reminder,true);
    }

    private function resource(Request $request,ClubReminder $reminder,bool $management,int $status=200): JsonResponse
    {
        $request->attributes->set('reminders_management_reader',$management);
        return response()->json(['data'=>(new ClubReminderResource($reminder))->resolve($request)],$status);
    }

    private function canManage(User $user,Club $club): bool
    {
        $membership=$user->clubMemberships()->with('role.permissions')->where('club_id',$club->id)->where('status','active')->first();
        return $membership?->role!==null&&in_array($membership->role->slug,ClubReminder::MANAGEMENT_ROLE_SLUGS,true)
            &&$membership->role->permissions->contains('slug',ClubReminder::PERMISSION_MANAGE);
    }
}