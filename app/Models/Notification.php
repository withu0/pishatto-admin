<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $fillable = [
        'user_id',
        'user_type',
        'type',
        'reservation_id',
        'cast_id',
        'message',
        'read',
    ];
} 