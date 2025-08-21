<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CastApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_url',
        'front_image',
        'profile_image',
        'full_body_image',
        'status',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the admin who reviewed this application
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the full URL for front image
     */
    public function getFrontImageUrlAttribute()
    {
        return $this->front_image ? '/storage/' . $this->front_image : null;
    }

    /**
     * Get the full URL for profile image
     */
    public function getProfileImageUrlAttribute()
    {
        return $this->profile_image ? '/storage/' . $this->profile_image : null;
    }

    /**
     * Get the full URL for full body image
     */
    public function getFullBodyImageUrlAttribute()
    {
        return $this->full_body_image ? '/storage/' . $this->full_body_image : null;
    }

    /**
     * Scope for pending applications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved applications
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected applications
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
