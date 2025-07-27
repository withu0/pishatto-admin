<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestGift extends Model
{
    use HasFactory;

    protected $table = 'guest_gifts';
    public $timestamps = false;
    protected $fillable = [
        'sender_guest_id',
        'receiver_cast_id',
        'gift_id',
        'message',
        'created_at',
    ];

    public function sender()
    {
        return $this->belongsTo(Guest::class, 'sender_guest_id');
    }

    public function receiver()
    {
        return $this->belongsTo(Cast::class, 'receiver_cast_id');
    }

    public function gift()
    {
        return $this->belongsTo(Gift::class, 'gift_id');
    }
} 