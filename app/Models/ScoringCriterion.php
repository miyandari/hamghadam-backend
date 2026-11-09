<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoringCriterion extends Model
{
    protected $fillable = ['event_id', 'name', 'description', 'weight', 'max_score', 'is_active'];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'max_score' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function registrationScores(): HasMany { return $this->hasMany(RegistrationScore::class); }
}
