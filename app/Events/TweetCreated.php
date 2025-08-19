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
        $channels = [new Channel('tweets')];
        
        // Also broadcast to user channels for notifications
        // Get all users who should be notified about new tweets
        
        // If it's a guest tweet, notify all casts
        if ($this->tweet->guest_id) {
            $casts = \App\Models\Cast::pluck('id')->toArray();
            foreach ($casts as $castId) {
                $channels[] = new Channel('user.' . $castId);
            }
        }
        
        // If it's a cast tweet, notify all guests
        if ($this->tweet->cast_id) {
            $guests = \App\Models\Guest::pluck('id')->toArray();
            foreach ($guests as $guestId) {
                $channels[] = new Channel('user.' . $guestId);
            }
        }
        
        return $channels;
    }
}
