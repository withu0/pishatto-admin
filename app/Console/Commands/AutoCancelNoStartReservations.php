<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Reservation;
use App\Models\ReservationApplication;
use App\Models\CastSession;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Notification;
use Carbon\Carbon;

class AutoCancelNoStartReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:auto-cancel-no-start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto cancel reservations where cast did not start timer within 1 hour of scheduled time';

    public function handle(): int
    {
        // Use Asia/Tokyo timezone for consistency
        $tz = 'Asia/Tokyo';
        $now = now($tz);
        $gracePeriodEnd = $now->copy()->subHour(); // 1 hour ago

        // Find reservations that:
        // 1. Are still active
        // 2. Have scheduled_at set
        // 3. scheduled_at + 1 hour has passed
        // 4. Not already cancelled
        // 5. Not already ended
        $candidates = Reservation::where('active', true)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $gracePeriodEnd)
            ->whereNull('cancelled_at')
            ->whereNull('ended_at')
            ->get();

        $processed = 0;
        $cancelled = 0;

        foreach ($candidates as $reservation) {
            DB::beginTransaction();
            try {
                // Refresh to avoid stale data
                $reservation->refresh();
                
                // Double-check conditions after refresh
                if (!$reservation->active || $reservation->cancelled_at || $reservation->ended_at) {
                    DB::rollBack();
                    continue;
                }

                $shouldCancel = false;
                $reason = '';

                if ($reservation->type === 'Pishatto') {
                    // For Pishatto: check if started_at is null
                    if (!$reservation->started_at) {
                        $shouldCancel = true;
                        $reason = 'キャストがタイマーを開始しませんでした（Pishatto）';
                    }
                } elseif ($reservation->type === 'free') {
                    // For free: check if any approved cast hasn't started a session
                    $approvedCasts = ReservationApplication::where('reservation_id', $reservation->id)
                        ->where('status', 'approved')
                        ->pluck('cast_id')
                        ->toArray();

                    if (empty($approvedCasts)) {
                        // No approved casts, skip
                        DB::rollBack();
                        continue;
                    }

                    // Check if any approved cast hasn't started a session
                    $startedCasts = CastSession::where('reservation_id', $reservation->id)
                        ->whereIn('cast_id', $approvedCasts)
                        ->whereNotNull('started_at')
                        ->pluck('cast_id')
                        ->toArray();

                    $notStartedCasts = array_diff($approvedCasts, $startedCasts);

                    if (!empty($notStartedCasts)) {
                        $shouldCancel = true;
                        $castCount = count($notStartedCasts);
                        $reason = "承認済みキャスト{$castCount}名がタイマーを開始しませんでした（free）";
                    }
                }

                if ($shouldCancel) {
                    // Calculate next business day at 23:59 in Asia/Tokyo timezone
                    $nextBusinessDay = $this->calculateNextBusinessDay($now);
                    $scheduledRefundAt = $nextBusinessDay->copy()->setTime(23, 59, 0)->setTimezone($tz);

                    // Cancel the reservation
                    $reservation->active = false;
                    $reservation->cancelled_at = $now;
                    $reservation->cancellation_reason = $reason;
                    $reservation->scheduled_refund_at = $scheduledRefundAt;
                    $reservation->save();

                    DB::commit();
                    $cancelled++;

                    \Log::info('Auto-cancelled reservation due to no timer start', [
                        'reservation_id' => $reservation->id,
                        'type' => $reservation->type,
                        'scheduled_at' => $reservation->scheduled_at,
                        'cancelled_at' => $reservation->cancelled_at,
                        'scheduled_refund_at' => $scheduledRefundAt,
                        'reason' => $reason,
                    ]);

                    // Send cancellation messages and notifications
                    $this->sendCancellationNotifications($reservation, $reason);

                    // Broadcast the reservation update event
                    event(new \App\Events\ReservationUpdated($reservation));
                } else {
                    DB::rollBack();
                }

                $processed++;
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('AutoCancelNoStartReservations failed for reservation', [
                    'reservation_id' => $reservation->id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Processed {$processed} reservations, cancelled {$cancelled}.");
        return Command::SUCCESS;
    }

    /**
     * Calculate the next business day (skipping weekends)
     * 
     * @param Carbon $date
     * @return Carbon
     */
    private function calculateNextBusinessDay(Carbon $date): Carbon
    {
        $nextDay = $date->copy()->addDay();
        
        // Skip weekends (Saturday = 6, Sunday = 0)
        while ($nextDay->isWeekend()) {
            $nextDay->addDay();
        }
        
        return $nextDay;
    }

    /**
     * Send cancellation messages to chat and notifications to users
     * 
     * @param Reservation $reservation
     * @param string $reason
     * @return void
     */
    private function sendCancellationNotifications(Reservation $reservation, string $reason): void
    {
        try {
            // Format scheduled time for messages
            $scheduledTime = $reservation->scheduled_at 
                ? Carbon::parse($reservation->scheduled_at)->setTimezone('Asia/Tokyo')->format('Y年m月d日 H:i')
                : '未設定';

            // Create cancellation message
            $cancellationMessage = "【予約キャンセル】\n\n予約がキャンセルされました。\n\n📅 予定時間: {$scheduledTime}\n❌ キャンセル理由: {$reason}\n\nポイントは次営業日の23:59に自動返金されます。";

            // Find all chats for this reservation
            $chats = Chat::where('reservation_id', $reservation->id)->get();

            if ($chats->isEmpty()) {
                \Log::warning('No chats found for cancelled reservation', [
                    'reservation_id' => $reservation->id
                ]);
            } else {
                // For group chats (free reservations), send one message per group
                // For individual chats (Pishatto), send one message per chat
                $processedGroups = [];

                foreach ($chats as $chat) {
                    try {
                        // Skip if we've already sent a message to this group
                        if ($chat->group_id && isset($processedGroups[$chat->group_id])) {
                            continue;
                        }

                        $message = Message::create([
                            'chat_id' => $chat->id,
                            'message' => $cancellationMessage,
                            'recipient_type' => 'both',
                            'is_read' => false,
                            'created_at' => now(),
                        ]);

                        // Load relationships for broadcasting
                        $message->load(['guest', 'cast', 'gift']);

                        // Broadcast message based on chat type
                        if ($chat->group_id) {
                            // Group chat (free reservation) - send once per group
                            event(new \App\Events\GroupMessageSent($message, $chat->group_id));
                            $processedGroups[$chat->group_id] = true;
                        } else {
                            // Individual chat (Pishatto reservation)
                            event(new \App\Events\MessageSent($message));
                        }

                        \Log::info('Cancellation message sent to chat', [
                            'reservation_id' => $reservation->id,
                            'chat_id' => $chat->id,
                            'group_id' => $chat->group_id,
                            'message_id' => $message->id
                        ]);
                    } catch (\Throwable $e) {
                        \Log::error('Failed to send cancellation message to chat', [
                            'reservation_id' => $reservation->id,
                            'chat_id' => $chat->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Send notification to guest
            if ($reservation->guest_id) {
                try {
                    $guestNotification = Notification::create([
                        'user_id' => $reservation->guest_id,
                        'user_type' => 'guest',
                        'type' => 'reservation_cancelled',
                        'reservation_id' => $reservation->id,
                        'message' => "予約がキャンセルされました。\n\n📅 予定時間: {$scheduledTime}\n❌ 理由: {$reason}",
                        'read' => false,
                    ]);

                    event(new \App\Events\NotificationSent($guestNotification));

                    \Log::info('Cancellation notification sent to guest', [
                        'reservation_id' => $reservation->id,
                        'guest_id' => $reservation->guest_id,
                        'notification_id' => $guestNotification->id
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('Failed to send cancellation notification to guest', [
                        'reservation_id' => $reservation->id,
                        'guest_id' => $reservation->guest_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Send notifications to all casts involved
            $castIds = [];

            // For Pishatto: get cast_id from reservation
            if ($reservation->type === 'Pishatto' && $reservation->cast_id) {
                $castIds[] = $reservation->cast_id;
            }

            // For free: get all approved casts
            if ($reservation->type === 'free') {
                $approvedCasts = ReservationApplication::where('reservation_id', $reservation->id)
                    ->where('status', 'approved')
                    ->pluck('cast_id')
                    ->toArray();
                $castIds = array_merge($castIds, $approvedCasts);
            }

            // Also get cast_ids from chats
            foreach ($chats as $chat) {
                if ($chat->cast_id && !in_array($chat->cast_id, $castIds)) {
                    $castIds[] = $chat->cast_id;
                }
            }

            // Remove duplicates
            $castIds = array_unique($castIds);

            // Send notification to each cast
            foreach ($castIds as $castId) {
                try {
                    $castNotification = Notification::create([
                        'user_id' => $castId,
                        'user_type' => 'cast',
                        'type' => 'reservation_cancelled',
                        'reservation_id' => $reservation->id,
                        'cast_id' => $castId,
                        'message' => "予約がキャンセルされました。\n\n📅 予定時間: {$scheduledTime}\n❌ 理由: {$reason}",
                        'read' => false,
                    ]);

                    event(new \App\Events\NotificationSent($castNotification));

                    \Log::info('Cancellation notification sent to cast', [
                        'reservation_id' => $reservation->id,
                        'cast_id' => $castId,
                        'notification_id' => $castNotification->id
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('Failed to send cancellation notification to cast', [
                        'reservation_id' => $reservation->id,
                        'cast_id' => $castId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

        } catch (\Throwable $e) {
            \Log::error('Failed to send cancellation notifications', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
