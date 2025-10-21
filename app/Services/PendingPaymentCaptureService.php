<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Guest;
use App\Models\PointTransaction;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PendingPaymentCaptureService
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Process all pending payments that are ready for capture
     */
    public function processPendingPayments()
    {
        try {
            // Find all pending payments that have expired (ready for capture)
            $pendingPayments = Payment::where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->whereNotNull('stripe_payment_intent_id')
                ->get();

            Log::info('Processing pending payments for capture', [
                'count' => $pendingPayments->count()
            ]);

            foreach ($pendingPayments as $payment) {
                $this->processSinglePendingPayment($payment);
            }

            return [
                'success' => true,
                'processed_count' => $pendingPayments->count()
            ];

        } catch (\Exception $e) {
            Log::error('Error processing pending payments', [
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
    private function processSinglePendingPayment(Payment $payment)
    {
        try {
            DB::beginTransaction();

            Log::info('Processing pending payment for capture', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $payment->stripe_payment_intent_id,
                'amount' => $payment->amount,
                'expires_at' => $payment->expires_at
            ]);

            // Attempt to capture the payment
            $captureResult = $this->stripeService->capturePaymentIntent(
                $payment->stripe_payment_intent_id,
                $payment->amount
            );

            if ($captureResult['success']) {
                // Capture successful
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'captured_at' => now()->toISOString(),
                        'capture_successful' => true
                    ])
                ]);

                // Update point transaction descriptions to reflect successful capture
                $this->updatePointTransactionDescriptions($payment, true);

                Log::info('Payment captured successfully', [
                    'payment_id' => $payment->id,
                    'payment_intent_id' => $payment->stripe_payment_intent_id
                ]);

                DB::commit();

            } else {
                // Capture failed
                $payment->update([
                    'status' => 'failed',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'capture_failed' => true,
                        'capture_failed_at' => now()->toISOString(),
                        'capture_error' => $captureResult['error'] ?? 'Unknown error'
                    ])
                ]);

                // Send failure notifications
                $this->sendCaptureFailureNotifications($payment);

                // Update point transaction descriptions to reflect failure
                $this->updatePointTransactionDescriptions($payment, false);

                Log::error('Payment capture failed', [
                    'payment_id' => $payment->id,
                    'payment_intent_id' => $payment->stripe_payment_intent_id,
                    'error' => $captureResult['error'] ?? 'Unknown error'
                ]);

                DB::commit();
            }

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error processing single pending payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark payment as failed due to processing error
            $payment->update([
                'status' => 'failed',
                'metadata' => array_merge($payment->metadata ?? [], [
                    'processing_error' => true,
                    'processing_error_at' => now()->toISOString(),
                    'processing_error_message' => $e->getMessage()
                ])
            ]);

            // Send failure notifications
            $this->sendCaptureFailureNotifications($payment);
        }
    }

    /**
     * Update point transaction descriptions based on capture result
     */
    private function updatePointTransactionDescriptions(Payment $payment, bool $captureSuccessful)
    {
        $pointTransactions = PointTransaction::where('payment_id', $payment->id)->get();

        foreach ($pointTransactions as $transaction) {
            if ($captureSuccessful) {
                // Update to reflect successful capture
                if ($transaction->type === 'buy') {
                    $transaction->update([
                        'description' => str_replace('2日後自動支払い予定', '支払い完了', $transaction->description)
                    ]);
                } elseif ($transaction->type === 'exceeded_pending') {
                    $transaction->update([
                        'description' => str_replace('2日後自動控除予定', '控除完了', $transaction->description)
                    ]);
                }
            } else {
                // Update to reflect capture failure
                if ($transaction->type === 'buy') {
                    $transaction->update([
                        'description' => str_replace('2日後自動支払い予定', '支払い失敗', $transaction->description)
                    ]);
                } elseif ($transaction->type === 'exceeded_pending') {
                    $transaction->update([
                        'description' => str_replace('2日後自動控除予定', '控除失敗', $transaction->description)
                    ]);
                }
            }
        }
    }

    /**
     * Send notifications for capture failure
     */
    private function sendCaptureFailureNotifications(Payment $payment)
    {
        try {
            $guest = Guest::find($payment->user_id);
            if (!$guest) {
                Log::warning('Guest not found for capture failure notification', [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id
                ]);
                return;
            }

            $reservationId = $payment->metadata['reservation_id'] ?? null;
            $requiredPoints = $payment->metadata['required_points'] ?? 0;

            // Send chat notifications
            $this->sendChatNotifications($guest, $reservationId, $requiredPoints, 'capture_failed');

            // Send push notifications
            $this->sendPushNotifications($guest, $reservationId, $requiredPoints, 'capture_failed');

            Log::info('Capture failure notifications sent', [
                'payment_id' => $payment->id,
                'guest_id' => $guest->id,
                'reservation_id' => $reservationId
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending capture failure notifications', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send chat notifications for capture failure
     */
    private function sendChatNotifications(Guest $guest, ?int $reservationId, int $requiredPoints, string $failureType)
    {
        try {
            // Find the chat for this reservation
            $chat = \App\Models\Chat::where('reservation_id', $reservationId)
                ->where('guest_id', $guest->id)
                ->first();

            if (!$chat) {
                Log::warning('Chat not found for capture failure notification', [
                    'guest_id' => $guest->id,
                    'reservation_id' => $reservationId
                ]);
                return;
            }

            $guestMessage = "延長時間の支払いが失敗しました。カードの確認をお願いします。";
            $castMessage = "ゲストの延長時間支払いが失敗しました。ゲストにカードの確認をお願いしてください。";

            // Send guest message
            $guestMessageRecord = \App\Models\Message::create([
                'chat_id' => $chat->id,
                'sender_guest_id' => $guest->id,
                'recipient_type' => 'both',
                'message' => json_encode([
                    'type' => 'system',
                    'target' => 'guest',
                    'text' => $guestMessage
                ]),
                'is_read' => 0,
                'created_at' => now()
            ]);

            // Send cast message
            $castMessageRecord = \App\Models\Message::create([
                'chat_id' => $chat->id,
                'sender_cast_id' => $chat->cast_id,
                'recipient_type' => 'both',
                'message' => json_encode([
                    'type' => 'system',
                    'target' => 'cast',
                    'text' => $castMessage
                ]),
                'is_read' => 0,
                'created_at' => now()
            ]);

            // Broadcast both messages
            event(new \App\Events\MessageSent($guestMessageRecord));
            event(new \App\Events\MessageSent($castMessageRecord));

        } catch (\Exception $e) {
            Log::error('Error sending chat notifications for capture failure', [
                'guest_id' => $guest->id,
                'reservation_id' => $reservationId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send push notifications for capture failure
     */
    private function sendPushNotifications(Guest $guest, ?int $reservationId, int $requiredPoints, string $failureType)
    {
        try {
            // Send notification to guest
            \App\Models\Notification::create([
                'user_id' => $guest->id,
                'user_type' => 'guest',
                'type' => 'payment_failed',
                'title' => '支払い失敗',
                'message' => '延長時間の支払いが失敗しました。カードの確認をお願いします。',
                'data' => json_encode([
                    'reservation_id' => $reservationId,
                    'failure_type' => $failureType,
                    'required_points' => $requiredPoints
                ])
            ]);

            // Send notification to cast if reservation exists
            if ($reservationId) {
                $reservation = \App\Models\Reservation::find($reservationId);
                if ($reservation && $reservation->cast_id) {
                    \App\Models\Notification::create([
                        'user_id' => $reservation->cast_id,
                        'user_type' => 'cast',
                        'type' => 'payment_failed',
                        'title' => 'ゲスト支払い失敗',
                        'message' => 'ゲストの延長時間支払いが失敗しました。',
                        'data' => json_encode([
                            'reservation_id' => $reservationId,
                            'guest_id' => $guest->id,
                            'failure_type' => $failureType,
                            'required_points' => $requiredPoints
                        ])
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Error sending push notifications for capture failure', [
                'guest_id' => $guest->id,
                'reservation_id' => $reservationId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
