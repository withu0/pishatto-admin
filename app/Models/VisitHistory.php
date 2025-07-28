<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitHistory extends Model
{
    protected $fillable = [
        'guest_id',
        'cast_id',
        'action',
        // add other fillable fields as needed
    ];

    public function cast()
    {
        return $this->belongsTo(Cast::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
