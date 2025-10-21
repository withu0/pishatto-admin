<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PointTransactionService;
use App\Services\StripeService;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExceededPendingController extends Controller
{
    protected $pointTransactionService;

    public function __construct(PointTransactionService $pointTransactionService)
    {
        $this->pointTransactionService = $pointTransactionService;
    }

    /**
     * Get all point transactions except pending type
     */
    public function index(): JsonResponse
    {
        try {
            $transactions = $this->pointTransactionService->getAllPointTransactionsExceptPending();

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'count' => $transactions->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch point transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get point transactions grouped by reservation
     */
    public function groupedByReservation(): JsonResponse
    {
        try {
            $reservationSummaries = $this->pointTransactionService->getPointTransactionsGroupedByReservation();

            return response()->json([
                'success' => true,
                'data' => $reservationSummaries,
                'count' => count($reservationSummaries)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grouped transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get point transactions count (except pending type)
     */
    public function count(): JsonResponse
    {
        try {
            $count = $this->pointTransactionService->getAllPointTransactionsExceptPendingCount();

            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get point transactions count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all point transactions (including pending)
     */
    public function getAllPointTransactions(): JsonResponse
    {
        try {
            $transactions = \App\Models\PointTransaction::with(['guest', 'cast', 'reservation.guest', 'payment'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'count' => $transactions->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch point transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually process all exceeded pending transactions (admin override)
     */
    public function processAll(): JsonResponse
    {
        try {
            $processedCount = $this->pointTransactionService->processAutoTransferExceededPending();

            return response()->json([
                'success' => true,
                'message' => "Processed {$processedCount} exceeded pending transactions",
                'processed_count' => $processedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process exceeded pending transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a Stripe pending payment
     */
    public function cancelPayment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'payment_id' => 'required|integer|exists:payments,id'
            ]);

            $payment = Payment::findOrFail($request->payment_id);

            // Check if payment is pending
            if ($payment->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is not in pending status'
                ], 400);
            }

            // Check if payment has Stripe payment intent
            if (!$payment->stripe_payment_intent_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Stripe payment intent found for this payment'
                ], 400);
            }

            // Cancel the Stripe payment intent
            $stripeService = app(StripeService::class);
            $result = $stripeService->cancelPaymentIntent($payment->stripe_payment_intent_id);

            if ($result['success']) {
                // Update payment status to refunded (cancelled pending authorization)
                $payment->update([
                    'status' => 'refunded',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'cancelled_at' => now()->toISOString(),
                        'cancelled_by' => 'admin',
                        'cancellation_reason' => 'Admin cancelled pending payment'
                    ])
                ]);

                // Reverse the previously granted points to the guest (remove granted points)
                if ($payment->user_type === 'guest' && $payment->user_id) {
                    $guest = \App\Models\Guest::find($payment->user_id);
                    if ($guest) {
                        // Use configured conversion rate (yen per point) and round down to int
                        $yenPerPoint = (float) (config('points.yen_per_point', 1.2));
                        $pointsToReverse = (int) floor($payment->amount / max($yenPerPoint, 0.0001));

                        // Decrease guest points but not below zero
                        $guest->points = max(0, (int) $guest->points - $pointsToReverse);
                        $guest->save();

                        // Create a refund transaction (negative to represent removal of granted points)
                        \App\Models\PointTransaction::create([
                            'guest_id' => $guest->id,
                            'type' => 'refund',
                            'amount' => -$pointsToReverse,
                            'reservation_id' => $payment->reservation_id,
                            'payment_id' => $payment->id,
                            'description' => "保留支払いキャンセル - 付与ポイント取り消し {$pointsToReverse}P"
                        ]);

                        // Mark related exceeded_pending (negative placeholder) as cancelled to prevent auto-transfer
                        $relatedExceeded = \App\Models\PointTransaction::where('payment_id', $payment->id)
                            ->where('type', 'exceeded_pending')
                            ->first();
                        if ($relatedExceeded) {
                            $relatedExceeded->update([
                                'description' => trim((string) $relatedExceeded->description . ' (キャンセル)')
                            ]);
                        }

                        // Optionally mark the related buy transaction as cancelled in description
                        $relatedBuy = \App\Models\PointTransaction::where('payment_id', $payment->id)
                            ->where('type', 'buy')
                            ->first();
                        if ($relatedBuy) {
                            $relatedBuy->update([
                                'description' => trim((string) $relatedBuy->description . ' (キャンセル)')
                            ]);
                        }
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment cancelled successfully',
                    'refunded_points' => 0
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel Stripe payment: ' . $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
