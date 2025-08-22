<?php

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ChatUpdated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $chat;

    public function __construct(Chat $chat)
    {
        $this->chat = $chat;
    }

    public function broadcastOn()
    {
        $channels = [];
        
        // Broadcast to guest channel
        if ($this->chat->guest_id) {
            $channels[] = new Channel('guest.' . $this->chat->guest_id);
        }
        
        // Broadcast to cast channel
        if ($this->chat->cast_id) {
            $channels[] = new Channel('cast.' . $this->chat->cast_id);
        }
        
        return $channels;
    }

    public function broadcastAs()
    {
        return 'ChatUpdated';
    }
}



