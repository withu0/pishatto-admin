<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Payment;
use App\Models\PointTransaction;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomaticPaymentService
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Process automatic payment for insufficient points during exceeded time
     *
     * @param int $guestId
     * @param int $requiredAmountInPoints
     * @param int $reservationId
     * @param string $description
     * @return array
     */
    public function processAutomaticPaymentForInsufficientPoints(
        int $guestId,
        int $requiredAmountInPoints,
        ?int $reservationId,
        string $description = 'Automatic payment for exceeded time'
    ): array {
        try {
            DB::beginTransaction();

            $guest = Guest::findOrFail($guestId);

            // Check if guest has a registered payment method
            if (!$guest->stripe_customer_id) {
                return [
                    'success' => false,
                    'error' => 'No registered payment method found',
                    'requires_card_registration' => true
                ];
            }

            // Convert points to yen (1 point = 1.2 yen)
            $yenPerPoint = (float) config('points.yen_per_point', 1.2);
            $amountInYen = (int) ceil($requiredAmountInPoints * $yenPerPoint);

            // Ensure minimum amount for Stripe (100 yen)
            if ($amountInYen < 100) {
                $amountInYen = 100;
            }

            Log::info('Processing automatic payment for insufficient points', [
                'guest_id' => $guestId,
                'required_points' => $requiredAmountInPoints,
                'amount_yen' => $amountInYen,
                'reservation_id' => $reservationId
            ]);

            // Create payment record
            $payment = Payment::create([
                'user_id' => $guestId,
                'user_type' => 'guest',
                'amount' => $amountInYen,
                'payment_method' => 'card',
                'status' => 'pending',
                'description' => $description,
                'reservation_id' => $reservationId,
                'is_automatic' => true, // Flag to indicate this is an automatic payment
                'stripe_customer_id' => $guest->stripe_customer_id,
                'metadata' => [
                    'automatic_payment' => true,
                    'required_points' => $requiredAmountInPoints,
                    'conversion_rate' => $yenPerPoint,
                    'original_points_requested' => $requiredAmountInPoints,
                    'deduction_type' => 'exceeded_time_shortfall'
                ]
            ]);

            // Process payment using Stripe with manual capture (pending for 2 days)
            $paymentData = [
                'customer_id' => $guest->stripe_customer_id,
                'amount' => $amountInYen,
                'currency' => 'jpy',
                'user_id' => $guestId,
                'user_type' => 'guest',
                'description' => $description,
                'payment_method_type' => 'card',
                'capture_method' => 'manual', // Use manual capture for delayed processing
                'metadata' => [
                    'automatic_payment' => true,
                    'required_points' => $requiredAmountInPoints,
                    'conversion_rate' => $yenPerPoint,
                    'original_points_requested' => $requiredAmountInPoints,
                    'deduction_type' => 'exceeded_time_shortfall',
                    'scheduled_capture_at' => now()->addDays(2)->toISOString()
                ]
            ];

            $result = $this->stripeService->processPaymentWithManualCapture($paymentData);

            if (!$result['success']) {
                Log::error('Automatic payment failed', [
                    'guest_id' => $guestId,
                    'amount_yen' => $amountInYen,
                    'error' => $result['error'],
                    'reservation_id' => $reservationId
                ]);

                $payment->update([
                    'status' => 'failed',
                    'stripe_payment_intent_id' => $result['payment_intent_id'] ?? null,
                    'error_message' => $result['error']
                ]);

                DB::rollBack();

                return [
                    'success' => false,
                    'error' => $result['error'],
                    'payment_id' => $payment->id,
                    'requires_card_registration' => false
                ];
            }

            // Update payment record with pending status (will be captured after 2 days)
            $paymentIntentId = $result['payment_intent']['id'] ?? null;
            $payment->update([
                'status' => 'pending', // Payment is pending until captured
                'stripe_payment_intent_id' => $paymentIntentId,
                'paid_at' => null, // Will be set when payment is captured
                'metadata' => array_merge($payment->metadata ?? [], [
                    'payment_authorized' => true,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'authorized_at' => now()->toISOString(),
                    'scheduled_capture_at' => now()->addDays(2)->toISOString(),
                    'deduction_amount_yen' => $amountInYen,
                    'deduction_amount_points' => $requiredAmountInPoints,
                    'capture_method' => 'manual'
                ])
            ]);

            // Add points to guest account immediately (authorization successful)
            $currentPoints = $guest->points ?? 0;
            $newPoints = $currentPoints + $requiredAmountInPoints;
            $guest->points = $newPoints;
            $guest->save();

            // Create point transaction record for the automatic purchase
            $pointTransaction = PointTransaction::create([
                'guest_id' => $guestId,
                'type' => 'buy',
                'amount' => $requiredAmountInPoints,
                'description' => "Automatic payment for exceeded time - reservation {$reservationId}",
                'reservation_id' => $reservationId,
                'payment_id' => $payment->id
            ]);

            // Create a separate transaction record for the deduction from exceeded_pending
            PointTransaction::create([
                'guest_id' => $guestId,
                'type' => 'exceeded_pending',
                'amount' => -$requiredAmountInPoints, // Negative amount for deduction
                'description' => "Automatic deduction for exceeded time - paid via card (Payment ID: {$payment->id})",
                'reservation_id' => $reservationId,
                'payment_id' => $payment->id
            ]);

            // Update guest grade
            try {
                $gradeService = app(\App\Services\GradeService::class);
                $gradeService->calculateAndUpdateGrade($guest);
            } catch (\Throwable $e) {
                Log::warning('Failed to update grade after automatic payment', [
                    'guest_id' => $guestId,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            Log::info('Automatic payment completed successfully', [
                'guest_id' => $guestId,
                'amount_yen' => $amountInYen,
                'points_added' => $requiredAmountInPoints,
                'payment_id' => $payment->id,
                'reservation_id' => $reservationId
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'amount_yen' => $amountInYen,
                'points_added' => $requiredAmountInPoints,
                'new_balance' => $newPoints,
                'stripe_payment_intent_id' => $paymentIntentId,
                'status' => 'pending',
                'scheduled_capture_at' => now()->addDays(2)->toISOString()
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Automatic payment processing failed', [
                'guest_id' => $guestId,
                'required_points' => $requiredAmountInPoints,
                'reservation_id' => $reservationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Payment processing failed: ' . $e->getMessage(),
                'requires_card_registration' => false
            ];
        }
    }

    /**
     * Check if guest has registered payment method
     *
     * @param int $guestId
     * @return bool
     */
    public function hasRegisteredPaymentMethod(int $guestId): bool
    {
        $guest = Guest::find($guestId);
        return $guest && $guest->stripe_customer_id;
    }

    /**
     * Get guest's payment method info
     *
     * @param int $guestId
     * @return array|null
     */
    public function getGuestPaymentInfo(int $guestId): ?array
    {
        try {
            $guest = Guest::find($guestId);
            if (!$guest || !$guest->stripe_customer_id) {
                return null;
            }

            // Get payment methods from Stripe
            $paymentMethods = $this->stripeService->getCustomerPaymentMethods($guest->stripe_customer_id);

            return [
                'has_payment_method' => !empty($paymentMethods),
                'customer_id' => $guest->stripe_customer_id,
                'payment_methods' => $paymentMethods
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get guest payment info', [
                'guest_id' => $guestId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
