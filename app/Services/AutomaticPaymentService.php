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
        string $description = '延長時間の自動支払い'
    ): array {
        try {
            DB::beginTransaction();

            $guest = Guest::findOrFail($guestId);

            // Check if guest has a registered payment method
            if (!$guest->stripe_customer_id) {
                Log::warning('Guest has no registered payment method', [
                    'guest_id' => $guestId,
                    'required_points' => $requiredAmountInPoints,
                    'reservation_id' => $reservationId
                ]);

                // Send notifications about no payment method (same as all cards fail)
                $this->sendPaymentFailureNotifications($guest, $reservationId, $requiredAmountInPoints, ['No registered payment method found']);

                return [
                    'success' => false,
                    'error' => 'No registered payment method found',
                    'requires_card_registration' => true,
                    'all_cards_failed' => true // Mark as all cards failed for consistent handling
                ];
            }

            // Convert points to yen (1 point = 1.2 yen)
            $yenPerPoint = (float) config('points.yen_per_point', 1.2);
            $baseAmountInYen = (int) ceil($requiredAmountInPoints * $yenPerPoint);

            // Apply consumption tax (1.1 multiplier)
            $amountInYen = (int) ceil($baseAmountInYen * 1.1);

            // Ensure minimum amount for Stripe (100 yen)
            if ($amountInYen < 100) {
                $amountInYen = 100;
            }

            Log::info('Processing automatic payment for insufficient points', [
                'guest_id' => $guestId,
                'required_points' => $requiredAmountInPoints,
                'base_amount_yen' => $baseAmountInYen,
                'amount_with_tax_yen' => $amountInYen,
                'tax_amount' => $amountInYen - $baseAmountInYen,
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
                    'deduction_type' => 'exceeded_time_shortfall',
                    'base_amount_yen' => $baseAmountInYen,
                    'tax_amount' => $amountInYen - $baseAmountInYen,
                    'consumption_tax_applied' => true
                ]
            ]);

            // Try payment with retry logic for all registered cards
            $result = $this->processPaymentWithRetry($guest, $amountInYen, $guestId, $description, $requiredAmountInPoints, $baseAmountInYen, $yenPerPoint, $reservationId);

            if (!$result['success']) {
                Log::error('All automatic payment attempts failed', [
                    'guest_id' => $guestId,
                    'amount_yen' => $amountInYen,
                    'errors' => $result['errors'],
                    'reservation_id' => $reservationId
                ]);

                $payment->update([
                    'status' => 'failed',
                    'stripe_payment_intent_id' => $result['last_payment_intent_id'] ?? null,
                    'error_message' => implode('; ', $result['errors'])
                ]);

                DB::rollBack();

                // Send notifications about payment failure
                $this->sendPaymentFailureNotifications($guest, $reservationId, $requiredAmountInPoints, $result['errors']);

                return [
                    'success' => false,
                    'error' => 'All payment methods failed: ' . implode('; ', $result['errors']),
                    'payment_id' => $payment->id,
                    'requires_card_registration' => false,
                    'all_cards_failed' => true
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
                'description' => "延長時間の自動支払い - 予約{$reservationId}",
                'reservation_id' => $reservationId,
                'payment_id' => $payment->id
            ]);

            // Create a separate transaction record for the deduction from exceeded_pending
            PointTransaction::create([
                'guest_id' => $guestId,
                'type' => 'exceeded_pending',
                'amount' => -$requiredAmountInPoints, // Negative amount for deduction
                'description' => "延長時間の自動控除 - カード支払い済み (支払いID: {$payment->id})",
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

    /**
     * Process payment with retry logic for all registered cards
     *
     * @param Guest $guest
     * @param int $amountInYen
     * @param int $guestId
     * @param string $description
     * @param int $requiredAmountInPoints
     * @param int $baseAmountInYen
     * @param float $yenPerPoint
     * @param int|null $reservationId
     * @return array
     */
    private function processPaymentWithRetry(
        Guest $guest,
        int $amountInYen,
        int $guestId,
        string $description,
        int $requiredAmountInPoints,
        int $baseAmountInYen,
        float $yenPerPoint,
        ?int $reservationId
    ): array {
        try {
            // Get all payment methods for the customer
            $paymentMethods = $this->stripeService->getCustomerPaymentMethods($guest->stripe_customer_id);

            if (empty($paymentMethods['data'])) {
                return [
                    'success' => false,
                    'errors' => ['No payment methods found'],
                    'last_payment_intent_id' => null
                ];
            }

            $errors = [];
            $lastPaymentIntentId = null;

            // Try each payment method
            foreach ($paymentMethods['data'] as $index => $paymentMethod) {
                Log::info('Attempting payment with card', [
                    'guest_id' => $guestId,
                    'payment_method_id' => $paymentMethod['id'],
                    'card_last4' => $paymentMethod['card']['last4'] ?? 'unknown',
                    'attempt' => $index + 1,
                    'total_cards' => count($paymentMethods['data'])
                ]);

                $paymentData = [
                    'customer_id' => $guest->stripe_customer_id,
                    'amount' => $amountInYen,
                    'currency' => 'jpy',
                    'user_id' => $guestId,
                    'user_type' => 'guest',
                    'description' => $description,
                    'payment_method_type' => 'card',
                    'capture_method' => 'manual',
                    'payment_method' => $paymentMethod['id'], // Use specific payment method
                    'metadata' => [
                        'automatic_payment' => true,
                        'required_points' => $requiredAmountInPoints,
                        'conversion_rate' => $yenPerPoint,
                        'original_points_requested' => $requiredAmountInPoints,
                        'deduction_type' => 'exceeded_time_shortfall',
                        'scheduled_capture_at' => now()->addDays(2)->toISOString(),
                        'base_amount_yen' => $baseAmountInYen,
                        'tax_amount' => $amountInYen - $baseAmountInYen,
                        'consumption_tax_applied' => true,
                        'payment_method_attempt' => $index + 1,
                        'total_payment_methods' => count($paymentMethods['data'])
                    ]
                ];

                $result = $this->stripeService->processPaymentWithManualCapture($paymentData);

                if ($result['success']) {
                    Log::info('Payment succeeded with card', [
                        'guest_id' => $guestId,
                        'payment_method_id' => $paymentMethod['id'],
                        'card_last4' => $paymentMethod['card']['last4'] ?? 'unknown',
                        'attempt' => $index + 1
                    ]);

                    return [
                        'success' => true,
                        'payment_intent' => $result['payment_intent'],
                        'payment_method_used' => $paymentMethod['id'],
                        'attempt_number' => $index + 1
                    ];
                } else {
                    $error = "Card ending in " . ($paymentMethod['card']['last4'] ?? 'unknown') . ": " . $result['error'];
                    $errors[] = $error;
                    $lastPaymentIntentId = $result['payment_intent_id'] ?? null;

                    Log::warning('Payment failed with card', [
                        'guest_id' => $guestId,
                        'payment_method_id' => $paymentMethod['id'],
                        'card_last4' => $paymentMethod['card']['last4'] ?? 'unknown',
                        'error' => $result['error'],
                        'attempt' => $index + 1
                    ]);
                }
            }

            return [
                'success' => false,
                'errors' => $errors,
                'last_payment_intent_id' => $lastPaymentIntentId
            ];

        } catch (\Exception $e) {
            Log::error('Payment retry logic failed', [
                'guest_id' => $guestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'errors' => ['Payment retry system error: ' . $e->getMessage()],
                'last_payment_intent_id' => null
            ];
        }
    }

    /**
     * Send notifications when all payment methods fail
     *
     * @param Guest $guest
     * @param int|null $reservationId
     * @param int $requiredAmountInPoints
     * @param array $errors
     * @return void
     */
    private function sendPaymentFailureNotifications(Guest $guest, ?int $reservationId, int $requiredAmountInPoints, array $errors): void
    {
        try {
            // Get reservation details
            $reservation = null;
            if ($reservationId) {
                $reservation = \App\Models\Reservation::find($reservationId);
            }

            // Send chat notifications
            $this->sendChatNotifications($guest, $reservation, $requiredAmountInPoints, $errors);

            // Send cast notifications
            $this->sendCastNotifications($guest, $reservation, $requiredAmountInPoints, $errors);

            // Send guest notifications
            $this->sendGuestNotifications($guest, $reservation, $requiredAmountInPoints, $errors);

        } catch (\Exception $e) {
            Log::error('Failed to send payment failure notifications', [
                'guest_id' => $guest->id,
                'reservation_id' => $reservationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send chat notifications for payment failure
     *
     * @param Guest $guest
     * @param \App\Models\Reservation|null $reservation
     * @param int $requiredAmountInPoints
     * @param array $errors
     * @return void
     */
    private function sendChatNotifications(Guest $guest, ?\App\Models\Reservation $reservation, int $requiredAmountInPoints, array $errors): void
    {
        if (!$reservation) {
            return;
        }

        try {
            $errorMessage = implode('; ', $errors);
            $guestAvailablePoints = $guest->points ?? 0;

            // Find the chat for this reservation
            $chat = \App\Models\Chat::where('guest_id', $guest->id)
                ->where('cast_id', $reservation->cast_id)
                ->first();

            if (!$chat) {
                Log::warning('No chat found for payment failure notification', [
                    'guest_id' => $guest->id,
                    'cast_id' => $reservation->cast_id,
                    'reservation_id' => $reservation->id
                ]);
                return;
            }

            // Determine if it's no payment methods or all cards failed
            $isNoPaymentMethods = in_array('No registered payment method found', $errors);
            $failureType = $isNoPaymentMethods ? '支払い方法が未登録' : '全支払い方法が失敗';

            // Message for guest
            $guestMessage = "⚠️ 延長時間の自動支払いが失敗しました。\n";
            $guestMessage .= "理由: {$failureType}\n";
            $guestMessage .= "必要なポイント: {$requiredAmountInPoints}pt\n";
            $guestMessage .= "利用可能ポイント: {$guestAvailablePoints}pt\n";
            if (!$isNoPaymentMethods) {
                $guestMessage .= "エラー: {$errorMessage}\n";
            }
            $guestMessage .= "手動での支払いが必要です。\n";
            if ($isNoPaymentMethods) {
                $guestMessage .= "支払い方法を登録してください。";
            } else {
                $guestMessage .= "支払い方法を確認し、再度お支払いください。";
            }

            // Message for cast
            $castMessage = "⚠️ ゲストの延長時間支払いが失敗しました。\n";
            $castMessage .= "理由: {$failureType}\n";
            $castMessage .= "ゲストが利用可能だったポイント: {$guestAvailablePoints}pt\n";
            $castMessage .= "必要なポイント: {$requiredAmountInPoints}pt\n";
            $castMessage .= "キャストは利用可能だったポイント分のみ受け取ります。";
            if (!$isNoPaymentMethods) {
                $castMessage .= "\nエラー: {$errorMessage}";
            }

            // Send guest message
            \App\Models\Message::create([
                'chat_id' => $chat->id,
                'sender_guest_id' => $guest->id,
                'recipient_type' => 'both',
                'message' => json_encode([
                    'type' => 'system',
                    'target' => 'guest',
                    'text' => $guestMessage
                ]),
                'is_read' => 0
            ]);

            // Send cast message
            \App\Models\Message::create([
                'chat_id' => $chat->id,
                'sender_cast_id' => $reservation->cast_id,
                'recipient_type' => 'both',
                'message' => json_encode([
                    'type' => 'system',
                    'target' => 'cast',
                    'text' => $castMessage
                ]),
                'is_read' => 0
            ]);

            // Broadcast the messages
            $lastMessage = \App\Models\Message::where('chat_id', $chat->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastMessage) {
                event(new \App\Events\MessageSent($lastMessage));
            }

            Log::info('Chat notifications sent for payment failure', [
                'guest_id' => $guest->id,
                'reservation_id' => $reservation->id,
                'cast_id' => $reservation->cast_id,
                'chat_id' => $chat->id,
                'guest_available_points' => $guestAvailablePoints,
                'required_points' => $requiredAmountInPoints
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send chat notifications', [
                'guest_id' => $guest->id,
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send cast notifications about payment failure and reduced earnings
     *
     * @param Guest $guest
     * @param \App\Models\Reservation|null $reservation
     * @param int $requiredAmountInPoints
     * @param array $errors
     * @return void
     */
    private function sendCastNotifications(Guest $guest, ?\App\Models\Reservation $reservation, int $requiredAmountInPoints, array $errors): void
    {
        if (!$reservation) {
            return;
        }

        try {
            $cast = \App\Models\Cast::find($reservation->cast_id);
            if (!$cast) {
                return;
            }

            $guestAvailablePoints = $guest->points ?? 0;
            $errorMessage = implode('; ', $errors);
            $isNoPaymentMethods = in_array('No registered payment method found', $errors);
            $failureType = $isNoPaymentMethods ? '支払い方法が未登録' : '全支払い方法が失敗';

            $message = "⚠️ ゲストの延長時間支払いが失敗しました。\n";
            $message .= "理由: {$failureType}\n";
            $message .= "ゲストが利用可能だったポイント: {$guestAvailablePoints}pt\n";
            $message .= "必要なポイント: {$requiredAmountInPoints}pt\n";
            $message .= "キャストは利用可能だったポイント分のみ受け取ります。";
            if (!$isNoPaymentMethods) {
                $message .= "\nエラー: {$errorMessage}";
            }

            // Send notification to cast using the notification service
            $notification = \App\Services\NotificationService::sendNotificationIfEnabled(
                $cast->id,
                'cast',
                'payments',
                'payment_failure',
                $message,
                $reservation->id,
                $cast->id
            );

            if ($notification) {
                // Broadcast the notification
                event(new \App\Events\NotificationSent($notification));
            }

            Log::info('Cast notification sent for payment failure', [
                'cast_id' => $cast->id,
                'guest_id' => $guest->id,
                'reservation_id' => $reservation->id,
                'guest_available_points' => $guestAvailablePoints,
                'required_points' => $requiredAmountInPoints,
                'notification_id' => $notification?->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send cast notifications', [
                'guest_id' => $guest->id,
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send guest notifications about payment failure
     *
     * @param Guest $guest
     * @param \App\Models\Reservation|null $reservation
     * @param int $requiredAmountInPoints
     * @param array $errors
     * @return void
     */
    private function sendGuestNotifications(Guest $guest, ?\App\Models\Reservation $reservation, int $requiredAmountInPoints, array $errors): void
    {
        try {
            $errorMessage = implode('; ', $errors);
            $guestAvailablePoints = $guest->points ?? 0;
            $isNoPaymentMethods = in_array('No registered payment method found', $errors);
            $failureType = $isNoPaymentMethods ? '支払い方法が未登録' : '全支払い方法が失敗';

            $message = "⚠️ 延長時間の自動支払いが失敗しました。\n";
            $message .= "理由: {$failureType}\n";
            $message .= "必要なポイント: {$requiredAmountInPoints}pt\n";
            $message .= "利用可能ポイント: {$guestAvailablePoints}pt\n";
            if (!$isNoPaymentMethods) {
                $message .= "エラー: {$errorMessage}\n";
            }
            $message .= "手動での支払いが必要です。\n";
            if ($isNoPaymentMethods) {
                $message .= "支払い方法を登録してください。";
            } else {
                $message .= "支払い方法を確認し、再度お支払いください。";
            }

            // Send notification to guest using the notification service
            $notification = \App\Services\NotificationService::sendNotificationIfEnabled(
                $guest->id,
                'guest',
                'payments',
                'payment_failure',
                $message,
                $reservation?->id,
                $reservation?->cast_id
            );

            if ($notification) {
                // Broadcast the notification
                event(new \App\Events\NotificationSent($notification));
            }

            Log::info('Guest notification sent for payment failure', [
                'guest_id' => $guest->id,
                'reservation_id' => $reservation?->id,
                'required_points' => $requiredAmountInPoints,
                'guest_available_points' => $guestAvailablePoints,
                'notification_id' => $notification?->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send guest notifications', [
                'guest_id' => $guest->id,
                'reservation_id' => $reservation?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
