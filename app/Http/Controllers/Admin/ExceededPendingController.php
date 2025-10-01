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
                // Update payment status to refunded
                $payment->update([
                    'status' => 'refunded',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'cancelled_at' => now()->toISOString(),
                        'cancelled_by' => 'admin',
                        'cancellation_reason' => 'Admin cancelled pending payment'
                    ])
                ]);

                // Refund the points to the guest
                if ($payment->user_type === 'guest' && $payment->user_id) {
                    $guest = \App\Models\Guest::find($payment->user_id);
                    if ($guest) {
                        $pointsToRefund = $payment->amount / 1.2; // Convert yen back to points
                        $guest->points += (int) $pointsToRefund;
                        $guest->save();

                        // Create a refund transaction
                        \App\Models\PointTransaction::create([
                            'guest_id' => $guest->id,
                            'type' => 'refund',
                            'amount' => (int) $pointsToRefund,
                            'reservation_id' => $payment->reservation_id,
                            'payment_id' => $payment->id,
                            'description' => "Payment cancelled - refunded {$pointsToRefund} points"
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment cancelled successfully',
                    'refunded_points' => (int) ($payment->amount / 1.2)
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
