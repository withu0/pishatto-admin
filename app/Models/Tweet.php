<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tweet extends Model
{
    use HasFactory;

    protected $table = 'tweets';
    public $timestamps = false;
    protected $fillable = [
        'guest_id',
        'cast_id',
        'content',
        'created_at',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function cast()
    {
        return $this->belongsTo(Cast::class, 'cast_id');
    }
} 