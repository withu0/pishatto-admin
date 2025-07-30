<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Guest extends Authenticatable
{
    use HasFactory;

    protected $table = 'guests';
    protected $fillable = [
        'phone',
        'line_id',
        'nickname',
        'age',
        'shiatsu',
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
        'payjp_customer_id',
        'payment_info',
        'points',
        'identity_verification',
        'identity_verification_completed',
        'status',
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

    /**
     * Get all gifts sent by this guest.
     */
    public function sentGifts()
    {
        return $this->hasMany(\App\Models\GuestGift::class, 'sender_guest_id');
    }

    // public function gifts()
    // {
    //     return $this->hasMany(\App\Models\Gift::class, 'guest_id');
    // }

    public function favorites()
    {
        return $this->belongsToMany(Cast::class, 'guest_favorites', 'guest_id', 'cast_id')->withTimestamps();
    }

    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    /**
     * Get all feedback written by this guest.
     */
    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }
}
