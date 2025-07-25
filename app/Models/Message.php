<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $table = 'messages';
    public $timestamps = false;
    protected $fillable = [
        'chat_id',
        'sender_guest_id',
        'sender_cast_id',
        'message',
        'image',
        'gift_id',
        'created_at',
        'is_read',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    public function guest()
    {
        return $this->belongsTo(\App\Models\Guest::class, 'sender_guest_id');
    }
    public function cast()
    {
        return $this->belongsTo(\App\Models\Cast::class, 'sender_cast_id');
    }

    public function gift()
    {
        return $this->belongsTo(\App\Models\Gift::class, 'gift_id');
    }
} 