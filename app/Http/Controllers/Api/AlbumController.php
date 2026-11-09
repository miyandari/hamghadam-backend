<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAlbum;
use App\Models\EventAlbumMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AlbumController extends Controller
{
    private function authorizeEvent(Request $request, Event $event): void
    {
        $u=$request->user();
        abort_unless($u->is_super_admin || $u->universityRoles()->where('university_id',$event->university_id)->where('is_active',true)->whereHas('role',fn($q)=>$q->where('name','cultural_manager'))->exists(),403);
    }

    public function index(Request $request, Event $event)
    {
        $u=$request->user();
        $manager=$u && ($u->is_super_admin || $u->universityRoles()->where('university_id',$event->university_id)->where('is_active',true)->whereHas('role',fn($q)=>$q->where('name','cultural_manager'))->exists());
        $participant=$u && $event->registrations()->where('user_id',$u->id)->whereIn('status',['pending','confirmed'])->exists();
        $query=$event->albums()->with('media','creator:id,name');
        if(!$manager) $query->where(function($q) use($participant){$q->where('visibility','public'); if($participant)$q->orWhere('visibility','participants');});
        return response()->json(['albums'=>$query->latest()->paginate(20)]);
    }

    public function store(Request $request, Event $event)
    {
        $this->authorizeEvent($request,$event);
        $data=$request->validate(['title'=>'required|string|max:255','description'=>'nullable|string','visibility'=>'sometimes|in:public,participants,private']);
        $album=$event->albums()->create([...$data,'created_by'=>$request->user()->id]);
        return response()->json(['album'=>$album],201);
    }

    public function upload(Request $request, Event $event, EventAlbum $album)
    {
        $this->authorizeEvent($request,$event); abort_unless($album->event_id===$event->id,404);
        $data=$request->validate(['files'=>'required|array|min:1|max:20','files.*'=>'required|image|mimes:jpg,jpeg,png,webp|max:10240','captions'=>'nullable|array','captions.*'=>'nullable|string|max:1000']);
        $created=[];
        foreach($request->file('files') as $i=>$file){$path=$file->store("events/{$event->id}/albums/{$album->id}",'public');$created[]=$album->media()->create(['uploaded_by'=>$request->user()->id,'file_path'=>$path,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),'caption'=>$data['captions'][$i]??null]);}
        return response()->json(['message'=>'تصاویر بارگذاری شدند.','media'=>$created],201);
    }

    public function destroyMedia(Request $request, Event $event, EventAlbum $album, EventAlbumMedia $media)
    {
        $this->authorizeEvent($request,$event); abort_unless($album->event_id===$event->id && $media->album_id===$album->id,404);
        Storage::disk('public')->delete($media->file_path); $media->delete();
        return response()->json(['message'=>'تصویر حذف شد.']);
    }

    public function destroy(Request $request, Event $event, EventAlbum $album)
    {
        $this->authorizeEvent($request,$event); abort_unless($album->event_id===$event->id,404);
        foreach($album->media as $media) Storage::disk('public')->delete($media->file_path);
        $album->delete(); return response()->json(['message'=>'آلبوم حذف شد.']);
    }
}
