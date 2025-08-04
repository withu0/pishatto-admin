<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ConciergeMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'message',
        'is_concierge',
        'is_read',
        'message_type',
        'category',
        'status',
        'admin_notes',
        'assigned_admin_id',
        'resolved_at',
        'user_agent',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'is_concierge' => 'boolean',
        'is_read' => 'boolean',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user (guest or cast) associated with this message
     */
    public function user()
    {
        if ($this->user_type === 'guest') {
            return $this->belongsTo(Guest::class, 'user_id');
        } else {
            return $this->belongsTo(Cast::class, 'user_id');
        }
    }

    /**
     * Get the assigned admin
     */
    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    /**
     * Scope to get messages for a specific user
     */
    public function scopeForUser($query, $userId, $userType)
    {
        return $query->where('user_id', $userId)
                    ->where('user_type', $userType);
    }

    /**
     * Scope to get unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to get concierge messages
     */
    public function scopeConcierge($query)
    {
        return $query->where('is_concierge', true);
    }

    /**
     * Scope to get user messages
     */
    public function scopeUser($query)
    {
        return $query->where('is_concierge', false);
    }

    /**
     * Scope to get messages by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get messages by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('message_type', $type);
    }

    /**
     * Scope to get urgent messages
     */
    public function scopeUrgent($query)
    {
        return $query->where('category', 'urgent');
    }

    /**
     * Scope to get pending messages
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Get message type label
     */
    public function getMessageTypeLabelAttribute()
    {
        $labels = [
            'inquiry' => 'お問い合わせ',
            'support' => 'サポート',
            'reservation' => '予約関連',
            'payment' => '支払い関連',
            'technical' => '技術的',
            'general' => '一般',
        ];
        
        return $labels[$this->message_type] ?? $this->message_type;
    }

    /**
     * Get category label
     */
    public function getCategoryLabelAttribute()
    {
        $labels = [
            'urgent' => '緊急',
            'normal' => '通常',
            'low' => '低優先度',
        ];
        
        return $labels[$this->category] ?? $this->category;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => '未対応',
            'in_progress' => '対応中',
            'resolved' => '解決済み',
            'closed' => 'クローズ',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }
} 