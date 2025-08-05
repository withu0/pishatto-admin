<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $table = 'notification_settings';
    protected $fillable = [
        'user_id',
        'user_type',
        'setting_key',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * Get the user that owns the notification setting
     */
    public function user()
    {
        if ($this->user_type === 'guest') {
            return $this->belongsTo(Guest::class, 'user_id');
        } else {
            return $this->belongsTo(Cast::class, 'user_id');
        }
    }
} 