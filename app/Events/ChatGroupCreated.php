<?php

namespace App\Events;

use App\Models\ChatGroup;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ChatGroupCreated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $chatGroup;

    public function __construct(ChatGroup $chatGroup)
    {
        $this->chatGroup = $chatGroup;
    }

    public function broadcastOn()
    {
        $channels = [];
        
        // Broadcast to guest channel if reservation exists
        if ($this->chatGroup->reservation && $this->chatGroup->reservation->guest_id) {
            $channels[] = new Channel('guest.' . $this->chatGroup->reservation->guest_id);
        }
        
        // Broadcast to cast channels
        if ($this->chatGroup->cast_ids) {
            foreach ($this->chatGroup->cast_ids as $castId) {
                $channels[] = new Channel('cast.' . $castId);
            }
        }
        
        return $channels;
    }

    public function broadcastAs()
    {
        return 'ChatGroupCreated';
    }
}



