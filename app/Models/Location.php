<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'prefecture',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    public function guests()
    {
        return $this->hasMany(Guest::class, 'favorite_area', 'name');
    }

    public function castMembers()
    {
        return $this->hasMany(Cast::class, 'location', 'name');
    }
} 