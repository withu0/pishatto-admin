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
        // $messages->map(function ($message) {
        //     $message->is_read = true;
        //     $message->save();
        // });
        return response()->json(['messages' => $messages]);
    }
} 