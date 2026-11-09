<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAlbum extends Model
{
    protected $fillable = ['event_id', 'created_by', 'title', 'description', 'visibility'];

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
