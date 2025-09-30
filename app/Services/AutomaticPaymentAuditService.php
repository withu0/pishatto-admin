<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PointTransaction;
use App\Models\Guest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomaticPaymentAuditService
{
    /**
     * Get all automatic payment deductions for a guest
     * 
     * @param int $guestId
     * @return array
     */
    public function getGuestAutomaticPayments(int $guestId): array
    {
        $payments = Payment::where('user_id', $guestId)
            ->where('user_type', 'guest')
            ->where('is_automatic', true)
            ->with(['pointTransactions'])
            ->orderBy('created_at', 'desc')
            ->get();

        $result = [];
        foreach ($payments as $payment) {
            $result[] = [
                'payment_id' => $payment->id,
                'amount_yen' => $payment->amount,
                'amount_points' => $this->getPointsFromPayment($payment),
                'status' => $payment->status,
                'description' => $payment->description,
                'reservation_id' => $payment->reservation_id,
                'paid_at' => $payment->paid_at,
                'created_at' => $payment->created_at,
                'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                'metadata' => $payment->metadata,
                'point_transactions' => $payment->pointTransactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => $transaction->type,
                        'amount' => $transaction->amount,
                        'description' => $transaction->description,
                        'created_at' => $transaction->created_at
                    ];
                })
            ];
        }

        return $result;
    }

    /**
     * Get automatic payment summary for a guest
     * 
     * @param int $guestId
     * @return array
     */
    public function getGuestAutomaticPaymentSummary(int $guestId): array
    {
        $payments = Payment::where('user_id', $guestId)
            ->where('user_type', 'guest')
            ->where('is_automatic', true);

        $totalYen = $payments->sum('amount');
        $totalPoints = $payments->where('status', 'paid')->get()->sum(function ($payment) {
            return $this->getPointsFromPayment($payment);
        });

        $successfulPayments = $payments->where('status', 'paid')->count();
        $failedPayments = $payments->where('status', 'failed')->count();

        return [
            'total_automatic_payments' => $payments->count(),
            'successful_payments' => $successfulPayments,
            'failed_payments' => $failedPayments,
            'total_yen_charged' => $totalYen,
            'total_points_purchased' => $totalPoints,
            'success_rate' => $payments->count() > 0 ? round(($successfulPayments / $payments->count()) * 100, 2) : 0
        ];
    }

    /**
     * Get all automatic payment deductions for a reservation
     * 
     * @param int $reservationId
     * @return array
     */
    public function getReservationAutomaticPayments(int $reservationId): array
    {
        $payments = Payment::where('reservation_id', $reservationId)
            ->where('is_automatic', true)
            ->with(['pointTransactions', 'guest'])
            ->orderBy('created_at', 'desc')
            ->get();

        $result = [];
        foreach ($payments as $payment) {
            $result[] = [
                'payment_id' => $payment->id,
                'guest_id' => $payment->user_id,
                'guest_name' => $payment->guest->name ?? 'Unknown',
                'amount_yen' => $payment->amount,
                'amount_points' => $this->getPointsFromPayment($payment),
                'status' => $payment->status,
                'description' => $payment->description,
                'paid_at' => $payment->paid_at,
                'created_at' => $payment->created_at,
                'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                'deduction_details' => $this->getDeductionDetails($payment)
            ];
        }

        return $result;
    }

    /**
     * Get deduction details from payment metadata
     * 
     * @param Payment $payment
     * @return array
     */
    private function getDeductionDetails(Payment $payment): array
    {
        $metadata = $payment->metadata ?? [];
        
        return [
            'deduction_type' => $metadata['deduction_type'] ?? 'unknown',
            'required_points' => $metadata['required_points'] ?? 0,
            'conversion_rate' => $metadata['conversion_rate'] ?? 1.2,
            'original_points_requested' => $metadata['original_points_requested'] ?? 0,
            'payment_successful' => $metadata['payment_successful'] ?? false,
            'processed_at' => $metadata['processed_at'] ?? null
        ];
    }

    /**
     * Get points amount from payment metadata or calculate from yen
     * 
     * @param Payment $payment
     * @return int
     */
    private function getPointsFromPayment(Payment $payment): int
    {
        $metadata = $payment->metadata ?? [];
        
        if (isset($metadata['deduction_amount_points'])) {
            return (int) $metadata['deduction_amount_points'];
        }
        
        if (isset($metadata['required_points'])) {
            return (int) $metadata['required_points'];
        }
        
        // Fallback: calculate from yen amount
        $yenPerPoint = (float) config('points.yen_per_point', 1.2);
        return (int) floor($payment->amount / $yenPerPoint);
    }

    /**
     * Get comprehensive audit trail for automatic payments
     * 
     * @param int $guestId
     * @param int|null $reservationId
     * @return array
     */
    public function getAuditTrail(int $guestId, ?int $reservationId = null): array
    {
        $query = PointTransaction::where('guest_id', $guestId)
            ->where('type', 'exceeded_pending')
            ->whereNotNull('payment_id');

        if ($reservationId) {
            $query->where('reservation_id', $reservationId);
        }

        $transactions = $query->with(['payment', 'reservation'])
            ->orderBy('created_at', 'desc')
            ->get();

        $result = [];
        foreach ($transactions as $transaction) {
            $payment = $transaction->payment;
            $result[] = [
                'transaction_id' => $transaction->id,
                'payment_id' => $payment->id ?? null,
                'reservation_id' => $transaction->reservation_id,
                'amount_points' => abs($transaction->amount), // Always positive for display
                'amount_yen' => $payment->amount ?? 0,
                'status' => $payment->status ?? 'unknown',
                'description' => $transaction->description,
                'created_at' => $transaction->created_at,
                'paid_at' => $payment->paid_at ?? null,
                'stripe_payment_intent_id' => $payment->stripe_payment_intent_id ?? null,
                'is_automatic' => $payment->is_automatic ?? false,
                'deduction_details' => $payment ? $this->getDeductionDetails($payment) : []
            ];
        }

        return $result;
    }

    /**
     * Log automatic payment deduction for audit purposes
     * 
     * @param int $guestId
     * @param int $paymentId
     * @param int $amountYen
     * @param int $amountPoints
     * @param int $reservationId
     * @param string $description
     * @return void
     */
    public function logAutomaticDeduction(
        int $guestId,
        int $paymentId,
        int $amountYen,
        int $amountPoints,
        int $reservationId,
        string $description
    ): void {
        Log::info('Automatic payment deduction recorded', [
            'guest_id' => $guestId,
            'payment_id' => $paymentId,
            'amount_yen' => $amountYen,
            'amount_points' => $amountPoints,
            'reservation_id' => $reservationId,
            'description' => $description,
            'timestamp' => now()->toISOString()
        ]);
    }
}
