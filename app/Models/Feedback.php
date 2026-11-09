<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $fillable = [
        'event_id', 'user_id', 'rating', 'comment', 'is_anonymous', 'is_visible'
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
