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
        'created_at',
    ];
} 