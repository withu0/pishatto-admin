<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Gift;
use Illuminate\Support\Facades\DB; // Added for DB facade
use Illuminate\Support\Facades\Log; // Added for Log facade

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->query('user_id');
        $userType = $request->query('user_type'); // 'guest' or 'cast'
        
        if (!$userId || !$userType) {
            return response()->json(['error' => 'user_id and user_type are required'], 400);
        }
        
        // Use more efficient query with proper eager loading and subqueries
        $chats = Chat::with([
            'guest:id,nickname,avatar',
            'cast:id,nickname,avatar',
            'group:id,name',
            'messages' => function($q) {
                $q->select('id', 'chat_id', 'message', 'created_at')
                  ->orderBy('created_at', 'desc')
                  ->limit(1); // Only get the last message
            }
        ])->when($userType === 'guest', function($query) use ($userId) {
            return $query->where('guest_id', $userId);
        })->when($userType === 'cast', function($query) use ($userId) {
            return $query->where('cast_id', $userId);
        })->get();

        // Get unread counts in a single query to avoid N+1
        $unreadCounts = DB::table('messages as m')
            ->join('chats as c', 'm.chat_id', '=', 'c.id')
            ->select(
                'm.chat_id',
                DB::raw('COUNT(*) as unread_count')
            )
            ->where('m.is_read', false)
            ->when($userType === 'guest', function($query) use ($userId) {
                return $query->where('m.sender_cast_id', '!=', null)
                           ->where('c.guest_id', $userId)
                           ->where(function($subQuery) {
                               $subQuery->where('m.recipient_type', 'both')
                                       ->orWhere('m.recipient_type', 'guest');
                           });
            })
            ->when($userType === 'cast', function($query) use ($userId) {
                return $query->where('m.sender_guest_id', '!=', null)
                           ->where('c.cast_id', $userId)
                           ->where(function($subQuery) {
                               $subQuery->where('m.recipient_type', 'both')
                                       ->orWhere('m.recipient_type', 'cast');
                           });
            })
            ->groupBy('m.chat_id')
            ->pluck('unread_count', 'chat_id')
            ->toArray();

        $result = $chats->map(function ($chat) use ($userId, $userType, $unreadCounts) {
            $lastMessage = $chat->messages->first();
            $unread = $unreadCounts[$chat->id] ?? 0;
            
            if ($userType === 'cast') {
                // Format for cast side
                $guest = $chat->guest;
                $chatData = [
                    'id' => $chat->id,
                    'avatar' => $guest ? $guest->avatar : '/assets/avatar/default.png',
                    'name' => $guest ? $guest->nickname : 'Unknown Guest',
                    'guest_id' => $guest ? $guest->id : null,
                    'guest_nickname' => $guest ? $guest->nickname : 'Unknown Guest',
                    'last_message' => $lastMessage ? $lastMessage->message : '',
                    'updated_at' => $lastMessage ? $lastMessage->created_at : $chat->created_at,
                    'created_at' => $chat->created_at,
                    'unread' => $unread,
                    'is_group_chat' => !is_null($chat->group_id),
                ];

                // Add group information if this is a group chat
                if ($chat->group_id) {
                    $chatData['group_id'] = $chat->group_id;
                    $chatData['group_name'] = $chat->group ? $chat->group->name : 'Group Chat';
                }
            } else {
                // Format for guest side
                $cast = $chat->cast;
                $chatData = [
                    'id' => $chat->id,
                    'avatar' => $cast ? $cast->avatar : '/assets/avatar/default.png',
                    'name' => $cast ? $cast->nickname : 'Unknown Cast',
                    'cast_id' => $cast ? $cast->id : null,
                    'cast_nickname' => $cast ? $cast->nickname : 'Unknown Cast',
                    'last_message' => $lastMessage ? $lastMessage->message : '',
                    'updated_at' => $lastMessage ? $lastMessage->created_at : $chat->created_at,
                    'created_at' => $chat->created_at,
                    'unread' => $unread,
                    'is_group_chat' => !is_null($chat->group_id),
                ];

                // Add group information if this is a group chat
                if ($chat->group_id) {
                    $chatData['group_id'] = $chat->group_id;
                    $chatData['group_name'] = $chat->group ? $chat->group->name : 'Group Chat';
                }
            }

            return $chatData;
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

        $message = Message::create($validated);
        $message->load(['guest', 'cast']);
        event(new \App\Events\MessageSent($message));

        // Save gift to guest_gifts table if a gift was sent
        if ($request->input('gift_id')) {
            $chat = $message->chat;
            if ($chat && $message->sender_guest_id && $chat->cast_id) {
                // Get the gift details
                $gift = Gift::find($request->input('gift_id'));
                
                if ($gift && $gift->points > 0) {
                    // Get guest and cast
                    $guest = \App\Models\Guest::find($message->sender_guest_id);
                    $cast = \App\Models\Cast::find($chat->cast_id);
                    
                    if ($guest && $cast) {
                        // Check if guest has enough points
                        if ($guest->points >= $gift->points) {
                            // Deduct points from guest and add to grade_points (spending contributes to grade)
                            $guest->points -= $gift->points;
                            $guest->grade_points += $gift->points;
                            $guest->save();
                            
                            // Add points to cast
                            $cast->points += $gift->points;
                            $cast->save();
                            // Grade upgrades are handled via quarterly evaluation & admin approval
                            
                            // Create point transaction record
                            try {
                                \App\Models\PointTransaction::create([
                                    'guest_id' => $guest->id,
                                    'cast_id' => $cast->id,
                                    'type' => 'gift',
                                    'amount' => $gift->points,
                                    'reservation_id' => $chat->reservation_id,
                                    'description' => "贈り物が送られました: {$gift->name}",
                                    'gift_type' => 'sent'
                                ]);
                            } catch (\Exception $e) {
                                // Log the error for debugging
                                Log::error('Failed to create point transaction for gift', [
                                    'error' => $e->getMessage(),
                                    'guest_id' => $guest->id,
                                    'cast_id' => $cast->id,
                                    'gift_id' => $gift->id,
                                    'points' => $gift->points
                                ]);
                                
                                // Try using raw SQL as fallback
                                try {
                                    DB::table('point_transactions')->insert([
                                        'guest_id' => $guest->id,
                                        'cast_id' => $cast->id,
                                        'type' => 'gift',
                                        'amount' => $gift->points,
                                        'reservation_id' => $chat->reservation_id,
                                        'description' => "贈り物が送られました: {$gift->name}",
                                        'gift_type' => 'sent',
                                        'created_at' => now(),
                                        'updated_at' => now()
                                    ]);
                                } catch (\Exception $e2) {
                                    Log::error('Raw SQL also failed for point transaction', [
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
            // Determine cast_id based on message sender
            $castId = null;
            if ($message->sender_cast_id) {
                $castId = $message->sender_cast_id;
            } elseif ($message->sender_guest_id && $chat && $chat->cast_id) {
                $castId = $chat->cast_id;
            }
            
            $notification = \App\Models\Notification::create([
                'user_id' => $recipientId,
                'user_type' => $recipientType,
                'type' => 'message',
                'reservation_id' => null,
                'cast_id' => $castId,
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
        $perPage = $request->query('per_page', 50); // Add pagination
        $page = $request->query('page', 1);
        
        // Use more efficient query with pagination and recipient type filtering
        $messagesQuery = Message::with(['guest:id,nickname,avatar', 'cast:id,nickname,avatar', 'gift:id,name,icon,points'])
            ->where('chat_id', $chatId);
        
        // Filter messages based on recipient type
        if ($userType) {
            $messagesQuery->where(function($query) use ($userType) {
                $query->where('recipient_type', 'both')
                      ->orWhere('recipient_type', $userType);
            });
        }
        
        $messages = $messagesQuery->orderBy('created_at', 'desc') // Changed to desc for better UX
            ->paginate($perPage, ['*'], 'page', $page);
        
        // Mark messages as read in batch to reduce database calls
        if ($userType && $userId) {
            $messageIds = $messages->pluck('id')->toArray();
            
            if (!empty($messageIds)) {
                $updateQuery = Message::whereIn('id', $messageIds);
                
                if ($userType === 'guest') {
                    $updateQuery->where('sender_cast_id', '!=', null)
                               ->where('is_read', false);
                } else if ($userType === 'cast') {
                    $updateQuery->where('sender_guest_id', '!=', null)
                               ->where('is_read', false);
                }
                
                $updatedCount = $updateQuery->update(['is_read' => true]);
                
                // Broadcast MessagesRead event if any messages were marked as read
                if ($updatedCount > 0) {
                    event(new \App\Events\MessagesRead($chatId, $userId, $userType));
                }
            }
        }
        
        return response()->json([
            'messages' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'has_more_pages' => $messages->hasMorePages(),
            ]
        ]);
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
        $gifts = DB::table('guest_gifts')
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
        $reservationId = $request->input('reservation_id');
        
        if (!$castId || !$guestId) {
            return response()->json(['message' => 'cast_id and guest_id are required'], 422);
        }
        
        // Check if chat already exists for this cast and guest
        $chat = \App\Models\Chat::where('cast_id', $castId)->where('guest_id', $guestId)->where('reservation_id', $reservationId)->first();
        if ($chat) {
            // Update reservation_id if provided and not already set
            if ($reservationId && !$chat->reservation_id) {
                $chat->update(['reservation_id' => $reservationId]);
            }
            return response()->json(['chat' => $chat, 'created' => false]);
        }
        
        $chat = \App\Models\Chat::create([
            'cast_id' => $castId,
            'guest_id' => $guestId,
            'reservation_id' => $reservationId,
        ]);
        
        // Load relationships for broadcasting
        $chat->load(['guest', 'cast', 'group']);
        
        // Broadcast chat creation to both participants
        event(new \App\Events\ChatCreated($chat));
        
        // Also broadcast chat list updates to both participants
        if ($chat->guest_id) {
            event(new \App\Events\ChatListUpdated($chat->guest_id, 'guest', $chat));
        }
        if ($chat->cast_id) {
            event(new \App\Events\ChatListUpdated($chat->cast_id, 'cast', $chat));
        }
        
        return response()->json(['chat' => $chat, 'created' => true]);
    }

    // Create a chat group with multiple participants
    public function createChatGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'guest_id' => 'required|exists:guests,id',
            'cast_ids' => 'required|array|min:1',
            'cast_ids.*' => 'exists:casts,id',
            'reservation_id' => 'nullable|exists:reservations,id',
        ]);

        try {
            DB::beginTransaction();

            // Create chat group
            $chatGroup = \App\Models\ChatGroup::create([
                'reservation_id' => $validated['reservation_id'],
                'cast_ids' => $validated['cast_ids'],
                'name' => $validated['name'],
                'created_at' => now(),
            ]);

            // If linked to a free-call reservation, create per-cast pending point transactions
            if (!empty($validated['reservation_id'])) {
                $reservation = \App\Models\Reservation::find($validated['reservation_id']);
                if ($reservation && $reservation->type === 'free') {
                    $hasPending = \App\Models\PointTransaction::where('reservation_id', $reservation->id)
                        ->where('type', 'pending')
                        ->exists();
                    if (!$hasPending) {
                        /** @var \App\Services\PointTransactionService $pointService */
                        $pointService = app(\App\Services\PointTransactionService::class);
                        $requiredPoints = $pointService->calculateReservationPoints($reservation);
                        $castIds = $validated['cast_ids'] ?? [];
                        $numCasts = count($castIds);
                        if ($numCasts > 0 && $requiredPoints > 0) {
                            $baseShare = intdiv($requiredPoints, $numCasts);
                            $remainder = $requiredPoints % $numCasts;
                            foreach (array_values($castIds) as $index => $castId) {
                                $amount = $baseShare + ($index < $remainder ? 1 : 0);
                                \App\Models\PointTransaction::create([
                                    'guest_id' => $reservation->guest_id,
                                    'cast_id' => $castId,
                                    'type' => 'pending',
                                    'amount' => $amount,
                                    'reservation_id' => $reservation->id,
                                    'description' => "Free call - {$reservation->duration} hours (pending)",
                                ]);
                            }
                        }
                    }
                }
            }

            // Create individual chats for each cast
            $chats = [];
            foreach ($validated['cast_ids'] as $castId) {
                $chat = \App\Models\Chat::create([
                    'guest_id' => $validated['guest_id'],
                    'cast_id' => $castId,
                    'reservation_id' => $validated['reservation_id'],
                    'group_id' => $chatGroup->id,
                ]);
                $chats[] = $chat;
            }

            DB::commit();

            // Broadcast group creation and individual chats so UIs can update in real-time
            event(new \App\Events\ChatGroupCreated($chatGroup));
            foreach ($chats as $c) {
                // Load relationships for broadcasting
                $c->load(['guest', 'cast', 'group']);
                event(new \App\Events\ChatCreated($c));
                
                // Also broadcast chat list updates to both participants
                if ($c->guest_id) {
                    event(new \App\Events\ChatListUpdated($c->guest_id, 'guest', $c));
                }
                if ($c->cast_id) {
                    event(new \App\Events\ChatListUpdated($c->cast_id, 'cast', $c));
                }
            }

            return response()->json([
                'chat_group' => $chatGroup,
                'chats' => $chats,
                'message' => 'Chat group created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('createChatGroup error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create chat group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($chatId)
    {
        $chat = \App\Models\Chat::with(['guest', 'cast', 'messages', 'reservation'])->find($chatId);
        if (!$chat) {
            return response()->json(['message' => 'Chat not found'], 404);
        }
        return response()->json(['chat' => $chat]);
    }

    public function update(Request $request, $chatId)
    {
        $chat = \App\Models\Chat::with(['guest', 'cast', 'reservation'])->find($chatId);
        if (!$chat) {
            return response()->json(['message' => 'Chat not found'], 404);
        }

        $validated = $request->validate([
            'guest_nickname' => 'nullable|string|max:50',
            'cast_nickname' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:100',
            'duration' => 'nullable|integer|min:1',
            'details' => 'nullable|string|max:500',
        ]);

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

        return response()->json(['message' => 'Chat updated successfully']);
    }

    public function destroy($chatId)
    {
        $chat = \App\Models\Chat::find($chatId);
        if (!$chat) {
            return response()->json(['message' => 'Chat not found'], 404);
        }

        // Delete all messages in this chat
        \App\Models\Message::where('chat_id', $chatId)->delete();
        
        // Delete the chat
        $chat->delete();

        return response()->json(['message' => 'Chat deleted successfully']);
    }

    public function sendGroupMessage(Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:chat_groups,id',
            'message' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'gift_id' => 'nullable|exists:gifts,id',
            'sender_guest_id' => 'nullable|exists:guests,id',
            'sender_cast_id' => 'nullable|exists:casts,id',
            'receiver_cast_id' => 'nullable|exists:casts,id',
        ]);

        try {
            DB::beginTransaction();

            // Determine target chat within the group
            $query = \App\Models\Chat::where('group_id', $validated['group_id']);
            if (!empty($validated['receiver_cast_id'])) {
                $query->where('cast_id', $validated['receiver_cast_id']);
            }
            $targetChat = $query->first();
            if (!$targetChat) {
                return response()->json(['message' => 'No chat found for this group'], 404);
            }

            $validated['chat_id'] = $targetChat->id;
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

            // Log the message being sent
            Log::info('GroupMessageSent: Broadcasting message', [
                'message_id' => $message->id,
                'group_id' => $validated['group_id'],
                'channel' => 'group.' . $validated['group_id'],
                'message_data' => $message->toArray()
            ]);

            // Broadcast to all participants in the group
            event(new \App\Events\GroupMessageSent($message, $validated['group_id']));

            // Handle gift logic if present
            if ($request->input('gift_id')) {
                $chat = $message->chat; // belongs to the target chat (cast in this group)
                if ($chat && $message->sender_guest_id && $chat->cast_id) {
                    $gift = \App\Models\Gift::find($request->input('gift_id'));
                    
                    if ($gift && $gift->points > 0) {
                        $guest = \App\Models\Guest::find($message->sender_guest_id);
                        $cast = \App\Models\Cast::find($chat->cast_id);
                        
                        if ($guest && $cast && $guest->points >= $gift->points) {
                            // Deduct points from guest and add to grade_points (spending contributes to grade)
                            $guest->points -= $gift->points;
                            $guest->grade_points += $gift->points;
                            $guest->save();
                            
                            $cast->points += $gift->points;
                            $cast->save();
                            // Grade upgrades are handled via quarterly evaluation & admin approval
                            
                            // Record point transaction
                            \App\Models\PointTransaction::create([
                                'guest_id' => $guest->id,
                                'cast_id' => $cast->id,
                                'type' => 'gift',
                                'amount' => $gift->points,
                                'reservation_id' => $chat->reservation_id,
                                'description' => "贈り物が送られました: {$gift->name}",
                                'gift_type' => 'sent'
                            ]);

                            // Store to guest_gifts table as well
                            \App\Models\GuestGift::create([
                                'sender_guest_id' => $message->sender_guest_id,
                                'receiver_cast_id' => $chat->cast_id,
                                'gift_id' => $request->input('gift_id'),
                                'message' => $request->input('message'),
                                'created_at' => now(),
                            ]);

                            // Update real-time rankings
                            $rankingService = app(\App\Services\RankingService::class);
                            $region = $chat && $chat->cast && $chat->cast->residence ? $chat->cast->residence : '全国';
                            $rankingService->updateRealTimeRankings($region);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => $message,
                'group_id' => $validated['group_id']
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('sendGroupMessage error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send group message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getGroupMessages($groupId, Request $request)
    {
        $userType = $request->query('user_type'); // 'guest' or 'cast'
        $userId = $request->query('user_id');

        // Verify user is part of this group
        $group = \App\Models\ChatGroup::find($groupId);
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $userInGroup = \App\Models\Chat::where('group_id', $groupId)
            ->where(function($query) use ($userId, $userType) {
                if ($userType === 'guest') {
                    $query->where('guest_id', $userId);
                } else {
                    $query->where('cast_id', $userId);
                }
            })->exists();

        if (!$userInGroup) {
            return response()->json(['message' => 'Not authorized to access this group'], 403);
        }

        // Get all chats in this group
        $chats = \App\Models\Chat::where('group_id', $groupId)->pluck('id');
        
        // Get all messages from all chats in this group with recipient type filtering
        $messagesQuery = \App\Models\Message::whereIn('chat_id', $chats)
            ->with(['guest', 'cast', 'gift']);
        
        // Filter messages based on recipient type
        if ($userType) {
            $messagesQuery->where(function($query) use ($userType) {
                $query->where('recipient_type', 'both')
                      ->orWhere('recipient_type', $userType);
            });
        }
        
        $messages = $messagesQuery->orderBy('created_at', 'asc')->get();

        // Load reservation information for the group
        $group->load('reservation');
        
        return response()->json([
            'messages' => $messages,
            'group' => $group
        ]);
    }

    public function getGroupParticipants($groupId)
    {
        $group = \App\Models\ChatGroup::with('reservation.guest')->find($groupId);
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $chats = \App\Models\Chat::where('group_id', $groupId)
            ->with(['guest', 'cast'])
            ->get();



        $participants = [];
        $seenGuests = [];
        $seenCasts = [];
        
        // Add participants from individual chats
        foreach ($chats as $chat) {
            if ($chat->guest && !in_array($chat->guest->id, $seenGuests)) {
                $participants[] = [
                    'id' => $chat->guest->id,
                    'type' => 'guest',
                    'nickname' => $chat->guest->nickname,
                    'avatar' => $chat->guest->avatar,
                ];
                $seenGuests[] = $chat->guest->id;
            }
            if ($chat->cast && !in_array($chat->cast->id, $seenCasts)) {
                $participants[] = [
                    'id' => $chat->cast->id,
                    'type' => 'cast',
                    'nickname' => $chat->cast->nickname,
                    'avatar' => $chat->cast->avatar,
                ];
                $seenCasts[] = $chat->cast->id;
            }
        }
        
        // Also add casts from the group's cast_ids array
        if ($group->cast_ids && is_array($group->cast_ids)) {
            $groupCasts = \App\Models\Cast::whereIn('id', $group->cast_ids)->get();
            foreach ($groupCasts as $cast) {
                if (!in_array($cast->id, $seenCasts)) {
                    $participants[] = [
                        'id' => $cast->id,
                        'type' => 'cast',
                        'nickname' => $cast->nickname,
                        'avatar' => $cast->avatar,
                    ];
                    $seenCasts[] = $cast->id;
                }
            }
        }
        




        return response()->json([
            'participants' => $participants,
            'group' => $group
        ]);
    }

    public function markChatRead(Request $request, $chatId)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|in:guest,cast',
        ]);

        $userId = $validated['user_id'];
        $userType = $validated['user_type'];

        // Verify the chat exists and user has access
        $chat = \App\Models\Chat::find($chatId);
        if (!$chat) {
            return response()->json(['message' => 'Chat not found'], 404);
        }

        // Check if user is part of this chat
        $userInChat = false;
        if ($userType === 'guest' && $chat->guest_id == $userId) {
            $userInChat = true;
        } elseif ($userType === 'cast' && $chat->cast_id == $userId) {
            $userInChat = true;
        }

        if (!$userInChat) {
            return response()->json(['message' => 'Not authorized to access this chat'], 403);
        }

        // Mark all unread messages as read for this user
        $messagesMarkedAsRead = false;
        $messages = \App\Models\Message::where('chat_id', $chatId)->get();
        
        foreach ($messages as $msg) {
            if ($userType === 'guest' && $msg->sender_cast_id && $msg->is_read == false && $msg->chat->guest_id == $userId && 
                ($msg->recipient_type === 'both' || $msg->recipient_type === 'guest')) {
                $msg->is_read = true;
                $msg->save();
                $messagesMarkedAsRead = true;
            } else if ($userType === 'cast' && $msg->sender_guest_id && $msg->is_read == false && $msg->chat->cast_id == $userId && 
                       ($msg->recipient_type === 'both' || $msg->recipient_type === 'cast')) {
                $msg->is_read = true;
                $msg->save();
                $messagesMarkedAsRead = true;
            }
        }
        
        // Broadcast MessagesRead event if any messages were marked as read
        if ($messagesMarkedAsRead) {
            event(new \App\Events\MessagesRead($chatId, $userId, $userType));
        }

        return response()->json([
            'message' => 'Messages marked as read successfully',
            'messages_marked' => $messagesMarkedAsRead
        ]);
    }
} 