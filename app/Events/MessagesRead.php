<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessagesRead implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $chatId;
    public $userId;
    public $userType;

    public function __construct($chatId, $userId, $userType)
    {
        $this->chatId = $chatId;
        $this->userId = $userId;
        $this->userType = $userType;
    }

    public function broadcastOn()
    {
        return new Channel('user.' . $this->userId);
    }
} 