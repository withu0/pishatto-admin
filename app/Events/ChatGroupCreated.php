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
    public $groupData;

    public function __construct(ChatGroup $chatGroup)
    {
        $this->chatGroup = $chatGroup;
        
        // Prepare comprehensive group data for real-time updates
        $this->groupData = [
            'id' => $chatGroup->id,
            'name' => $chatGroup->name,
            'reservation_id' => $chatGroup->reservation_id,
            'cast_ids' => $chatGroup->cast_ids,
            'created_at' => $chatGroup->created_at,
            'reservation' => $chatGroup->reservation ? [
                'id' => $chatGroup->reservation->id,
                'guest_id' => $chatGroup->reservation->guest_id,
                'scheduled_at' => $chatGroup->reservation->scheduled_at,
                'location' => $chatGroup->reservation->location,
                'duration' => $chatGroup->reservation->duration,
            ] : null,
        ];
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

    public function broadcastWith()
    {
        return [
            'chatGroup' => $this->groupData
        ];
    }
}



