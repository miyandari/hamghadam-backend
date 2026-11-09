<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Feedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request, Event $event)
    {
        abort_unless($event->status==='completed',422,'ثبت بازخورد پس از پایان رویداد امکان‌پذیر است.');
        abort_unless($event->registrations()->where('user_id',$request->user()->id)->where('status','confirmed')->exists(),403,'فقط شرکت‌کنندگان تأییدشده امکان ثبت بازخورد دارند.');
        $data=$request->validate(['rating'=>'required|integer|min:1|max:5','comment'=>'nullable|string|max:5000','is_anonymous'=>'sometimes|boolean']);
        $feedback=Feedback::updateOrCreate(['event_id'=>$event->id,'user_id'=>$request->user()->id],[...$data,'is_visible'=>false]);
        return response()->json(['message'=>'بازخورد ثبت شد و پس از بررسی نمایش داده می‌شود.','feedback'=>$feedback],201);
    }

    public function moderate(Request $request, Event $event, Feedback $feedback)
    {
        $u=$request->user(); abort_unless($u->is_super_admin || $u->universityRoles()->where('university_id',$event->university_id)->where('is_active',true)->whereHas('role',fn($q)=>$q->where('name','cultural_manager'))->exists(),403);
        abort_unless($feedback->event_id===$event->id,404);
        $data=$request->validate(['is_visible'=>'required|boolean']); $feedback->update($data);
        return response()->json(['feedback'=>$feedback->fresh()]);
    }

    public function publicIndex(Event $event)
    {
        return response()->json(['feedbacks'=>$event->feedbacks()->where('is_visible',true)->latest()->paginate(20)->through(fn($f)=>['id'=>$f->id,'rating'=>$f->rating,'comment'=>$f->comment,'created_at'=>$f->created_at,'user'=>$f->is_anonymous?null:$f->user()->select('id','name')->first()])]);
    }
}
