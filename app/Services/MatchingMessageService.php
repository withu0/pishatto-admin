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
     * Send automatic matching confirmation messages to guest and cast only (no group chat message)
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

            // Format the reservation details for meeting time
            $reservationDate = Carbon::parse($reservation->scheduled_at);
            $meetingTime = $reservationDate->format('H:i');
            
            // Send individual matching confirmation messages to guest and cast only
            $this->sendIndividualMatchingMessages($reservation, $castId, $meetingTime);
            
            Log::info('Individual matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'guest_id' => $reservation->guest_id,
                'meeting_time' => $meetingTime
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
     * Send individual matching confirmation messages to guest and cast
     */
    private function sendIndividualMatchingMessages(Reservation $reservation, int $castId, string $meetingTime): void
    {
        try {
            // Find or create individual chats for guest and cast
            $guestChat = $this->findOrCreateGuestChat($reservation->guest_id, $castId, $reservation->id);
            $castChat = $this->findOrCreateCastChat($reservation->guest_id, $castId, $reservation->id);
            
            if ($guestChat) {
                // Send message to guest
                $guestMessage = "マッチングが成立しました。合流時間は{$meetingTime}となります。キャストの合流ボタン押下後、マッチング開始となります。";
                $this->sendToChat($guestChat->id, $guestMessage);
            }
            
            if ($castChat) {
                // Send message to cast
                $castMessage = "マッチングが成立しました。合流時間は{$meetingTime}となります。ゲストと合流する直前に合流ボタンを必ず押下してください。また大幅な遅刻等はマナー違反です。合流時間に従って行動するようにしてください。";
                $this->sendToChat($castChat->id, $castMessage);
            }
            
            Log::info('Individual matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'guest_id' => $reservation->guest_id,
                'guest_chat_id' => $guestChat?->id,
                'cast_chat_id' => $castChat?->id,
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
     * Send message to individual chat
     */
    private function sendToChat(int $chatId, string $message): void
    {
        Message::create([
            'chat_id' => $chatId,
            'message' => $message,
            'created_at' => now(),
            'is_read' => false,
        ]);
    }

    /**
     * Send matching message for multiple cast approvals (only individual messages, no group message)
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

            // Format the reservation details for meeting time
            $reservationDate = Carbon::parse($reservation->scheduled_at);
            $meetingTime = $reservationDate->format('H:i');
            
            // Send individual messages to guest and each cast only (no group message)
            $this->sendMultipleIndividualMatchingMessages($reservation, $castIds, $meetingTime);
            
            Log::info('Multiple individual matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_count' => count($castIds),
                'guest_id' => $reservation->guest_id,
                'meeting_time' => $meetingTime
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
     * Send individual matching confirmation messages for multiple casts
     */
    private function sendMultipleIndividualMatchingMessages(Reservation $reservation, array $castIds, string $meetingTime): void
    {
        try {
            // Send message to guest
            $guestChat = $this->findOrCreateGuestChat($reservation->guest_id, $castIds[0], $reservation->id);
            if ($guestChat) {
                $guestMessage = "マッチングが成立しました。合流時間は{$meetingTime}となります。キャストの合流ボタン押下後、マッチング開始となります。";
                $this->sendToChat($guestChat->id, $guestMessage);
            }
            
            // Send message to each cast
            foreach ($castIds as $castId) {
                $castChat = $this->findOrCreateCastChat($reservation->guest_id, $castId, $reservation->id);
                if ($castChat) {
                    $castMessage = "マッチングが成立しました。合流時間は{$meetingTime}となります。ゲストと合流する直前に合流ボタンを必ず押下してください。また大幅な遅刻等はマナー違反です。合流時間に従って行動するようにしてください。";
                    $this->sendToChat($castChat->id, $castMessage);
                }
            }
            
            Log::info('Multiple individual matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_ids' => $castIds,
                'guest_id' => $reservation->guest_id,
                'meeting_time' => $meetingTime
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send multiple individual matching messages', [
                'reservation_id' => $reservation->id,
                'cast_ids' => $castIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send matching message when additional cast joins existing group (only individual messages, no group message)
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

            // Format the reservation details for meeting time
            $reservationDate = Carbon::parse($reservation->scheduled_at);
            $meetingTime = $reservationDate->format('H:i');
            
            // Send individual matching confirmation messages to guest and cast only (no group message)
            $this->sendIndividualMatchingMessages($reservation, $castId, $meetingTime);
            
            Log::info('Additional cast individual matching messages sent successfully', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'guest_id' => $reservation->guest_id,
                'meeting_time' => $meetingTime
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
