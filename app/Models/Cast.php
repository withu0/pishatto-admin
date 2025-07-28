<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cast extends Authenticatable
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
        'grade',
        'grade_points',
        'residence',
        'birthplace',
        'profile_text',
        'payjp_customer_id',
        'payment_info',
        'created_at',
        'updated_at',
    ];

    // public function chats()
    // {
    //     return $this->hasMany(Chat::class, 'cast_id', 'id');
    // }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'cast_badge', 'cast_id', 'badge_id');
    }

    public function likes()
    {
        return $this->hasMany(\App\Models\Like::class, 'cast_id');
    }

    public function receivedGifts()
    {
        return $this->hasMany(\App\Models\GuestGift::class, 'receiver_cast_id');
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(Guest::class, 'guest_favorites', 'cast_id', 'guest_id')->withTimestamps();
    }
} 