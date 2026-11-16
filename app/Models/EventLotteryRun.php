<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLotteryRun extends Model
{
    protected $fillable = ['event_id', 'run_by', 'candidate_count', 'winner_count', 'waitlist_count', 'result_snapshot', 'executed_at'];

    protected function casts(): array
    {
        return ['result_snapshot' => 'array', 'executed_at' => 'datetime'];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function runner(): BelongsTo { return $this->belongsTo(User::class, 'run_by'); }
}
