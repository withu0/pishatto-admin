<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_id',
        'cast_id',
        'type',
        'amount',
        'reservation_id',
        'description',
        'gift_type',
        'payment_id',
        'cast_payout_id',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function cast()
    {
        return $this->belongsTo(Cast::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function castPayout()
    {
        return $this->belongsTo(CastPayout::class);
    }
} 