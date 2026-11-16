<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    protected $fillable = [
        'event_id', 'user_id', 'registration_group_id', 'status',
        'registration_type', 'selection_method', 'total_score', 'lottery_weight', 'lottery_number', 'lottery_rank',
        'registered_at', 'confirmed_at'
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
            'lottery_weight' => 'decimal:4',
            'registered_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function group(): BelongsTo { return $this->belongsTo(RegistrationGroup::class, 'registration_group_id'); }
    public function scores(): HasMany { return $this->hasMany(RegistrationScore::class); }
    public function waitlistEntry() { return $this->hasOne(WaitlistEntry::class); }
}
