<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    protected $fillable = [
        'event_id', 'user_id', 'registration_id',
        'position', 'status', 'offered_at', 'expires_at'
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function registration(): BelongsTo { return $this->belongsTo(Registration::class); }
}
