<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AdminNewsPublished implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $news;

    public function __construct($news)
    {
        $this->news = $news;
    }

    public function broadcastOn()
    {
        $channelName = 'admin-news';
        if ($this->news->target_type === 'guest') {
            $channelName = 'admin-news.guest';
        } elseif ($this->news->target_type === 'cast') {
            $channelName = 'admin-news.cast';
        }
        
        return new Channel($channelName);
    }

    public function broadcastAs()
    {
        return 'AdminNewsPublished';
    }
} 