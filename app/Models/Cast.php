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
} 