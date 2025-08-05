<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Message;

class Chat extends Model
{
    use HasFactory;

    protected $table = 'chats';
    public $timestamps = false;
    protected $fillable = [
        'guest_id',
        'cast_id',
        'reservation_id',
        'group_id',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function cast()
    {
        return $this->belongsTo(Cast::class, 'cast_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'chat_id');
    }

    public function reservation()
    {
        return $this->belongsTo(\App\Models\Reservation::class, 'reservation_id');
    }

    public function group()
    {
        return $this->belongsTo(ChatGroup::class, 'group_id');
    }

    public function favorites()
    {
        return $this->hasMany(ChatFavorite::class, 'chat_id');
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(Guest::class, 'chat_favorites', 'chat_id', 'guest_id')->withTimestamps();
    }
} 