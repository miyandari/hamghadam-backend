<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventAlbum extends Model
{
    protected $fillable = ['event_id', 'created_by', 'title', 'description', 'visibility'];

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function media(): HasMany { return $this->hasMany(EventAlbumMedia::class, 'album_id')->orderBy('sort_order'); }
}
