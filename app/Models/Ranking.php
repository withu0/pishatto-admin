<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ranking extends Model
{
    use HasFactory;

    protected $table = 'rankings';
    public $timestamps = false;
    
    protected $fillable = [
        'type',
        'category',
        'user_id',
        'points',
        'period',
        'region',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relationships
    public function cast()
    {
        return $this->belongsTo(Cast::class, 'user_id');
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'user_id');
    }

    // Scopes for filtering
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPeriod($query, $period)
    {
        return $query->where('period', $period);
    }

    public function scopeByRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
} 