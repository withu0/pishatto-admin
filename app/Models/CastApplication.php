<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CastApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_id',
        'phone_number',
        'front_image',
        'profile_image',
        'full_body_image',
        'status',
        'preliminary_notes',
        'preliminary_reviewed_at',
        'preliminary_reviewed_by',
        'final_notes',
        'final_reviewed_at',
        'final_reviewed_by',
    ];

    protected $casts = [
        'preliminary_reviewed_at' => 'datetime',
        'final_reviewed_at' => 'datetime',
    ];

    /**
     * Get the admin who reviewed this application at preliminary stage
     */
    public function preliminaryReviewer()
    {
        return $this->belongsTo(User::class, 'preliminary_reviewed_by');
    }

    /**
     * Get the admin who reviewed this application at final stage
     */
    public function finalReviewer()
    {
        return $this->belongsTo(User::class, 'final_reviewed_by');
    }

    /**
     * Get the admin who reviewed this application (for backward compatibility)
     */
    public function reviewer()
    {
        return $this->finalReviewer();
    }

    /**
     * Get the full URL for front image
     */
    public function getFrontImageUrlAttribute()
    {
        if (!$this->front_image) {
            return null;
        }

        // If it's already a full URL, return as is
        if (filter_var($this->front_image, FILTER_VALIDATE_URL)) {
            return $this->front_image;
        }

        // If it's a file path, prepend storage path
        return '/storage/' . $this->front_image;
    }

    /**
     * Get the full URL for profile image
     */
    public function getProfileImageUrlAttribute()
    {
        if (!$this->profile_image) {
            return null;
        }

        // If it's already a full URL, return as is
        if (filter_var($this->profile_image, FILTER_VALIDATE_URL)) {
            return $this->profile_image;
        }

        // If it's a file path, prepend storage path
        return '/storage/' . $this->profile_image;
    }

    /**
     * Get the full URL for full body image
     */
    public function getFullBodyImageUrlAttribute()
    {
        if (!$this->full_body_image) {
            return null;
        }

        // If it's already a full URL, return as is
        if (filter_var($this->full_body_image, FILTER_VALIDATE_URL)) {
            return $this->full_body_image;
        }

        // If it's a file path, prepend storage path
        return '/storage/' . $this->full_body_image;
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
        return $query->whereIn('status', ['preliminary_rejected', 'final_rejected']);
    }

    /**
     * Scope for preliminary passed applications
     */
    public function scopePreliminaryPassed($query)
    {
        return $query->where('status', 'preliminary_passed');
    }

    /**
     * Scope for preliminary rejected applications
     */
    public function scopePreliminaryRejected($query)
    {
        return $query->where('status', 'preliminary_rejected');
    }

    /**
     * Scope for final passed applications
     */
    public function scopeFinalPassed($query)
    {
        return $query->where('status', 'final_passed');
    }

    /**
     * Scope for final rejected applications
     */
    public function scopeFinalRejected($query)
    {
        return $query->where('status', 'final_rejected');
    }

    /**
     * Scope for applications that need final screening
     */
    public function scopeNeedsFinalScreening($query)
    {
        return $query->where('status', 'preliminary_passed');
    }
}
