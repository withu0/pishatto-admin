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
        'type',
        'scheduled_at',
        'location',
        'duration',
        'details',
        'created_at',
        'time', // allow mass assignment of time
        'active',
        'started_at',
        'ended_at',
        'points_earned',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }
} 