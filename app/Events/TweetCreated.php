<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TweetCreated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $tweet;

    public function __construct($tweet)
    {
        $this->tweet = $tweet;
    }

    public function broadcastOn()
    {
        return new Channel('tweets');
    }
}
