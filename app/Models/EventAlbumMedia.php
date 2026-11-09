<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EventAlbumMedia extends Model
{
    protected $table = 'event_album_media';
    protected $fillable = ['album_id','uploaded_by','file_path','original_name','mime_type','size','caption','sort_order'];
    protected $appends = ['url'];
    public function getUrlAttribute(): string { return Storage::disk('public')->url($this->file_path); }
    public function album(): BelongsTo { return $this->belongsTo(EventAlbum::class, 'album_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
