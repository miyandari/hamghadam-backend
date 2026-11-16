<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCulturalRecord extends Model
{
    protected $fillable = [
        'user_id', 'university_id', 'record_type', 'title', 'organization_name',
        'description', 'started_on', 'ended_on', 'status', 'verified_by',
        'verified_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date', 'verified_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
