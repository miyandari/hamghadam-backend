<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'university_id', 'created_by', 'title', 'slug', 'description',
        'category', 'venue', 'starts_at', 'ends_at',
        'registration_starts_at', 'registration_ends_at',
        'capacity', 'status', 'is_group_registration_allowed'
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_starts_at' => 'datetime',
            'registration_ends_at' => 'datetime',
            'is_group_registration_allowed' => 'boolean',
        ];
    }

    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function policy(): HasOne { return $this->hasOne(EventPolicy::class); }
    public function registrations(): HasMany { return $this->hasMany(Registration::class); }
    public function groups(): HasMany { return $this->hasMany(RegistrationGroup::class); }
    public function scoringCriteria(): HasMany { return $this->hasMany(ScoringCriterion::class); }
    public function waitlistEntries(): HasMany { return $this->hasMany(WaitlistEntry::class); }
    public function feedbacks(): HasMany { return $this->hasMany(Feedback::class); }
    public function albums(): HasMany { return $this->hasMany(EventAlbum::class); }
    public function lotteryFactorAwards(): HasMany { return $this->hasMany(EventLotteryFactorAward::class); }
    public function lotteryRuns(): HasMany { return $this->hasMany(EventLotteryRun::class); }
}
