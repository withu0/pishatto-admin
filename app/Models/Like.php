<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    protected $table = 'likes';
    public $timestamps = false;
    protected $fillable = [
        'guest_id',
        'cast_id',
        'tweet_id',
        'created_at',
    ];

    public function tweet()
    {
        return $this->belongsTo(Tweet::class, 'tweet_id');
    }
    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }
    public function cast()
    {
        return $this->belongsTo(Cast::class, 'cast_id');
    }
} 