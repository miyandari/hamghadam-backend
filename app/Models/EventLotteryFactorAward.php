<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLotteryFactorAward extends Model
{
    protected $fillable = ['event_id', 'user_id', 'factor_name', 'coefficient', 'status', 'verified_by', 'verified_at', 'note'];

    protected function casts(): array
    {
        return ['coefficient' => 'decimal:3', 'verified_at' => 'datetime'];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
