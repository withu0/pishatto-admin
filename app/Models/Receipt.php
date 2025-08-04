<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $table = 'receipts';
    
    protected $fillable = [
        'receipt_number',
        'user_type',
        'user_id',
        'payment_id',
        'recipient_name',
        'amount',
        'tax_amount',
        'tax_rate',
        'total_amount',
        'purpose',
        'issued_at',
        'company_name',
        'company_address',
        'company_phone',
        'registration_number',
        'status',
        'pdf_url',
        'html_content'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function cast()
    {
        return $this->belongsTo(Cast::class);
    }

    public function user()
    {
        // This is a dynamic relationship based on user_type
        // We'll handle this in the controller
        return null;
    }

    public function guestUser()
    {
        return $this->belongsTo(Guest::class, 'user_id');
    }

    public function castUser()
    {
        return $this->belongsTo(Cast::class, 'user_id');
    }
} 