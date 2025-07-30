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
        'status',
        'name',
        'birth_year',
        'height',
        'grade',
        'grade_points',
        'residence',
        'birthplace',
        'profile_text',
        'payjp_customer_id',
        'payment_info',
        'points',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the first avatar URL
     */
    public function getFirstAvatarUrlAttribute()
    {
        if ($this->avatar) {
            $avatars = explode(',', $this->avatar);
            return '/storage/' . trim($avatars[0]);
        }
        return null;
    }

    /**
     * Get all avatar URLs
     */
    public function getAvatarUrlsAttribute()
    {
        if ($this->avatar) {
            $avatars = explode(',', $this->avatar);
            return array_map(function($path) {
                return '/storage/' . trim($path);
            }, $avatars);
        }
        return [];
    }

    /**
     * Set avatars as comma-separated string
     */
    public function setAvatarsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['avatar'] = implode(',', $value);
        } else {
            $this->attributes['avatar'] = $value;
        }
    }

    // public function chats()
    // {
    //     return $this->hasMany(Chat::class, 'cast_id', 'id');
    // }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'cast_badge', 'cast_id', 'badge_id')
                    ->withTimestamps();
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

    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }
}
