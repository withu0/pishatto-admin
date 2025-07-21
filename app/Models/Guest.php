<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $table = 'guests';
    protected $fillable = [
        'phone',
        'line_id',
        'nickname',
        'avatar',
        'location',
        'birth_year',
        'height',
        'residence',
        'birthplace',
        'annual_income',
        'education',
        'occupation',
        'alcohol',
        'tobacco',
        'siblings',
        'cohabitant',
        'pressure',
        'favorite_area',
        'interests',
        'created_at',
        'updated_at',
    ];
    protected $casts = [
        'interests' => 'array',
    ];

    public function reservations()
    {
        return $this->hasMany(\App\Models\Reservation::class, 'guest_id');
    }
} 