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
} 