<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Gift;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->query('user_id');
        $userType = $request->query('user_type'); // 'guest' or 'cast'
        $chats = Chat::with(['guest', 'cast', 'messages' => function($q) {
            $q->orderBy('created_at', 'desc');
        }])->get();

        $result = $chats->map(function ($chat) use ($userId, $userType) {
            $user = $chat->guest ?? $chat->cast;
            $name = $user->nickname ?? 'Unknown';
            $avatar = $user->avatar ?? '/assets/avatar/default.png';
            $lastMessage = $chat->messages->first();
            // Count unread messages for this user in this chat
            $unread = $chat->messages->where('is_read', false)
                ->filter(function($msg) use ($userId, $userType) {
                    if ($userType === 'guest') {
                        return $msg->sender_cast_id && $msg->is_read == false && $msg->chat->guest_id == $userId;
                    } else if ($userType === 'cast') {
                        return $msg->sender_guest_id && $msg->is_read == false && $msg->chat->cast_id == $userId;
                    }
                    return false;
                })->count();
            return [
                'id' => $chat->id,
                'avatar' => $avatar,
                'name' => $name,
                'lastMessage' => $lastMessage ? $lastMessage->message : '',
                'timestamp' => $lastMessage ? $lastMessage->created_at : now(),
                'unread' => $unread,
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

        // Save gift to guest_gifts table if a gift was sent
        if ($request->input('gift_id')) {
            $chat = $message->chat;
            if ($chat && $message->sender_guest_id && $chat->cast_id) {
                // Get the gift details
                $gift = \App\Models\Gift::find($request->input('gift_id'));
                
                if ($gift && $gift->points > 0) {
                    // Get guest and cast
                    $guest = \App\Models\Guest::find($message->sender_guest_id);
                    $cast = \App\Models\Cast::find($chat->cast_id);
                    
                    if ($guest && $cast) {
                        // Check if guest has enough points
                        if ($guest->points >= $gift->points) {
                            // Deduct points from guest
                            $guest->points -= $gift->points;
                            $guest->save();
                            
                            // Add points to cast
                            $cast->points += $gift->points;
                            $cast->save();
                            
                            // Create point transaction record
                            try {
                                \App\Models\PointTransaction::create([
                                    'guest_id' => $guest->id,
                                    'cast_id' => $cast->id,
                                    'type' => 'gift',
                                    'amount' => $gift->points,
                                    'reservation_id' => $chat->reservation_id,
                                    'description' => "Gift sent: {$gift->name}",
                                    'gift_type' => 'sent'
                                ]);
                            } catch (\Exception $e) {
                                // Log the error for debugging
                                \Log::error('Failed to create point transaction for gift', [
                                    'error' => $e->getMessage(),
                                    'guest_id' => $guest->id,
                                    'cast_id' => $cast->id,
                                    'gift_id' => $gift->id,
                                    'points' => $gift->points
                                ]);
                                
                                // Try using raw SQL as fallback
                                try {
                                    \DB::table('point_transactions')->insert([
                                        'guest_id' => $guest->id,
                                        'cast_id' => $cast->id,
                                        'type' => 'gift',
                                        'amount' => $gift->points,
                                        'reservation_id' => $chat->reservation_id,
                                        'description' => "Gift sent: {$gift->name}",
                                        'gift_type' => 'sent',
                                        'created_at' => now(),
                                        'updated_at' => now()
                                    ]);
                                } catch (\Exception $e2) {
                                    \Log::error('Raw SQL also failed for point transaction', [
                                        'error' => $e2->getMessage()
                                    ]);
                                }
                                
                                // Continue with the gift sending even if transaction record fails
                                // The points have already been deducted/added
                            }
                        } else {
                            // If insufficient points, delete the message and return error
                            $message->delete();
                            return response()->json([
                                'error' => 'Insufficient points to send this gift',
                                'required_points' => $gift->points,
                                'available_points' => $guest->points
                            ], 400);
                        }
                    }
                }
                
                \App\Models\GuestGift::create([
                    'sender_guest_id' => $message->sender_guest_id,
                    'receiver_cast_id' => $chat->cast_id,
                    'gift_id' => $request->input('gift_id'),
                    'message' => $request->input('message'),
                    'created_at' => now(),
                ]);
            }
            
            // Real-time ranking update for gifts
            $rankingService = app(\App\Services\RankingService::class);
            $region = $chat && $chat->cast && $chat->cast->residence ? $chat->cast->residence : '全国';
            $rankingService->updateRealTimeRankings($region);
        }

        // Notification logic for recipient
        $chat = $message->chat;
        if ($message->sender_guest_id && $chat && $chat->cast_id) {
            // Guest sent message to cast
            $recipientId = $chat->cast_id;
            $recipientType = 'cast';
        } elseif ($message->sender_cast_id && $chat && $chat->guest_id) {
            // Cast sent message to guest
            $recipientId = $chat->guest_id;
            $recipientType = 'guest';
        } else {
            $recipientId = null;
            $recipientType = null;
        }
        if ($recipientId && $recipientType) {
            $notification = \App\Models\Notification::create([
                'user_id' => $recipientId,
                'user_type' => $recipientType,
                'type' => 'message',
                'reservation_id' => null,
                'message' => '新しいメッセージが届きました',
                'read' => false,
            ]);
            event(new \App\Events\NotificationSent($notification));
        }
        return response()->json(['message' => $message], 201);
    }

    public function messages($chatId, Request $request)
    {
        $userId = $request->query('user_id');
        $userType = $request->query('user_type');
        $messages = Message::with(['guest', 'cast', 'gift'])
            ->where('chat_id', $chatId)
            ->orderBy('created_at', 'asc') 
            ->get();
        // Mark all messages as read for this user
        foreach ($messages as $msg) {
            if ($userType === 'guest' && $msg->sender_cast_id && $msg->is_read == false && $msg->chat->guest_id == $userId) {
                $msg->is_read = true;
                $msg->save();
            } else if ($userType === 'cast' && $msg->sender_guest_id && $msg->is_read == false && $msg->chat->cast_id == $userId) {
                $msg->is_read = true;
                $msg->save();
            }
        }
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

    // Create a chat group between cast and guest (no reservation)
    public function createChat(Request $request)
    {
        $castId = $request->input('cast_id');
        $guestId = $request->input('guest_id');
        if (!$castId || !$guestId) {
            return response()->json(['message' => 'cast_id and guest_id are required'], 422);
        }
        $chat = \App\Models\Chat::where('cast_id', $castId)->where('guest_id', $guestId)->first();
        if ($chat) {
            return response()->json(['chat' => $chat, 'created' => false]);
        }
        $chat = \App\Models\Chat::create([
            'cast_id' => $castId,
            'guest_id' => $guestId,
            'reservation_id' => null,
        ]);
        return response()->json(['chat' => $chat, 'created' => true]);
    }

    public function show($chatId)
    {
        $chat = \App\Models\Chat::with(['guest', 'cast', 'messages', 'reservation'])->find($chatId);
        if (!$chat) {
            return response()->json(['message' => 'Chat not found'], 404);
        }
        return response()->json(['chat' => $chat]);
    }
} 