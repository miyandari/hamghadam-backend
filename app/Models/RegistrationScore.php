<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationScore extends Model
{
    protected $fillable = [
        'registration_id', 'scoring_criterion_id', 'evaluator_id', 'score', 'note'
    ];

    protected function casts(): array
    {
        return ['score' => 'decimal:2'];
    }

    public function registration(): BelongsTo { return $this->belongsTo(Registration::class); }
    public function criterion(): BelongsTo { return $this->belongsTo(ScoringCriterion::class, 'scoring_criterion_id'); }
    public function evaluator(): BelongsTo { return $this->belongsTo(User::class, 'evaluator_id'); }
}
