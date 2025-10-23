<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function index()
    {
        $chats = Chat::with(['guest', 'cast', 'reservation', 'messages'])
            ->withCount('messages')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($chat) {
                $lastMessage = $chat->messages()->latest()->first();

                // Get the first avatar for cast (in case they have multiple)
                $castAvatar = $chat->cast->avatar;
                if ($castAvatar) {
                    $avatars = explode(',', $castAvatar);
                    $castAvatar = trim($avatars[0]);
                }

                return [
                    'id' => $chat->id,
                    'guest' => [
                        'id' => $chat->guest->id,
                        'nickname' => $chat->guest->nickname,
                        'avatar' => $chat->guest->avatar,
                    ],
                    'cast' => [
                        'id' => $chat->cast->id,
                        'nickname' => $chat->cast->nickname,
                        'avatar' => $castAvatar,
                    ],
                    'reservation' => $chat->reservation ? [
                        'id' => $chat->reservation->id,
                        'type' => $chat->reservation->type,
                        'scheduled_at' => $chat->reservation->scheduled_at,
                        'location' => $chat->reservation->location,
                        'duration' => $chat->reservation->duration,
                        'details' => $chat->reservation->details,
                    ] : null,
                    'created_at' => $chat->created_at,
                    'message_count' => $chat->messages_count,
                    'last_message_at' => $lastMessage ? $lastMessage->created_at : null,
                ];
            });

        return Inertia::render('admin/matching-manage', [
            'chats' => $chats
        ]);
    }

    public function show($id)
    {
        $chat = Chat::with(['guest', 'cast', 'reservation', 'messages' => function($query) {
            $query->orderBy('created_at', 'asc');
        }, 'messages.guest', 'messages.cast', 'messages.gift'])->findOrFail($id);

        $messages = $chat->messages->map(function ($message) {
            return [
                'id' => $message->id,
                'message' => $message->message,
                'image' => $message->image,
                'gift_id' => $message->gift_id,
                'sender_guest_id' => $message->sender_guest_id,
                'sender_cast_id' => $message->sender_cast_id,
                'created_at' => $message->created_at,
                'is_read' => $message->is_read,
                'guest' => $message->guest ? [
                    'id' => $message->guest->id,
                    'nickname' => $message->guest->nickname,
                    'avatar' => $message->guest->avatar,
                ] : null,
                'cast' => $message->cast ? [
                    'id' => $message->cast->id,
                    'nickname' => $message->cast->nickname,
                    'avatar' => $message->cast->avatar,
                ] : null,
                'gift' => $message->gift ? [
                    'id' => $message->gift->id,
                    'name' => $message->gift->name,
                    'icon' => $message->gift->icon,
                    'points' => $message->gift->points,
                ] : null,
            ];
        });

        return response()->json([
            'chat' => [
                'id' => $chat->id,
                'guest' => [
                    'id' => $chat->guest->id,
                    'nickname' => $chat->guest->nickname,
                    'avatar' => $chat->guest->avatar,
                ],
                'cast' => [
                    'id' => $chat->cast->id,
                    'nickname' => $chat->cast->nickname,
                    'avatar' => $chat->cast->avatar,
                ],
                'reservation' => $chat->reservation ? [
                    'id' => $chat->reservation->id,
                    'type' => $chat->reservation->type,
                    'scheduled_at' => $chat->reservation->scheduled_at,
                    'location' => $chat->reservation->location,
                    'duration' => $chat->reservation->duration,
                    'details' => $chat->reservation->details,
                ] : null,
                'created_at' => $chat->created_at,
                'messages' => $messages,
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'guest_nickname' => 'nullable|string|max:50',
            'cast_nickname' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:100',
            'duration' => 'nullable|integer|min:1',
            'details' => 'nullable|string|max:500',
        ]);

        $chat = Chat::with(['guest', 'cast', 'reservation'])->findOrFail($id);

        // Update guest nickname if provided
        if (isset($validated['guest_nickname']) && $chat->guest) {
            $chat->guest->update(['nickname' => $validated['guest_nickname']]);
        }

        // Update cast nickname if provided
        if (isset($validated['cast_nickname']) && $chat->cast) {
            $chat->cast->update(['nickname' => $validated['cast_nickname']]);
        }

        // Update reservation if provided
        if ($chat->reservation && (isset($validated['location']) || isset($validated['duration']) || isset($validated['details']))) {
            $reservationData = [];
            if (isset($validated['location'])) $reservationData['location'] = $validated['location'];
            if (isset($validated['duration'])) $reservationData['duration'] = $validated['duration'];
            if (isset($validated['details'])) $reservationData['details'] = $validated['details'];

            $chat->reservation->update($reservationData);
        }

        return response()->json([
            'message' => 'Chat updated successfully'
        ]);
    }

    public function destroy($id)
    {
        $chat = Chat::findOrFail($id);

        // Delete all messages in this chat
        $chat->messages()->delete();

        // Delete the chat
        $chat->delete();

        return response()->json([
            'message' => 'Chat deleted successfully'
        ]);
    }

    public function deleteMessage($chatId, $messageId)
    {
        $message = Message::where('chat_id', $chatId)->findOrFail($messageId);
        $message->delete();

        return response()->json([
            'message' => 'Message deleted successfully'
        ]);
    }
}
