<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cast extends Model
{
    use HasFactory;

    protected $table = 'casts';
    protected $fillable = [
        'phone',
        'line_id',
        'nickname',
        'avatar',
        'birth_year',
        'height',
        'residence',
        'birthplace',
        'profile_text',
        'created_at',
        'updated_at',
    ];

    public function reservations()
    {
        return $this->belongsToMany(Reservation::class, 'cast_reservation', 'cast_id', 'reservation_id');
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'cast_badge', 'cast_id', 'badge_id');
    }

    public function receivedGifts()
    {
        return $this->hasManyThrough(
            \App\Models\Gift::class,
            'guest_gifts',
            'cast_id', // Foreign key on guest_gifts table
            'id',      // Foreign key on gifts table
            'id',      // Local key on casts table
            'gift_id'  // Local key on guest_gifts table
        );
    }
} 