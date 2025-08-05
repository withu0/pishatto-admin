<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class GroupMessageSent implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $message;
    public $group_id;

    public function __construct(Message $message, $group_id)
    {
        $this->message = $message;
        $this->group_id = $group_id;
    }

    public function broadcastOn()
    {
        return new Channel('group.' . $this->group_id);
    }
} 