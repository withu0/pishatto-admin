<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;

class MessageSent implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;

        // Log message event creation
        Log::info('MessageSent: Event created', [
            'message_id' => $message->id,
            'chat_id' => $message->chat_id,
            'sender_guest_id' => $message->sender_guest_id,
            'sender_cast_id' => $message->sender_cast_id,
            'recipient_type' => $message->recipient_type,
            'message_preview' => substr($message->message ?? '', 0, 50),
            'has_image' => !is_null($message->image),
            'has_gift' => !is_null($message->gift_id),
            'timestamp' => now()->toISOString()
        ]);
    }

    public function broadcastOn()
    {
        $channels = [new Channel('chat.' . $this->message->chat_id)];

        // Log channel determination
        Log::info('MessageSent: Determining broadcast channels', [
            'message_id' => $this->message->id,
            'chat_id' => $this->message->chat_id,
            'sender_guest_id' => $this->message->sender_guest_id,
            'sender_cast_id' => $this->message->sender_cast_id,
            'recipient_type' => $this->message->recipient_type
        ]);

        // Also broadcast to user channels for real-time notifications
        if ($this->message->sender_guest_id) {
            // Message from guest, notify cast only if message is for cast or both
            $chat = $this->message->chat;
            if ($chat && $chat->cast_id &&
                ($this->message->recipient_type === 'both' || $this->message->recipient_type === 'cast')) {
                $channels[] = new Channel('user.' . $chat->cast_id);
                Log::info('MessageSent: Added cast user channel', [
                    'message_id' => $this->message->id,
                    'cast_id' => $chat->cast_id,
                    'channel' => 'user.' . $chat->cast_id
                ]);
            }
        } else if ($this->message->sender_cast_id) {
            // Message from cast, notify guest only if message is for guest or both
            $chat = $this->message->chat;
            if ($chat && $chat->guest_id &&
                ($this->message->recipient_type === 'both' || $this->message->recipient_type === 'guest')) {
                $channels[] = new Channel('user.' . $chat->guest_id);
                Log::info('MessageSent: Added guest user channel', [
                    'message_id' => $this->message->id,
                    'guest_id' => $chat->guest_id,
                    'channel' => 'user.' . $chat->guest_id
                ]);
            }
        }

        // Log final channels
        $channelNames = array_map(function($channel) {
            return $channel->name;
        }, $channels);

        Log::info('MessageSent: Broadcasting to channels', [
            'message_id' => $this->message->id,
            'channels' => $channelNames,
            'total_channels' => count($channels)
        ]);

        return $channels;
    }

    public function broadcastAs()
    {
        return 'MessageSent';
    }

    public function broadcastWith()
    {
        Log::info('MessageSent: Preparing broadcast data', [
            'message_id' => $this->message->id,
            'data_size' => strlen(json_encode($this->message->toArray()))
        ]);

        return [
            'message' => $this->message->load(['guest', 'cast', 'gift'])->toArray()
        ];
    }
}
