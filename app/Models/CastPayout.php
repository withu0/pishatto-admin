<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CastPayout extends Model
{
    use HasFactory;

    public const TYPE_SCHEDULED = 'scheduled';
    public const TYPE_INSTANT = 'instant';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'cast_id',
        'type',
        'closing_month',
        'period_start',
        'period_end',
        'total_points',
        'conversion_rate',
        'gross_amount_yen',
        'fee_rate',
        'fee_amount_yen',
        'net_amount_yen',
        'transaction_count',
        'status',
        'scheduled_payout_date',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'scheduled_payout_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function cast()
    {
        return $this->belongsTo(Cast::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function markProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->save();
    }

    public function markPaid(?string $note = null): void
    {
        $metadata = $this->metadata ?? [];
        if ($note) {
            $metadata['note'] = $note;
        }

        $this->forceFill([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'metadata' => $metadata,
        ])->save();
    }
}


