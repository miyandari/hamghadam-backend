<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationGroup extends Model
{
    protected $fillable = [
        'event_id', 'leader_user_id', 'name', 'invite_code', 'max_members', 'status'
    ];

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function leader(): BelongsTo { return $this->belongsTo(User::class, 'leader_user_id'); }
    public function registrations(): HasMany { return $this->hasMany(Registration::class); }
}
