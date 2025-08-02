<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'amount',
        'status',
        'payment_method',
        'payjp_charge_id',
        'payjp_customer_id',
        'payjp_token',
        'description',
        'metadata',
        'paid_at',
        'failed_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'user_id');
    }

    public function cast()
    {
        return $this->belongsTo(Cast::class, 'user_id');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }
} 