<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationApplication extends Model
{
    protected $fillable = [
        'reservation_id',
        'cast_id',
        'status', // 'pending', 'approved', 'rejected'
        'applied_at',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason'
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function cast()
    {
        return $this->belongsTo(Cast::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
