<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';
    
    protected $fillable = [
        'reservation_id',
        'cast_id',
        'guest_id',
        'comment',
        'rating',
        'badge_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the reservation that this feedback belongs to.
     */
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Get the cast that this feedback is for.
     */
    public function cast()
    {
        return $this->belongsTo(Cast::class);
    }

    /**
     * Get the guest who wrote this feedback.
     */
    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * Get the badge that was given with this feedback.
     */
    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }
} 