<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class GroupMessageSent implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public $message;
    public $group_id;

    public function __construct(Message $message, $group_id)
    {
        $this->message = $message;
        $this->group_id = $group_id;
        
        // Log the event construction
        \Log::info('GroupMessageSent: Event constructed', [
            'message_id' => $message->id,
            'group_id' => $group_id,
            'channel' => 'group.' . $group_id,
            'event_name' => 'GroupMessageSent'
        ]);
    }

    public function broadcastOn()
    {
        return new Channel('group.' . $this->group_id);
    }

    public function broadcastAs()
    {
        return 'GroupMessageSent';
    }
} 