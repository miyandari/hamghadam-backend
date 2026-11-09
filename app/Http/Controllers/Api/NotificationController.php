<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query=DB::table('notifications')->where('user_id',$request->user()->id)->orderByDesc('created_at');
        if ($request->boolean('unread')) $query->whereNull('read_at');
        return response()->json(['notifications'=>$query->paginate(30),'unread_count'=>DB::table('notifications')->where('user_id',$request->user()->id)->whereNull('read_at')->count()]);
    }

    public function read(Request $request, string $notification)
    {
        $updated=DB::table('notifications')->where('id',$notification)->where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now(),'updated_at'=>now()]);
        abort_unless($updated || DB::table('notifications')->where('id',$notification)->where('user_id',$request->user()->id)->exists(),404);
        return response()->json(['message'=>'اعلان خوانده شد.']);
    }

    public function readAll(Request $request)
    {
        $count=DB::table('notifications')->where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'همه اعلان‌ها خوانده شدند.','updated'=>$count]);
    }

    public function send(Request $request)
    {
        $data=$request->validate(['user_ids'=>'required|array|min:1','user_ids.*'=>'required|integer|distinct|exists:users,id','title'=>'required|string|max:255','body'=>'required|string|max:5000','data'=>'nullable|array']);
        $rows=[];
        foreach($data['user_ids'] as $id) $rows[]=['id'=>(string)Str::uuid(),'user_id'=>$id,'channel'=>'database','type'=>'App\\Notifications\\SystemMessage','data'=>json_encode(['title'=>$data['title'],'body'=>$data['body'],'extra'=>$data['data']??[]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()];
        DB::table('notifications')->insert($rows);
        return response()->json(['message'=>'اعلان ارسال شد.','recipients'=>count($rows)],201);
    }

    public function sendEvent(Request $request, Event $event)
    {
        $u=$request->user();
        abort_unless($u->is_super_admin || $u->universityRoles()->where('university_id',$event->university_id)->where('is_active',true)->whereHas('role',fn($q)=>$q->where('name','cultural_manager'))->exists(),403);
        $data=$request->validate(['title'=>'required|string|max:255','body'=>'required|string|max:5000','audience'=>'required|in:registered,confirmed,all']);
        $query=$event->registrations()->when($data['audience']==='confirmed',fn($q)=>$q->where('status','confirmed'))->when($data['audience']==='registered',fn($q)=>$q->whereIn('status',['pending','confirmed']))->select('user_id')->distinct();
        $ids=$query->pluck('user_id'); $rows=[];
        foreach($ids as $id) $rows[]=['id'=>(string)Str::uuid(),'user_id'=>$id,'channel'=>'database','type'=>'App\\Notifications\\EventMessage','data'=>json_encode(['title'=>$data['title'],'body'=>$data['body'],'event_id'=>$event->id],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()];
        if($rows) DB::table('notifications')->insert($rows);
        return response()->json(['message'=>'اعلان رویداد ارسال شد.','recipients'=>count($rows)],201);
    }
}
