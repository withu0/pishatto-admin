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
     * Send automatic matching information message to group chat only
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

            // Format the reservation details
            $reservationDate = Carbon::parse($reservation->scheduled_at);
            $formattedDate = $reservationDate->format('Y年m月d日');
            $formattedTime = $reservationDate->format('H:i');
            
            // Create the matching information message
            $matchingMessage = $this->createMatchingMessage($reservation, $cast, $guest, $formattedDate, $formattedTime);
            
            // Only send message to the group chat, not individual chats
            if ($groupId) {
                $this->sendToGroup($groupId, $matchingMessage);
            }
            
            Log::info('Matching message sent to group chat successfully', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'guest_id' => $reservation->guest_id,
                'group_id' => $groupId,
                'message' => $matchingMessage
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
     * Create the matching information message content
     */
    private function createMatchingMessage(Reservation $reservation, Cast $cast, Guest $guest, string $formattedDate, string $formattedTime): string
    {
        $location = $reservation->location ?? '未設定';
        $duration = $reservation->duration ?? 0;
        $durationText = $duration > 0 ? "{$duration}時間" : '未設定';
        $type = $this->getReservationTypeText($reservation->type);
        
        $message = "🎉 新しいキャストが参加しました！\n\n";
        $message .= "📅 予約日: {$formattedDate}\n";
        $message .= "🕐 予約時間: {$formattedTime}\n";
        $message .= "📍 場所: {$location}\n";
        $message .= "⏱️ 時間: {$durationText}\n";
        $message .= "🎭 サービス: {$type}\n";
        $message .= "👤 参加キャスト: {$cast->nickname}\n\n";
        
        if ($reservation->details) {
            $message .= "📝 詳細:\n{$reservation->details}\n\n";
        }
        
        $message .= "✨ 素敵な時間をお過ごしください！";
        
        return $message;
    }

    /**
     * Get Japanese text for reservation type
     */
    private function getReservationTypeText(?string $type): string
    {
        return match($type) {
            'pishatto' => 'ピシャット',
            'free' => 'フリーコール',
            default => '予約'
        };
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
     * Send message to group chat
     */
    private function sendToGroup(int $groupId, string $message): void
    {
        // Find the first chat in the group to use as the message target
        $chat = Chat::where('group_id', $groupId)->first();
        
        if ($chat) {
            Message::create([
                'chat_id' => $chat->id,
                'message' => $message,
                'created_at' => now(),
                'is_read' => false,
            ]);
        }
    }

    /**
     * Send matching message for multiple cast approvals
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

            // Format the reservation details
            $reservationDate = Carbon::parse($reservation->scheduled_at);
            $formattedDate = $reservationDate->format('Y年m月d日');
            $formattedTime = $reservationDate->format('H:i');
            
            // Send only one group message for multiple cast matching
            $groupMessage = $this->createMultipleMatchingMessage($reservation, $guest, $formattedDate, $formattedTime, count($castIds));
            $this->sendToGroup($groupId, $groupMessage);
            
            Log::info('Multiple cast matching message sent to group chat successfully', [
                'reservation_id' => $reservation->id,
                'cast_count' => count($castIds),
                'guest_id' => $reservation->guest_id,
                'group_id' => $groupId,
                'group_message' => $groupMessage
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
     * Create the multiple matching message content
     */
    private function createMultipleMatchingMessage(Reservation $reservation, Guest $guest, string $formattedDate, string $formattedTime, int $castCount): string
    {
        $location = $reservation->location ?? '未設定';
        $duration = $reservation->duration ?? 0;
        $durationText = $duration > 0 ? "{$duration}時間" : '未設定';
        $type = $this->getReservationTypeText($reservation->type);
        
        $message = "🎉 グループチャットが作成されました！\n\n";
        $message .= "📅 予約日: {$formattedDate}\n";
        $message .= "🕐 予約時間: {$formattedTime}\n";
        $message .= "📍 場所: {$location}\n";
        $message .= "⏱️ 時間: {$durationText}\n";
        $message .= "🎭 サービス: {$type}\n";
        $message .= "👥 参加キャスト数: {$castCount}名\n\n";
        
        if ($reservation->details) {
            $message .= "📝 詳細:\n{$reservation->details}\n\n";
        }
        
        $message .= "✨ 素敵な時間をお過ごしください！";
        
        return $message;
    }

    /**
     * Send matching message when additional cast joins existing group
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

            // Format the reservation details
            $reservationDate = Carbon::parse($reservation->scheduled_at);
            $formattedDate = $reservationDate->format('Y年m月d日');
            $formattedTime = $reservationDate->format('H:i');
            
            // Create the matching information message for this additional cast
            $matchingMessage = $this->createMatchingMessage($reservation, $cast, $guest, $formattedDate, $formattedTime);
            
            // Send message to the group chat only
            $this->sendToGroup($groupId, $matchingMessage);
            
            Log::info('Additional cast matching message sent to group chat successfully', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'guest_id' => $reservation->guest_id,
                'group_id' => $groupId,
                'message' => $matchingMessage
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
