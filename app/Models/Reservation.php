<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $table = 'reservations';
    public $timestamps = false;
    protected $fillable = [
        'guest_id',
        'cast_id',
        'cast_ids',
        'type',
        'scheduled_at',
        'location',
        'address',
        'name',
        'duration',
        'details',
        'created_at',
        'time', // allow mass assignment of time
        'active',
        'started_at',
        'ended_at',
        'points_earned',
        'meeting_location',
        'reservation_name',
        'points',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'created_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'cast_ids' => 'array',
        'duration' => 'decimal:4', // Cast duration as decimal with 4 decimal places
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function cast()
    {
        return $this->belongsTo(Cast::class, 'cast_id');
    }

    public function applications()
    {
        return $this->hasMany(ReservationApplication::class);
    }

    public function pendingApplications()
    {
        return $this->hasMany(ReservationApplication::class)->where('status', 'pending');
    }

    public function approvedApplication()
    {
        return $this->hasOne(ReservationApplication::class)->where('status', 'approved');
    }

    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    /**
     * Get all feedback for this reservation.
     */
    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Get all cast sessions for this reservation.
     */
    public function castSessions()
    {
        return $this->hasMany(CastSession::class);
    }

    /**
     * Get active cast sessions for this reservation.
     */
    public function activeCastSessions()
    {
        return $this->hasMany(CastSession::class)->where('status', 'active');
    }

    /**
     * Get completed cast sessions for this reservation.
     */
    public function completedCastSessions()
    {
        return $this->hasMany(CastSession::class)->where('status', 'completed');
    }

    /**
     * Check if reservation has any active cast sessions (for group calls)
     */
    public function hasActiveCastSessions(): bool
    {
        return $this->activeCastSessions()->exists();
    }

    /**
     * Check if reservation is started (either reservation started_at or has active cast sessions)
     */
    public function isStarted(): bool
    {
        if ($this->type === 'free') {
            // For group calls, check if any cast sessions are active
            return $this->hasActiveCastSessions();
        } else {
            // For individual calls, check reservation started_at
            return !is_null($this->started_at);
        }
    }

    /**
     * Check if reservation is completed (either reservation ended_at or all cast sessions completed)
     */
    public function isCompleted(): bool
    {
        if ($this->type === 'free') {
            // For group calls, check if all cast sessions are completed
            $totalCasts = count($this->cast_ids ?? []);
            $completedSessions = $this->completedCastSessions()->count();
            return $totalCasts > 0 && $completedSessions >= $totalCasts;
        } else {
            // For individual calls, check reservation ended_at
            return !is_null($this->ended_at);
        }
    }

    /**
     * Get total points earned across all cast sessions
     */
    public function getTotalCastEarnings(): int
    {
        return $this->castSessions()->sum('points_earned') ?? 0;
    }
}
