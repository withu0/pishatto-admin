<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Chat;
use App\Models\ChatGroup;
use App\Models\Reservation;
use App\Models\Cast;
use App\Models\Guest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MatchingMessageService
{
    /**
     * Send automatic matching confirmation messages to group chat instead of individual chats
     */
    public function sendMatchingMessage(Reservation $reservation, int $castId, ?int $chatId = null, ?int $groupId = null)
    {
        try {
            // Get the cast and guest information
            $cast = Cast::find($castId);
            $guest = Guest::find($reservation->guest_id);

            if (!$cast || !$guest) {
                Log::error('Failed to send matching message: Cast or Guest not found', [
                    'reservation_id' => $reservation->id,
                    'cast_id' => $castId,
                    'guest_id' => $reservation->guest_id
                ]);
                return false;
            }

            // Format the reservation details for meeting time (convert from UTC to JST)
            $reservationDate = Carbon::parse($reservation->scheduled_at)->setTimezone('Asia/Tokyo');
            $meetingTime = $reservationDate->format('H:i');

            // Send matching confirmation messages to group chat
            $this->sendGroupMatchingMessages($reservation, $castId, $meetingTime, $groupId);

            Log::info('Group matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'guest_id' => $reservation->guest_id,
                'meeting_time' => $meetingTime,
                'group_id' => $groupId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send matching message', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send matching confirmation messages to group chat
     */
    private function sendGroupMatchingMessages(Reservation $reservation, int $castId, string $meetingTime, ?int $groupId = null): void
    {
        try {
            // Find the chat group and a chat within it to send the message
            $chat = null;
            if ($groupId) {
                $chat = Chat::where('group_id', $groupId)->first();
            }

            if (!$chat) {
                // Fallback to finding a chat for this reservation
                $chat = Chat::where('reservation_id', $reservation->id)->first();
            }

            if ($chat && $chat->group_id) {
                // Send message to guest only in group chat
                $guestMessage = "マッチングが成立しました。合流時間は{$meetingTime}となります。キャストの合流ボタン押下後、マッチング開始となります。";
                $this->sendToGroupChat($chat->group_id, $guestMessage, 'guest');

                // Send message to cast only in group chat
                $castMessage = "マッチングが成立しました。合流時間は{$meetingTime}となります。ゲストと合流する直前に合流ボタンを必ず押下してください。また大幅な遅刻等はマナー違反です。合流時間に従って行動するようにしてください。";
                $this->sendToGroupChat($chat->group_id, $castMessage, 'cast');
            } else {
                // Fallback to individual chat if no group found
                $this->sendIndividualMatchingMessages($reservation, $castId, $meetingTime, null);
            }

            Log::info('Group matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'guest_id' => $reservation->guest_id,
                'group_id' => $chat?->group_id,
                'meeting_time' => $meetingTime
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send group matching messages', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send message to group chat with recipient type
     */
    private function sendToGroupChat(int $groupId, string $message, string $recipientType = 'both'): void
    {
        // Find a chat within the group to use as the target chat
        $targetChat = Chat::where('group_id', $groupId)->first();

        if ($targetChat) {
            $messageRecord = Message::create([
                'chat_id' => $targetChat->id,
                'message' => $message,
                'recipient_type' => $recipientType,
                'created_at' => now(),
                'is_read' => false,
            ]);

            // Broadcast the message in real-time
            event(new \App\Events\GroupMessageSent($messageRecord, $groupId));
        }
    }

    /**
     * Send individual matching confirmation messages to guest and cast (fallback method)
     */
    private function sendIndividualMatchingMessages(Reservation $reservation, int $castId, string $meetingTime, ?int $chatId = null): void
    {
        try {
            // Use provided chat ID or find/create chat
            $chat = null;
            if ($chatId) {
                $chat = Chat::find($chatId);
            }

            if (!$chat) {
                // Find or create individual chats for guest and cast
                $chat = $this->findOrCreateGuestChat($reservation->guest_id, $castId, $reservation->id);
            }

            if ($chat) {
                // Send message to guest only
                $guestMessage = "マッチングが成立しました。合流時間は{$meetingTime}となります。キャストの合流ボタン押下後、マッチング開始となります。";
                $this->sendToChat($chat->id, $guestMessage, 'guest');

                // Send message to cast only
                $castMessage = "マッチングが成立しました。合流時間は{$meetingTime}となります。ゲストと合流する直前に合流ボタンを必ず押下してください。また大幅な遅刻等はマナー違反です。合流時間に従って行動するようにしてください。";
                $this->sendToChat($chat->id, $castMessage, 'cast');
            }

            Log::info('Individual matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'guest_id' => $reservation->guest_id,
                'chat_id' => $chat?->id,
                'meeting_time' => $meetingTime
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send individual matching messages', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Find or create a chat for the guest
     */
    private function findOrCreateGuestChat(int $guestId, int $castId, int $reservationId): ?Chat
    {
        // Try to find existing chat
        $chat = Chat::where('guest_id', $guestId)
                   ->where('cast_id', $castId)
                   ->where('reservation_id', $reservationId)
                   ->first();

        if ($chat) {
            return $chat;
        }

        // Create new chat if not found
        try {
            $chat = Chat::create([
                'guest_id' => $guestId,
                'cast_id' => $castId,
                'reservation_id' => $reservationId,
                'created_at' => now(),
            ]);

            // Broadcast chat creation
            event(new \App\Events\ChatCreated($chat));

            return $chat;
        } catch (\Exception $e) {
            Log::error('Failed to create guest chat', [
                'guest_id' => $guestId,
                'cast_id' => $castId,
                'reservation_id' => $reservationId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find or create a chat for the cast
     */
    private function findOrCreateCastChat(int $guestId, int $castId, int $reservationId): ?Chat
    {
        // For cast, we can use the same chat as guest since it's a 1-to-1 chat
        return $this->findOrCreateGuestChat($guestId, $castId, $reservationId);
    }

    /**
     * Send message to individual chat with recipient type
     */
    private function sendToChat(int $chatId, string $message, string $recipientType = 'both'): void
    {
        $messageRecord = Message::create([
            'chat_id' => $chatId,
            'message' => $message,
            'recipient_type' => $recipientType,
            'created_at' => now(),
            'is_read' => false,
        ]);

        // Broadcast the message in real-time
        event(new \App\Events\MessageSent($messageRecord));
    }

    /**
     * Send matching message for multiple cast approvals (to group chat)
     */
    public function sendMultipleMatchingMessage(Reservation $reservation, array $castIds, int $groupId)
    {
        try {
            $guest = Guest::find($reservation->guest_id);

            if (!$guest) {
                Log::error('Failed to send multiple matching message: Guest not found', [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $reservation->guest_id
                ]);
                return false;
            }

            // Format the reservation details for meeting time (convert from UTC to JST)
            $reservationDate = Carbon::parse($reservation->scheduled_at)->setTimezone('Asia/Tokyo');
            $meetingTime = $reservationDate->format('H:i');

            // Send group messages to guest and each cast
            $this->sendMultipleGroupMatchingMessages($reservation, $castIds, $meetingTime, $groupId);

            Log::info('Multiple group matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_count' => count($castIds),
                'guest_id' => $reservation->guest_id,
                'meeting_time' => $meetingTime,
                'group_id' => $groupId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send multiple matching message', [
                'reservation_id' => $reservation->id,
                'cast_ids' => $castIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send group matching confirmation messages for multiple casts
     */
    private function sendMultipleGroupMatchingMessages(Reservation $reservation, array $castIds, string $meetingTime, int $groupId): void
    {
        try {
            // Send message to guest only in group chat
            $guestMessage = "マッチングが成立しました。合流時間は{$meetingTime}となります。キャストの合流ボタン押下後、マッチング開始となります。";
            $this->sendToGroupChat($groupId, $guestMessage, 'guest');

            // Send message to each cast only in group chat
            foreach ($castIds as $castId) {
                $castMessage = "マッチングが成立しました。合流時間は{$meetingTime}となります。ゲストと合流する直前に合流ボタンを必ず押下してください。また大幅な遅刻等はマナー違反です。合流時間に従って行動するようにしてください。";
                $this->sendToGroupChat($groupId, $castMessage, 'cast');
            }

            Log::info('Multiple group matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_ids' => $castIds,
                'guest_id' => $reservation->guest_id,
                'group_id' => $groupId,
                'meeting_time' => $meetingTime
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send multiple group matching messages', [
                'reservation_id' => $reservation->id,
                'cast_ids' => $castIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send matching message when additional cast joins existing group (to group chat)
     */
    public function sendAdditionalCastMatchingMessage(Reservation $reservation, int $castId, int $groupId)
    {
        try {
            // Get the cast and guest information
            $cast = Cast::find($castId);
            $guest = Guest::find($reservation->guest_id);

            if (!$cast || !$guest) {
                Log::error('Failed to send additional cast matching message: Cast or Guest not found', [
                    'reservation_id' => $reservation->id,
                    'cast_id' => $castId,
                    'guest_id' => $reservation->guest_id
                ]);
                return false;
            }

            // Format the reservation details for meeting time (convert from UTC to JST)
            $reservationDate = Carbon::parse($reservation->scheduled_at)->setTimezone('Asia/Tokyo');
            $meetingTime = $reservationDate->format('H:i');

            // Send group matching confirmation messages to guest and cast
            $this->sendGroupMatchingMessages($reservation, $castId, $meetingTime, $groupId);

            Log::info('Additional cast group matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'guest_id' => $reservation->guest_id,
                'meeting_time' => $meetingTime,
                'group_id' => $groupId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send additional cast matching message', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
