<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ChatListUpdated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $userId;
    public $userType;
    public $chatData;

    public function __construct($userId, $userType, $chatData)
    {
        $this->userId = $userId;
        $this->userType = $userType;
        
        // Format chat data based on user type
        if ($chatData instanceof \App\Models\Chat) {
            // Load relationships for broadcasting
            $chatData->load(['guest', 'cast', 'group']);
            
            $guest = $chatData->guest;
            $cast = $chatData->cast;
            $group = $chatData->group;
            
            $this->chatData = [
                'id' => $chatData->id,
                'guest_id' => $chatData->guest_id,
                'cast_id' => $chatData->cast_id,
                'reservation_id' => $chatData->reservation_id,
                'group_id' => $chatData->group_id,
                'created_at' => $chatData->created_at,
                'updated_at' => $chatData->created_at,
                
                // Guest-side data
                'guest' => $guest ? [
                    'id' => $guest->id,
                    'nickname' => $guest->nickname,
                    'avatar' => $guest->avatar,
                ] : null,
                'cast' => $cast ? [
                    'id' => $cast->id,
                    'nickname' => $cast->nickname,
                    'avatar' => $cast->avatar,
                ] : null,
                
                // Cast-side data (for cast UI)
                'guest_nickname' => $guest ? $guest->nickname : 'Unknown Guest',
                'cast_nickname' => $cast ? $cast->nickname : 'Unknown Cast',
                'name' => $guest ? $guest->nickname : ($cast ? $cast->nickname : 'Unknown'),
                'avatar' => $guest ? $guest->avatar : ($cast ? $cast->avatar : '/assets/avatar/default.png'),
                
                // Group data
                'group' => $group ? [
                    'id' => $group->id,
                    'name' => $group->name,
                ] : null,
                'group_name' => $group ? $group->name : null,
                'is_group_chat' => !is_null($chatData->group_id),
                
                // Message data
                'last_message' => '',
                'lastMessage' => '',
                'unread' => 0,
                
                // Additional fields for cast side
                'guest_age' => $guest ? $guest->birth_year : null,
            ];
        } else {
            $this->chatData = $chatData;
        }
    }

    public function broadcastOn()
    {
        $channelName = $this->userType . '.' . $this->userId;
        return [new Channel($channelName)];
    }

    public function broadcastAs()
    {
        return 'ChatListUpdated';
    }

    public function broadcastWith()
    {
        return [
            'chat' => $this->chatData,
            'userType' => $this->userType,
            'userId' => $this->userId
        ];
    }
}
