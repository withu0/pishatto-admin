<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Message;

class ChatController extends Controller
{
    public function index()
    {
        $chats = Chat::with(['guest', 'cast', 'messages' => function($q) {
            $q->orderBy('created_at', 'desc');
        }])->get();

        $result = $chats->map(function ($chat) {
            // Prefer guest, fallback to cast
            $user = $chat->guest ?? $chat->cast;
            $name = $user->nickname ?? 'Unknown';
            $avatar = $user->avatar ?? '/assets/avatar/default.png';
            $lastMessage = $chat->messages->first();
            return [
                'id' => $chat->id,
                'avatar' => $avatar,
                'name' => $name,
                'lastMessage' => $lastMessage ? $lastMessage->message : '',
                'timestamp' => $lastMessage ? $lastMessage->created_at : now(),
                'unread' => false, // Implement unread logic if needed
            ];
        });

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'chat_id' => 'required|exists:chats,id',
            'message' => 'nullable|string',
            'image' => 'nullable|string',
            'gift_id' => 'nullable|exists:gifts,id',
            'sender_guest_id' => 'nullable|exists:guests,id',
            'sender_cast_id' => 'nullable|exists:casts,id',
        ]);
        $validated['created_at'] = now();
        $message = \App\Models\Message::create($validated);
        return response()->json(['message' => $message], 201);
    }

    public function messages($chatId)
    {
        $messages = Message::with(['guest', 'cast'])
            ->where('chat_id', $chatId)
            ->orderBy('created_at', 'asc')
            ->get();
        $result = $messages->map(function ($msg) {
            $sender = $msg->guest ?? $msg->cast;
            return [
                'id' => $msg->id,
                'message' => $msg->message,
                'image' => $msg->image,
                'gift_id' => $msg->gift_id,
                'created_at' => $msg->created_at,
                'sender_guest_id' => $msg->sender_guest_id,
                'sender_cast_id' => $msg->sender_cast_id,
                'avatar' => $sender ? $sender->avatar : null,
                'nickname' => $sender ? $sender->nickname : null,
            ];
        });
        return response()->json(['messages' => $result]);
    }
} 