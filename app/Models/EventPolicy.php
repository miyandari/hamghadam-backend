<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPolicy extends Model
{
    protected $fillable = [
        'event_id', 'min_age', 'max_age', 'min_score',
        'max_group_size', 'requires_approval', 'lottery_enabled', 'rules'
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'lottery_enabled' => 'boolean',
            'rules' => 'array',
        ];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
}
