<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatFavorite extends Model
{
    use HasFactory;

    protected $table = 'chat_favorites';
    public $timestamps = false;
    protected $fillable = [
        'guest_id',
        'chat_id',
        'created_at',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }
} 