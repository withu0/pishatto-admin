<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatGroup extends Model
{
    use HasFactory;

    protected $table = 'chat_groups';
    public $timestamps = false;
    protected $fillable = [
        'reservation_id',
        'cast_ids',
        'name',
        'created_at',
    ];

    protected $casts = [
        'cast_ids' => 'array',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function chats()
    {
        return $this->hasMany(Chat::class, 'group_id');
    }

    public function messages()
    {
        return $this->hasManyThrough(Message::class, Chat::class, 'group_id', 'chat_id');
    }
} 