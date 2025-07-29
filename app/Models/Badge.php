<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use HasFactory;

    protected $table = 'badges';
    protected $fillable = [
        'name',
        'icon',
        'description',
    ];

    // Temporarily comment out the relationship to debug the issue
    /*
    public function casts()
    {
        return $this->belongsToMany(Cast::class, 'cast_badge', 'badge_id', 'cast_id')
                    ->withTimestamps();
    }
    */
}
