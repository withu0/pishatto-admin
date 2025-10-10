<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;

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
        Log::info('GroupMessageSent: Event constructed', [
            'message_id' => $message->id,
            'group_id' => $group_id,
            'channel' => 'group.' . $group_id,
            'event_name' => 'GroupMessageSent'
        ]);
    }

    public function broadcastOn()
    {
        $channel = new Channel('group.' . $this->group_id);

        Log::info('GroupMessageSent: Broadcasting to channel', [
            'message_id' => $this->message->id,
            'group_id' => $this->group_id,
            'channel' => 'group.' . $this->group_id,
            'message_preview' => substr($this->message->message ?? '', 0, 50),
            'sender_guest_id' => $this->message->sender_guest_id,
            'sender_cast_id' => $this->message->sender_cast_id
        ]);

        return $channel;
    }

    public function broadcastAs()
    {
        return 'GroupMessageSent';
    }

    public function broadcastWith()
    {
        Log::info('GroupMessageSent: Preparing broadcast data', [
            'message_id' => $this->message->id,
            'group_id' => $this->group_id,
            'data_size' => strlen(json_encode($this->message->toArray()))
        ]);

        return [
            'message' => $this->message->load(['guest', 'cast', 'gift'])->toArray(),
            'group_id' => $this->group_id
        ];
    }
}
