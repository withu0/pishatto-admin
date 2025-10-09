<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CastSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'cast_id',
        'started_at',
        'ended_at',
        'points_earned',
        'status'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function cast()
    {
        return $this->belongsTo(Cast::class);
    }

    /**
     * Get the elapsed time in seconds for this cast session
     */
    public function getElapsedTimeAttribute(): int
    {
        if (!$this->started_at) {
            return 0;
        }

        $endTime = $this->ended_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Check if the session is currently active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->started_at && !$this->ended_at;
    }

    /**
     * Check if the session is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' && $this->started_at && $this->ended_at;
    }
}
