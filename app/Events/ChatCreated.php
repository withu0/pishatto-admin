<?php

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log; // will be removed with debug logs below

class ChatCreated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $chat;
    public $chatData;

    public function __construct(Chat $chat)
    {
        $this->chat = $chat;
        
        // Load relationships for broadcasting
        $chat->load(['guest', 'cast', 'group']);
        
        $guest = $chat->guest;
        $cast = $chat->cast;
        $group = $chat->group;
        
        // Create a unified data structure that works for both guest and cast sides
        $this->chatData = [
            'id' => $chat->id,
            'guest_id' => $chat->guest_id,
            'cast_id' => $chat->cast_id,
            'reservation_id' => $chat->reservation_id,
            'group_id' => $chat->group_id,
            'created_at' => $chat->created_at,
            'updated_at' => $chat->created_at,
            
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
            'is_group_chat' => !is_null($chat->group_id),
            
            // Message data
            'last_message' => '',
            'lastMessage' => '',
            'unread' => 0,
            
            // Additional fields for cast side
            'guest_age' => $guest ? $guest->birth_year : null,
        ];
        
        // (debug logs removed)
    }

    public function broadcastOn()
    {
        $channels = [];
        
        // Broadcast to guest channel
        if ($this->chat->guest_id) {
            $channels[] = new Channel('guest.' . $this->chat->guest_id);
        }
        
        // Broadcast to cast channel
        if ($this->chat->cast_id) {
            $channels[] = new Channel('cast.' . $this->chat->cast_id);
        }
        
        // (debug logs removed)
        
        return $channels;
    }

    public function broadcastAs()
    {
        return 'ChatCreated';
    }

    public function broadcastWith()
    {
        // (debug logs removed)
        
        return [
            'chat' => $this->chatData
        ];
    }
}



