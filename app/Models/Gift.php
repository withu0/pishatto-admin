<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gift extends Model
{
    use HasFactory;

    protected $table = 'gifts';
    public $timestamps = false;
    protected $fillable = [
        'name',
        'category',
        'points',
        'icon',
        'description',
        'created_at',
    ];
} 