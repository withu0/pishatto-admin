<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestFavorite extends Model
{
    protected $table = 'guest_favorites';
    protected $fillable = [
        'guest_id',
        'cast_id',
    ];
} 