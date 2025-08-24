<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageSent implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        $channels = [new Channel('chat.' . $this->message->chat_id)];
        
        // Also broadcast to user channels for real-time notifications
        if ($this->message->sender_guest_id) {
            // Message from guest, notify cast only if message is for cast or both
            $chat = $this->message->chat;
            if ($chat && $chat->cast_id && 
                ($this->message->recipient_type === 'both' || $this->message->recipient_type === 'cast')) {
                $channels[] = new Channel('user.' . $chat->cast_id);
            }
        } else if ($this->message->sender_cast_id) {
            // Message from cast, notify guest only if message is for guest or both
            $chat = $this->message->chat;
            if ($chat && $chat->guest_id && 
                ($this->message->recipient_type === 'both' || $this->message->recipient_type === 'guest')) {
                $channels[] = new Channel('user.' . $chat->guest_id);
            }
        }
        
        return $channels;
    }
}
