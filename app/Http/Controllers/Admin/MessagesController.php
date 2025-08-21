<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Guest;
use App\Models\Cast;
use App\Models\Chat;
use App\Models\Gift;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MessagesController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $messagesPaginator = Message::with(['guest', 'cast', 'chat.guest', 'chat.cast', 'gift'])
            ->latest('created_at')
            ->paginate($perPage);

        $rawMessages = $messagesPaginator->getCollection();

        $transformed = $messagesPaginator->getCollection()->map(function ($message) {
            $guest = null;
            $cast = null;
            $content = null;
            $image = null;
            $gift = null;
            
            // Get guest name - try direct relationship first, then through chat
            if ($message->sender_guest_id && $message->guest) {
                $guest = $message->guest->nickname ?? $message->guest->phone;
            } elseif ($message->chat && $message->chat->guest) {
                $guest = $message->chat->guest->nickname ?? $message->chat->guest->phone;
            }
            
            // Get cast name - try direct relationship first, then through chat
            if ($message->sender_cast_id && $message->cast) {
                $cast = $message->cast->nickname ?? $message->cast->phone;
            } elseif ($message->chat && $message->chat->cast) {
                $cast = $message->chat->cast->nickname ?? $message->chat->cast->phone;
            }

            // Handle content - check for text, image, or gift
            if ($message->message) {
                $content = substr($message->message, 0, 50) . (strlen($message->message) > 50 ? '...' : '');
            } elseif ($message->image) {
                $content = '[画像]';
                $image = asset('storage/' . $message->image);
            } elseif ($message->gift_id && $message->gift) {
                $content = '[ギフト] ' . $message->gift->name;
                $gift = [
                    'id' => $message->gift->id,
                    'name' => $message->gift->name,
                    'icon' => $message->gift->icon, // Direct emoji, not file path
                    'points' => $message->gift->points
                ];
            } else {
                $content = 'No content';
            }

            // Safe date formatting
            $date = 'Unknown';
            if ($message->created_at) {
                if ($message->created_at instanceof Carbon) {
                    $date = $message->created_at->format('Y-m-d H:i');
                } else {
                    // If it's a string, try to parse it
                    try {
                        $date = Carbon::parse($message->created_at)->format('Y-m-d H:i');
                    } catch (\Exception $e) {
                        $date = $message->created_at;
                    }
                }
            }

            return [
                'id' => $message->id,
                'guest' => $guest ?? 'Unknown',
                'cast' => $cast ?? 'Unknown',
                'content' => $content,
                'image' => $image,
                'gift' => $gift,
                'date' => $date,
            ];
        });

        $messagesPaginator->setCollection($transformed);

        // Get additional data for forms
        $guests = Guest::select('id', 'nickname', 'phone')->get();
        $casts = Cast::select('id', 'nickname', 'phone')->get();
        $gifts = Gift::select('id', 'name', 'icon', 'points')->get();

        return Inertia::render('admin/messages', [
            'messages' => $messagesPaginator,
            'guests' => $guests,
            'casts' => $casts,
            'gifts' => $gifts,
            'rawMessages' => $rawMessages,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'chat_id' => 'required|exists:chats,id',
            'sender_guest_id' => 'nullable|exists:guests,id',
            'sender_cast_id' => 'nullable|exists:casts,id',
            'message' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'gift_id' => 'nullable|exists:gifts,id',
        ]);

        $data = $request->only(['chat_id', 'sender_guest_id', 'sender_cast_id', 'message', 'gift_id']);
        $data['created_at'] = now();

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('messages', 'public');
            $data['image'] = $imagePath;
        }

        $message = Message::create($data);

        return response()->json([
            'success' => true,
            'message' => 'メッセージが正常に作成されました。',
            'data' => $message->load(['guest', 'cast', 'gift'])
        ]);
    }

    public function show($id)
    {
        $message = Message::with(['guest', 'cast', 'chat.guest', 'chat.cast', 'gift'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $message
        ]);
    }

    public function update(Request $request, $id)
    {
        $message = Message::findOrFail($id);

        try {
            $request->validate([
                'chat_id' => 'required|exists:chats,id',
                'sender_guest_id' => 'nullable|exists:guests,id',
                'sender_cast_id' => 'nullable|exists:casts,id',
                'message' => 'nullable|string|max:1000',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'gift_id' => 'nullable|exists:gifts,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラーが発生しました。',
                'errors' => $e->errors()
            ], 422);
        }

        $data = $request->only(['chat_id', 'sender_guest_id', 'sender_cast_id', 'message', 'gift_id']);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($message->image) {
                Storage::disk('public')->delete($message->image);
            }
            $imagePath = $request->file('image')->store('messages', 'public');
            $data['image'] = $imagePath;
        } elseif ($request->has('remove_image') && $request->remove_image == '1') {
            // Remove existing image
            if ($message->image) {
                Storage::disk('public')->delete($message->image);
            }
            $data['image'] = null;
        }

        try {
            $message->update($data);

            return response()->json([
                'success' => true,
                'message' => 'メッセージが正常に更新されました。',
                'data' => $message->load(['guest', 'cast', 'gift'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'メッセージの更新に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $message = Message::findOrFail($id);
        
        // Delete image if exists
        if ($message->image) {
            Storage::disk('public')->delete($message->image);
        }
        
        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'メッセージが正常に削除されました。'
        ]);
    }

    public function getMessagesData()
    {
        $messages = Message::with(['guest', 'cast', 'chat.guest', 'chat.cast', 'gift'])
            ->latest('created_at')
            ->get()
            ->map(function ($message) {
                $guest = null;
                $cast = null;
                $content = null;
                $image = null;
                $gift = null;
                
                // Get guest name - try direct relationship first, then through chat
                if ($message->sender_guest_id && $message->guest) {
                    $guest = $message->guest->nickname ?? $message->guest->phone;
                } elseif ($message->chat && $message->chat->guest) {
                    $guest = $message->chat->guest->nickname ?? $message->chat->guest->phone;
                }
                
                // Get cast name - try direct relationship first, then through chat
                if ($message->sender_cast_id && $message->cast) {
                    $cast = $message->cast->nickname ?? $message->cast->phone;
                } elseif ($message->chat && $message->chat->cast) {
                    $cast = $message->chat->cast->nickname ?? $message->chat->cast->phone;
                }

                // Handle content - check for text, image, or gift
                if ($message->message) {
                    $content = substr($message->message, 0, 50) . (strlen($message->message) > 50 ? '...' : '');
                } elseif ($message->image) {
                    $content = '[画像]';
                    $image = asset('storage/' . $message->image);
                } elseif ($message->gift_id && $message->gift) {
                    $content = '[ギフト] ' . $message->gift->name;
                    $gift = [
                        'id' => $message->gift->id,
                        'name' => $message->gift->name,
                        'icon' => $message->gift->icon, // Direct emoji, not file path
                        'points' => $message->gift->points
                    ];
                } else {
                    $content = 'No content';
                }

                // Safe date formatting
                $date = 'Unknown';
                if ($message->created_at) {
                    if ($message->created_at instanceof Carbon) {
                        $date = $message->created_at->format('Y-m-d H:i');
                    } else {
                        // If it's a string, try to parse it
                        try {
                            $date = Carbon::parse($message->created_at)->format('Y-m-d H:i');
                        } catch (\Exception $e) {
                            $date = $message->created_at;
                        }
                    }
                }

                return [
                    'id' => $message->id,
                    'guest' => $guest ?? 'Unknown',
                    'cast' => $cast ?? 'Unknown',
                    'content' => $content,
                    'image' => $image,
                    'gift' => $gift,
                    'date' => $date,
                ];
            });

        return response()->json(['messages' => $messages]);
    }
} 