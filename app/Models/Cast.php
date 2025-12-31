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
        'location',
        'profile_text',
        'payjp_customer_id',
        'stripe_customer_id',
        'stripe_connect_account_id',
        'stripe_onboarding_status',
        'stripe_requirements',
        'payouts_enabled',
        'stripe_connect_last_synced_at',
        'stripe_payouts_enabled_at',
        'payment_info',
        'points',
        'category',
        'bank_name',
        'branch_name',
        'account_type',
        'account_number',
        'account_holder_name',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'stripe_requirements' => 'array',
        'payouts_enabled' => 'boolean',
        'stripe_connect_last_synced_at' => 'datetime',
        'stripe_payouts_enabled_at' => 'datetime',
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

    /**
     * Get the category points based on the cast's category
     */
    public function getCategoryPointsAttribute()
    {
        switch ($this->category) {
            case 'プレミアム':
                return 12000;
            case 'VIP':
                return 15000;
            case 'ロイヤルVIP':
                return 18000;
            default:
                return 12000; // Default to プレミアム
        }
    }

    // public function chats()
    // {
    //     return $this->hasMany(Chat::class, 'cast_id', 'id');
    // }

    public function badges()
    {
        // Get badges from feedback table with counts
        return $this->hasManyThrough(
            Badge::class,
            Feedback::class,
            'cast_id', // Foreign key on feedback table
            'id', // Foreign key on badges table
            'id', // Local key on casts table
            'badge_id' // Local key on feedback table
        );
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

    public function payouts()
    {
        return $this->hasMany(CastPayout::class);
    }

    /**
     * Get concierge messages for this cast
     */
    public function conciergeMessages()
    {
        return $this->hasMany(ConciergeMessage::class, 'user_id')->where('user_type', 'cast');
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
     * Get reservation applications for this cast
     */
    public function reservationApplications()
    {
        return $this->hasMany(ReservationApplication::class);
    }
}
