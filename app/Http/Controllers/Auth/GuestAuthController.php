<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Reservation;
use App\Models\Notification;
use App\Models\Badge;

class GuestAuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|unique:guests,phone',
            'verification_code' => 'required|string',
            'nickname' => 'required|string|max:50',
            'location' => 'required|string|max:50',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480',
            'interests' => 'nullable|string',
            'age' => 'nullable|string',
            'shiatsu' => 'nullable|string',
            'alcohol' => 'nullable|string',
            'tobacco' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (empty($request->verification_code)) {
            return response()->json(['message' => 'Invalid verification code'], 422);
        }

        $data = [
            'phone' => $request->phone,
            'nickname' => $request->nickname,
            'location' => $request->location,
            'age' => $request->age,
            'shiatsu' => $request->shiatsu,
        ];
        // Map location to favorite_area for backward compatibility
        if ($request->has('favorite_area')) {
            $data['favorite_area'] = $request->favorite_area;
        } elseif ($request->has('location')) {
            $data['favorite_area'] = $request->location;
        }

        // Handle avatar upload
        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('avatars', $fileName, 'public');
            $data['avatar'] = 'avatars/' . $fileName;
        }

        // Save interests as JSON
        if ($request->has('interests')) {
            $interests = json_decode($request->input('interests'), true);
            if (is_array($interests)) {
                $data['interests'] = $interests;
            }
        }

        // Create the guest
        $guest = Guest::create($data);

        // Log the guest in using Laravel session (guest guard)
        \Illuminate\Support\Facades\Auth::guard('guest')->login($guest);

        return response()->json([
            'message' => 'Guest registered successfully',
            'guest' => $guest,
            'token' => base64_encode('guest|'.$guest->id.'|'.now()), // placeholder token
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }
        $guest = Guest::where('phone', $request->phone)->first();
        if (!$guest) {
            $guest = Guest::create(['phone' => $request->phone]);
        }
        // Log the guest in using Laravel session (guest guard)
        \Illuminate\Support\Facades\Auth::guard('guest')->login($guest);
        return response()->json([
            'guest' => $guest,
            'token' => base64_encode('guest|' . $guest->id . '|' . now()), // placeholder token
        ]);
    }

    public function likeStatus($cast_id, $guest_id)
    {
        $like = \App\Models\Like::where('cast_id', $cast_id)->where('guest_id', $guest_id)->first();
        return response()->json(['liked' => $like ? true : false]);
    }

    public function likeGuest(Request $request)
    {
        $castId = $request->input('cast_id');
        $guestId = $request->input('guest_id');
        if (!$castId || !$guestId) {
            return response()->json(['message' => 'cast_id and guest_id are required'], 422);
        }
        $like = \App\Models\Like::where('cast_id', $castId)->where('guest_id', $guestId)->first();
        if ($like) {
            return response()->json(['liked' => false, 'message' => 'Already liked']);
        } else {
            \App\Models\Like::create(['cast_id' => $castId, 'guest_id' => $guestId]);
            return response()->json(['liked' => true]);
        }
    }

    public function getProfile($phone)
    {
        $guest = Guest::where('phone', $phone)->first();
        
        if (!$guest) {
            return response()->json(['message' => 'Guest not found'], 404);
        }
        
        return response()->json([
            'guest' => $guest,
            'interests' => $guest->interests ?? [],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|exists:guests,phone',
            'nickname' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:50',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480',
            'birth_year' => 'nullable|integer|min:1900|max:' . (date('Y') - 18),
            'height' => 'nullable|integer|min:100|max:250',
            'residence' => 'nullable|string|max:100',
            'birthplace' => 'nullable|string|max:100',
            'annual_income' => 'nullable|string|max:100',
            'education' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:100',
            'alcohol' => 'nullable|in:未選択,飲まない,飲む,ときどき飲む',
            'tobacco' => 'nullable|in:未選択,吸わない,吸う（電子タバコ）,吸う（紙巻きたばこ）,ときどき吸う',
            'siblings' => 'nullable|string|max:100',
            'cohabitant' => 'nullable|string|max:100',
            'pressure' => 'nullable|in:weak,medium,strong',
            'favorite_area' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'phone', 'line_id', 'nickname', 'birth_year', 'height', 'residence', 'birthplace','location',
            'annual_income', 'education', 'occupation', 'alcohol', 'tobacco', 'siblings', 'cohabitant',
            'pressure', 'favorite_area'
        ]);
        
        // Handle avatar upload
        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('avatars', $fileName, 'public');
            $data['avatar'] = 'avatars/' . $fileName;
        }
        
        // Remove null values to avoid overwriting existing data with null
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        // Map location to favorite_area for backward compatibility
        if (isset($data['favorite_area'])) {
            $data['favorite_area'] = $data['favorite_area'];
        } elseif (isset($data['location'])) {
            $data['favorite_area'] = $data['location'];
        }
        
        $guest = Guest::updateOrCreate(
            ['phone' => $data['phone']],
            $data
        );
        
        return response()->json([
            'message' => 'Profile updated successfully',
            'guest' => $guest
        ]);
    }

    public function getAvatar($filename)
    {
        // Use the public storage path where avatars should be stored
        $path = storage_path('app/public/avatars/' . $filename);
        
        if (!file_exists($path)) {
            // Fallback to the private path for existing files
            $path = storage_path('app/private/public/avatars/' . $filename);
            
            if (!file_exists($path)) {
                abort(404);
            }
        }
        
        return response()->file($path);
    }

    public function createReservation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_id' => 'required|exists:guests,id',
            'type' => 'nullable|in:free,pishatto',
            'scheduled_at' => 'required|date',
            'location' => 'nullable|string|max:255',
            'duration' => 'nullable|integer',
            'details' => 'nullable|string',
            'time' => 'nullable|string|max:10',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $reservation = Reservation::create($request->only([
            'guest_id', 'type', 'scheduled_at', 'location', 'duration', 'details', 'time'
        ]));
        // Real-time ranking update for guest
        $rankingService = app(\App\Services\RankingService::class);
        $rankingService->updateRealTimeRankings($reservation->location ?? '全国');
        // Broadcast reservation creation
        event(new \App\Events\ReservationUpdated($reservation));
        return response()->json(['reservation' => $reservation], 201);
    }

    public function listReservations($guest_id)
    {
        $reservations = \App\Models\Reservation::where('guest_id', $guest_id)->orderBy('scheduled_at', 'desc')->get();
        return response()->json(['reservations' => $reservations]);
    }

    public function matchReservation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'cast_id' => 'required|exists:casts,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $reservation = Reservation::find($request->reservation_id);

        $reservation->active = false;
        $reservation->save();
        // Real-time ranking update for both guest and cast
        $rankingService = app(\App\Services\RankingService::class);
        $rankingService->updateRealTimeRankings($reservation->location ?? '全国');
        // Broadcast reservation update
        // event(new \App\Events\ReservationUpdated($reservation));
        // Only create a chat if one does not already exist for this reservation and cast
        $chat = \App\Models\Chat::where('reservation_id', $reservation->id)
            ->where('cast_id', $request->cast_id)
            ->first();
        if (!$chat) {
            $chat = \App\Models\Chat::create([
                'guest_id' => $reservation->guest_id,
                'cast_id' => $request->cast_id,
                'reservation_id' => $reservation->id,
            ]);
        }
        // // Notify guest
        $notification = Notification::create([
            'user_id' => $reservation->guest_id,
            'user_type' => 'guest',
            'type' => 'order_matched',
            'reservation_id' => $reservation->id,
            'message' => '予約がキャストにマッチされました',
            'read' => false,
        ]);
        // Broadcast notification
        event(new \App\Events\NotificationSent($notification));
        // Return reservation with attached casts
        return response()->json([
            'message' => 'Reservation matched and group chat created',
            'chat' => $chat,
            'reservation' => $reservation->toArray(),
        ]);
    }

    public function getUserChats($userType, $userId)
    {
        if ($userType === 'guest') {
            $chats = \App\Models\Chat::where('guest_id', $userId)->with(['cast', 'messages'])->get();
            $result = $chats->map(function ($chat) use ($userId) {
                // Count unread messages for this guest in this chat
                $unread = $chat->messages->where('is_read', false)
                    ->filter(function($msg) {
                        return $msg->sender_cast_id && !$msg->is_read;
                    })->count();
                return [
                    'id' => $chat->id,
                    'avatar' => $chat->cast ? $chat->cast->avatar : null,
                    'cast_id' => $chat->cast ? $chat->cast->id : null,
                    'cast_nickname' => $chat->cast ? $chat->cast->nickname : null,
                    'last_message' => $chat->messages->last()->message ?? '',
                    'updated_at' => $chat->updated_at ?? null,
                    'unread' => $unread,
                ];
            });
            return response()->json(['chats' => $result]);
        } else {
            // For cast, join reservation and guest to get guest avatar
            $chats = \App\Models\Chat::where('cast_id', $userId)->with(['guest', 'reservation.guest', 'messages'])->get();
            $result = $chats->map(function ($chat) use ($userId) {
                $guest = $chat->guest;
                if (!$guest && $chat->reservation && $chat->reservation->guest) {
                    $guest = $chat->reservation->guest;
                }
                // Count unread messages for this cast in this chat
                $unread = $chat->messages->where('is_read', false)
                    ->filter(function($msg) {
                        return $msg->sender_guest_id && !$msg->is_read;
                    })->count();
                return [
                    'id' => $chat->id,
                    'avatar' => $guest ? $guest->avatar : null,
                    'guest_id' => $guest ? $guest->id : null,
                    'guest_nickname' => $guest ? $guest->nickname : null,
                    'last_message' => $chat->messages->last()->message ?? '',
                    'updated_at' => $chat->created_at ?? null,
                    'unread' => $unread,
                ];
            });
            return response()->json(['chats' => $result]);
        }
    }

    public function allChats()
    {
        $chats = \App\Models\Chat::all();
        return response()->json(['chats' => $chats]);
    }

    public function getReservationById($id)
    {
        $reservation = \App\Models\Reservation::find($id);
        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }
        return response()->json(['reservation' => $reservation]);
    }

    public function repeatGuests(Request $request)
    {
        $guests = \App\Models\Guest::withCount('reservations')
            ->having('reservations_count', '>', 1)
            ->get(['id', 'nickname', 'avatar']);
        
        $result = $guests->map(function ($guest) {
            return [
                'id' => $guest->id,
                'nickname' => $guest->nickname,
                'avatar' => $guest->avatar,
                'reservations_count' => $guest->reservations_count,
            ];
        });
        return response()->json(['guests' => $result]);
    }

    public function getProfileById($id)
    {
        $guest = \App\Models\Guest::find($id);
        if (!$guest) {
            return response()->json(['message' => 'Guest not found'], 404);
        }
        return response()->json(['guest' => $guest]);
    }

    // Fetch notifications for a user
    public function getNotifications($userType, $userId)
    {
        $notifications = Notification::where('user_type', $userType)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['notifications' => $notifications]);
    }

    // Mark a notification as read
    public function markNotificationRead($id)
    {
        $notification = Notification::find($id);
        if ($notification) {
            $notification->read = true;
            $notification->save();
        }
        return response()->json(['success' => true]);
    }

    // Mark all notifications as read for a user
    public function markAllNotificationsRead($userType, $userId)
    {
        \App\Models\Notification::where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('read', false)
            ->update(['read' => true]);
        return response()->json(['success' => true]);
    }

    // Add this method to fetch all guest phone numbers
    public function allPhones()
    {
        $phones = Guest::pluck('phone');
        return response()->json(['phones' => $phones]);
    }

    public function updateReservation(Request $request, $id)
    {
        try {
            $reservation = Reservation::find($id);
            if (!$reservation) {
                return response()->json(['message' => 'Reservation not found'], 404);
            }
            $validator = Validator::make($request->all(), [
                'scheduled_at' => 'sometimes|date',
                'duration' => 'sometimes|integer',
                'location' => 'sometimes|string|max:255',
                'details' => 'sometimes|string',
                'time' => 'sometimes|string|max:10',
                'started_at' => 'sometimes|date',
                'ended_at' => 'sometimes|date',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            $reservation->fill($request->only(['scheduled_at', 'duration', 'location', 'details', 'time', 'started_at', 'ended_at', 'feedback_text', 'feedback_rating', 'feedback_badge_id']));

            // Points calculation logic
            if ($reservation->started_at && $reservation->ended_at) {
                $scheduled = $reservation->scheduled_at ? strtotime($reservation->scheduled_at) : null;
                $start = strtotime($reservation->started_at);
                $end = strtotime($reservation->ended_at);
                $duration = $reservation->duration ?? 1;
                $planned_end = $scheduled ? strtotime("+{$duration} hour", $scheduled) : ($start + $duration * 3600);
                $base_points = $duration * 1000;
                $overtime_points = 0;
                $exceeded_seconds = 0;
                if ($end > $planned_end) {
                    $exceeded_seconds = $end - $planned_end;
                    $exceeded_minutes = ceil($exceeded_seconds / 60);
                    $overtime_points = $exceeded_minutes * 20;
                }
                $reservation->points_earned = $base_points + $overtime_points;
            }

            // Assign badge to cast if feedback_badge_id is present
            if ($request->filled('feedback_badge_id')) {
                $badgeId = $request->input('feedback_badge_id');
                // Find the cast for this reservation (assuming a cast_id field or relationship exists)
                $castId = $request->input('cast_id') ?? ($reservation->cast_id ?? null);
                if ($castId) {
                    $cast = \App\Models\Cast::find($castId);
                    if ($cast && !$cast->badges()->where('badges.id', $badgeId)->exists()) {
                        $cast->badges()->attach($badgeId);
                    }
                }
            }

            $reservation->save();
            // Broadcast reservation update
            event(new \App\Events\ReservationUpdated($reservation));
            return response()->json(['reservation' => $reservation]);
        } catch (\Throwable $e) {
            \Log::error('updateReservation error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    // Add this method to return all badges
    public function getAllBadges()
    {
        return response()->json(['badges' => Badge::all()]);
    }
} 