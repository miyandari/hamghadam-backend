<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private function authorizeEvent(Request $request, Event $event): void
    {
        $u=$request->user();
        abort_unless($u->is_super_admin || $u->universityRoles()->where('university_id',$event->university_id)->where('is_active',true)->whereHas('role',fn($q)=>$q->where('name','cultural_manager'))->exists(),403);
    }

    public function dashboard(Request $request)
    {
        $u=$request->user();
        $events=Event::query()->when(!$u->is_super_admin,fn($q)=>$q->whereIn('university_id',$u->universityRoles()->where('is_active',true)->whereHas('role',fn($r)=>$r->where('name','cultural_manager'))->select('university_id')));
        $ids=(clone $events)->select('id');
        return response()->json(['summary'=>[
            'events'=>(clone $events)->count(),
            'published_events'=>(clone $events)->where('status','published')->count(),
            'registrations'=>DB::table('registrations')->whereIn('event_id',$ids)->count(),
            'confirmed_registrations'=>DB::table('registrations')->whereIn('event_id',$ids)->where('status','confirmed')->count(),
            'waitlist'=>DB::table('waitlist_entries')->whereIn('event_id',$ids)->where('status','waiting')->count(),
            'feedback_count'=>DB::table('feedbacks')->whereIn('event_id',$ids)->count(),
            'average_rating'=>round((float)DB::table('feedbacks')->whereIn('event_id',$ids)->avg('rating'),2),
        ],'events'=>(clone $events)->withCount(['registrations','feedbacks'])->withAvg('feedbacks','rating')->latest()->paginate(20)]);
    }

    public function event(Request $request, Event $event)
    {
        $this->authorizeEvent($request,$event);
        return response()->json(['report'=>[
            'event'=>$event->load('university'),
            'registrations'=>$event->registrations()->selectRaw('status, count(*) as total')->groupBy('status')->get(),
            'scores'=>$event->registrations()->selectRaw('count(*) as count, round(avg(total_score),2) as average, max(total_score) as maximum, min(total_score) as minimum')->first(),
            'feedback'=>['count'=>$event->feedbacks()->count(),'average_rating'=>round((float)$event->feedbacks()->avg('rating'),2),'ratings'=>$event->feedbacks()->selectRaw('rating,count(*) as total')->groupBy('rating')->orderBy('rating')->get()],
            'criteria'=>$event->scoringCriteria()->withCount('registrationScores')->get(),
        ]]);
    }
}
