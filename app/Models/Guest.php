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
        'stripe_customer_id',
        'payment_info',
        'points',
        'grade',
        'grade_points',
        'grade_updated_at',
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

    public function chatFavorites()
    {
        return $this->hasMany(ChatFavorite::class, 'guest_id');
    }

    public function favoritedChats()
    {
        return $this->belongsToMany(Chat::class, 'chat_favorites', 'guest_id', 'chat_id')->withPivot('created_at');
    }

    /**
     * Get concierge messages for this guest
     */
    public function conciergeMessages()
    {
        return $this->hasMany(ConciergeMessage::class, 'user_id')->where('user_type', 'guest');
    }

    /**
     * Get unread concierge messages count
     */
    public function getUnreadConciergeCountAttribute()
    {
        return $this->conciergeMessages()
            ->where('is_concierge', true)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get the avatar URL with storage path
     */
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return '/storage/' . $this->avatar;
        }
        return null;
    }

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
}
