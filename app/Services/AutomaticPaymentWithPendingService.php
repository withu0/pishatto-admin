<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Payment;
use App\Models\PointTransaction;
use App\Models\Notification;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomaticPaymentWithPendingService
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Process automatic payment for insufficient points with 2-day pending transaction
     *
     * @param int $guestId
     * @param int $requiredPoints
     * @param int $reservationId
     * @param string $description
     * @return array
     */
    public function processAutomaticPaymentWithPending(
        int $guestId,
        int $requiredPoints,
        int $reservationId,
        string $description = '提案による予約 - 自動支払い'
    ): array {
        try {
            DB::beginTransaction();

            $guest = Guest::findOrFail($guestId);

            // Check if guest has a registered payment method
            if (!$guest->stripe_customer_id) {
                Log::warning('Guest has no registered payment method for automatic payment', [
                    'guest_id' => $guestId,
                    'required_points' => $requiredPoints,
                    'reservation_id' => $reservationId
                ]);

                return [
                    'success' => false,
                    'error' => 'No registered payment method found',
                    'requires_card_registration' => true,
                    'all_cards_failed' => true
                ];
            }

            // Convert points to yen (1 point = 1.2 yen)
            $yenPerPoint = (float) config('points.yen_per_point', 1.2);
            $baseAmountInYen = (int) ceil($requiredPoints * $yenPerPoint);

            // Apply consumption tax (1.1 multiplier)
            $amountInYen = (int) ceil($baseAmountInYen * 1.1);

            // Ensure minimum amount for Stripe (100 yen)
            if ($amountInYen < 100) {
                $amountInYen = 100;
            }

            Log::info('Processing automatic payment with pending for insufficient points', [
                'guest_id' => $guestId,
                'required_points' => $requiredPoints,
                'base_amount_yen' => $baseAmountInYen,
                'amount_with_tax_yen' => $amountInYen,
                'reservation_id' => $reservationId
            ]);

            // Create Stripe payment intent with 2-day capture delay
            $paymentIntent = $this->stripeService->createPaymentIntentWithDelay(
                $guest->stripe_customer_id,
                $amountInYen,
                $description,
                2 // 2 days delay for production
            );

            if (!$paymentIntent['success']) {
                DB::rollBack();

                // Check if the error is related to missing payment method
                $requiresCardRegistration = false;
                if (strpos($paymentIntent['error'], 'payment method') !== false ||
                    strpos($paymentIntent['error'], 'return_url') !== false) {
                    $requiresCardRegistration = true;
                }

                return [
                    'success' => false,
                    'error' => $paymentIntent['error'],
                    'requires_card_registration' => $requiresCardRegistration
                ];
            }

            // Create payment record with pending status
            $payment = Payment::create([
                'user_id' => $guestId,
                'user_type' => 'guest',
                'amount' => $amountInYen,
                'currency' => 'jpy',
                'status' => 'pending',
                'stripe_payment_intent_id' => $paymentIntent['payment_intent_id'],
                'description' => $description,
                'reservation_id' => $reservationId > 0 ? $reservationId : null,
                'is_automatic' => true,
                'expires_at' => now()->addDays(2)->addHour(), // 2 days + 1 hour from now (for production)
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create pending point transaction
            $pointTransaction = PointTransaction::create([
                'guest_id' => $guestId,
                'type' => 'pending',
                'amount' => $requiredPoints,
                'reservation_id' => $reservationId > 0 ? $reservationId : null,
                'description' => $description . ' (2日後自動支払い)',
                'payment_id' => $payment->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Add points to guest immediately (they can use them right away)
            $guest->points += $requiredPoints;
            $guest->save();

            DB::commit();

            // Send notification to guest about automatic payment
            $this->sendAutomaticPaymentNotification($guest, $requiredPoints, $amountInYen, $reservationId);

            Log::info('Automatic payment with pending created successfully', [
                'guest_id' => $guestId,
                'payment_id' => $payment->id,
                'point_transaction_id' => $pointTransaction->id,
                'amount_yen' => $amountInYen,
                'points_added' => $requiredPoints,
                'expires_at' => $payment->expires_at
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'point_transaction_id' => $pointTransaction->id,
                'amount_yen' => $amountInYen,
                'points_added' => $requiredPoints,
                'new_balance' => $guest->points,
                'expires_at' => $payment->expires_at,
                'payment_intent_id' => $paymentIntent['payment_intent_id']
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Automatic payment with pending failed', [
                'guest_id' => $guestId,
                'required_points' => $requiredPoints,
                'reservation_id' => $reservationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process all pending payments that are ready for capture (2 days old)
     */
    public function processPendingPaymentsForCapture(): array
    {
        try {
            // Find all pending payments that are ready for capture (2 days old)
            $pendingPayments = Payment::where('status', 'pending')
                ->where('expires_at', '<=', now()) // Capture when expired (2+ days)
                ->whereNotNull('stripe_payment_intent_id')
                ->where('is_automatic', true)
                ->get();

            // Debug: Log all pending automatic payments
            $allPendingAutomatic = Payment::where('status', 'pending')
                ->where('is_automatic', true)
                ->get();

            Log::info('Debug: All pending automatic payments', [
                'count' => $allPendingAutomatic->count(),
                'payments' => $allPendingAutomatic->map(function($payment) {
                    return [
                        'id' => $payment->id,
                        'expires_at' => $payment->expires_at,
                        'now' => now(),
                        'is_expired' => $payment->expires_at <= now(),
                        'stripe_payment_intent_id' => $payment->stripe_payment_intent_id
                    ];
                })->toArray()
            ]);

            Log::info('Processing pending payments for capture', [
                'count' => $pendingPayments->count()
            ]);

            $processedCount = 0;
            $failedCount = 0;

            foreach ($pendingPayments as $payment) {
                $result = $this->processSinglePendingPayment($payment);
                if ($result['success']) {
                    $processedCount++;
                } else {
                    $failedCount++;
                }
            }

            return [
                'success' => true,
                'processed_count' => $processedCount,
                'failed_count' => $failedCount,
                'total_found' => $pendingPayments->count()
            ];

        } catch (\Exception $e) {
            Log::error('Error processing pending payments for capture', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process a single pending payment for capture
     */
    private function processSinglePendingPayment(Payment $payment): array
    {
        try {
            DB::beginTransaction();

            // Capture the payment intent
            $captureResult = $this->stripeService->capturePaymentIntent($payment->stripe_payment_intent_id);

            if ($captureResult['success']) {
                // Update payment status
                $payment->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now()
                ]);

                // Update point transaction
                $pointTransaction = PointTransaction::where('payment_id', $payment->id)->first();
                if ($pointTransaction) {
                    $pointTransaction->update([
                        'description' => str_replace('(2日後自動支払い)', '(自動支払い完了)', $pointTransaction->description),
                        'updated_at' => now()
                    ]);
                }

                DB::commit();

                // Send notification to guest about successful payment capture
                $this->sendPaymentCaptureNotification($payment);

                Log::info('Pending payment captured successfully', [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'guest_id' => $payment->user_id
                ]);

                return [
                    'success' => true,
                    'payment_id' => $payment->id
                ];
            } else {
                // Payment failed - handle accordingly
                $payment->update([
                    'status' => 'failed',
                    'error_message' => $captureResult['error'],
                    'updated_at' => now()
                ]);

                // Refund points from guest
                $guest = Guest::find($payment->user_id);
                if ($guest) {
                    $pointTransaction = PointTransaction::where('payment_id', $payment->id)->first();
                    if ($pointTransaction) {
                        $guest->points = max(0, $guest->points - $pointTransaction->amount);
                        $guest->save();

                        // Update transaction description
                        $pointTransaction->update([
                            'description' => str_replace('(2日後自動支払い)', '(支払い失敗 - ポイント返却)', $pointTransaction->description),
                            'updated_at' => now()
                        ]);
                    }
                }

                DB::commit();

                // Send notification to guest about payment failure
                $this->sendPaymentFailureNotification($payment, $captureResult['error']);

                Log::warning('Pending payment capture failed', [
                    'payment_id' => $payment->id,
                    'error' => $captureResult['error']
                ]);

                return [
                    'success' => false,
                    'error' => $captureResult['error']
                ];
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing single pending payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to guest about automatic payment creation
     */
    private function sendAutomaticPaymentNotification(Guest $guest, int $pointsAdded, int $amountYen, ?int $reservationId = null): void
    {
        try {
            $amountFormatted = number_format($amountYen);
            $pointsFormatted = number_format($pointsAdded);

            $message = "【自動支払い処理】\n";
            $message .= "ポイント不足のため、自動的に支払い処理を開始しました。\n";
            $message .= "追加ポイント: {$pointsFormatted}P\n";
            $message .= "支払い金額: ¥{$amountFormatted}\n";
            $message .= "2日後に自動的に支払いが完了します。\n\n";
            $message .= "予約は正常に作成されました。";

            $notification = Notification::create([
                'user_id' => $guest->id,
                'user_type' => 'guest',
                'type' => 'automatic_payment_created',
                'message' => $message,
                'reservation_id' => $reservationId,
                'read' => false,
            ]);

            // Broadcast the notification
            event(new \App\Events\NotificationSent($notification));

            Log::info('Automatic payment notification sent', [
                'guest_id' => $guest->id,
                'notification_id' => $notification->id,
                'points_added' => $pointsAdded,
                'amount_yen' => $amountYen
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send automatic payment notification', [
                'guest_id' => $guest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to guest about successful payment capture
     */
    private function sendPaymentCaptureNotification(Payment $payment): void
    {
        try {
            $guest = Guest::find($payment->user_id);
            if (!$guest) {
                return;
            }

            $amountFormatted = number_format($payment->amount);
            $pointsAdded = $payment->points_added;
            $pointsFormatted = number_format($pointsAdded);

            $message = "【支払い完了】\n";
            $message .= "自動支払いが正常に完了しました。\n";
            $message .= "支払い金額: ¥{$amountFormatted}\n";
            $message .= "追加されたポイント: {$pointsFormatted}P\n";
            $message .= "現在のポイント残高: " . number_format($guest->points) . "P";

            $notification = Notification::create([
                'user_id' => $guest->id,
                'user_type' => 'guest',
                'type' => 'payment_completed',
                'message' => $message,
                'reservation_id' => $payment->reservation_id,
                'read' => false,
            ]);

            // Broadcast the notification
            event(new \App\Events\NotificationSent($notification));

            Log::info('Payment capture notification sent', [
                'guest_id' => $guest->id,
                'payment_id' => $payment->id,
                'notification_id' => $notification->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment capture notification', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to guest about payment failure
     */
    private function sendPaymentFailureNotification(Payment $payment, string $errorMessage): void
    {
        try {
            $guest = Guest::find($payment->user_id);
            if (!$guest) {
                return;
            }

            $amountFormatted = number_format($payment->amount);
            $pointsAdded = $payment->points_added;
            $pointsFormatted = number_format($pointsAdded);

            $message = "【支払い失敗】\n";
            $message .= "自動支払いの処理に失敗しました。\n";
            $message .= "支払い金額: ¥{$amountFormatted}\n";
            $message .= "追加されたポイント: {$pointsFormatted}P は返却されました。\n";
            $message .= "現在のポイント残高: " . number_format($guest->points) . "P\n\n";
            $message .= "カード情報の確認をお願いします。";

            $notification = Notification::create([
                'user_id' => $guest->id,
                'user_type' => 'guest',
                'type' => 'payment_failed',
                'message' => $message,
                'reservation_id' => $payment->reservation_id,
                'read' => false,
            ]);

            // Broadcast the notification
            event(new \App\Events\NotificationSent($notification));

            Log::info('Payment failure notification sent', [
                'guest_id' => $guest->id,
                'payment_id' => $payment->id,
                'notification_id' => $notification->id,
                'error' => $errorMessage
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment failure notification', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
