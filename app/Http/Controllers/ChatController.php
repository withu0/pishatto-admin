<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Gift;

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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'gift_id' => 'nullable|exists:gifts,id',
            'sender_guest_id' => 'nullable|exists:guests,id',
            'sender_cast_id' => 'nullable|exists:casts,id',
        ]);
        $validated['created_at'] = now();

        // Handle image upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('chat_images', $fileName, 'public');
            $validated['image'] = 'chat_images/' . $fileName;
        }

        $message = \App\Models\Message::create($validated);
        $message->load(['guest', 'cast']);
        event(new \App\Events\MessageSent($message));

        // Notification logic for recipient
        // $chat = $message->chat;
        // if ($message->sender_guest_id && $chat && $chat->cast_id) {
        //     // Guest sent message to cast
        //     $recipientId = $chat->cast_id;
        //     $recipientType = 'cast';
        // } elseif ($message->sender_cast_id && $chat && $chat->guest_id) {
        //     // Cast sent message to guest
        //     $recipientId = $chat->guest_id;
        //     $recipientType = 'guest';
        // } else {
        //     $recipientId = null;
        //     $recipientType = null;
        // }
        // if ($recipientId && $recipientType) {
        //     $notification = \App\Models\Notification::create([
        //         'user_id' => $recipientId,
        //         'user_type' => $recipientType,
        //         'type' => 'message',
        //         'reservation_id' => null,
        //         'message' => '新しいメッセージが届きました',
        //         'read' => false,
        //     ]);
        //     event(new \App\Events\NotificationSent($notification));
        // }
        return response()->json(['message' => $message], 201);
    }

    public function messages($chatId)
    {
        $messages = Message::with(['guest', 'cast', 'gift'])
            ->where('chat_id', $chatId)
            ->orderBy('created_at', 'asc') 
            ->get();
        // $messages->map(function ($message) {
        //     $message->is_read = true;
        //     $message->save();
        // });
        return response()->json(['messages' => $messages]);
    }

    // Fetch all available gifts
    public function gifts()
    {
        $gifts = Gift::all();
        return response()->json(['gifts' => $gifts]);
    }

    // Fetch all gifts received by a cast
    public function receivedGifts($castId)
    {
        $gifts = \DB::table('guest_gifts')
            ->join('gifts', 'guest_gifts.gift_id', '=', 'gifts.id')
            ->join('guests', 'guest_gifts.sender_guest_id', '=', 'guests.id')
            ->where('guest_gifts.receiver_cast_id', $castId)
            ->orderBy('guest_gifts.created_at', 'desc')
            ->select(
                'guest_gifts.id',
                'guests.nickname as sender',
                'guests.avatar as sender_avatar',
                'guest_gifts.created_at as date',
                'gifts.name as gift_name',
                'gifts.points',
                'gifts.icon as gift_icon'
            )
            ->get();
        return response()->json(['gifts' => $gifts]);
    }
} 