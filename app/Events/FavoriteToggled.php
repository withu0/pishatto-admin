<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class FavoriteToggled implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $chatId;
    public $isFavorited;
    public $userId;

    public function __construct($chatId, $isFavorited, $userId)
    {
        $this->chatId = $chatId;
        $this->isFavorited = $isFavorited;
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new Channel('user.' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'FavoriteToggled';
    }
}



