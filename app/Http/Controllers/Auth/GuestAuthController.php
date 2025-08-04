<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\ChatFavorite;        
use App\Models\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Reservation;
use App\Models\Notification;
use App\Models\Badge;
use App\Services\PointTransactionService;
use App\Services\TwilioService;

class GuestAuthController extends Controller
{
    protected $pointTransactionService;
    protected $twilioService;

    public function __construct(PointTransactionService $pointTransactionService, TwilioService $twilioService)
    {
        $this->pointTransactionService = $pointTransactionService;
        $this->twilioService = $twilioService;
    }

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

        // Check if verification code has been verified
        $phoneNumber = $request->phone;
        $phoneNumber = $this->formatPhoneNumberForTwilio($phoneNumber);
        
        $isVerified = $this->twilioService->isCodeVerified($phoneNumber, $request->verification_code);
        
        if (!$isVerified) {
            return response()->json(['message' => 'Verification code not found or already used'], 422);
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
            'verification_code' => 'required|string|size:6',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        // Verify SMS code
        $phoneNumber = $request->phone;
        $phoneNumber = $this->formatPhoneNumberForTwilio($phoneNumber);
        
        $verificationResult = $this->twilioService->verifyCode($phoneNumber, $request->verification_code);
        
        if (!$verificationResult['success']) {
            return response()->json(['message' => $verificationResult['message']], 422);
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

        try {
            DB::beginTransaction();

            // Create the reservation
            $reservation = Reservation::create($request->only([
                'guest_id', 'type', 'scheduled_at', 'location', 'duration', 'details', 'time'
            ]));

            // Calculate required points for this reservation (including night time bonus)
            $requiredPoints = $this->pointTransactionService->calculateReservationPointsLegacy($reservation);

            // Get the guest and check if they have enough points
            $guest = Guest::find($request->guest_id);
            if (!$guest) {
                DB::rollBack();
                return response()->json(['message' => 'Guest not found'], 404);
            }

            \Log::info('Reservation creation - point calculation', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'guest_points_before' => $guest->points,
                'required_points' => $requiredPoints,
                'duration' => $reservation->duration,
                'details' => $reservation->details
            ]);

            if ($guest->points < $requiredPoints) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Insufficient points',
                    'required_points' => $requiredPoints,
                    'available_points' => $guest->points
                ], 400);
            }

            // Deduct points from guest and mark as pending
            $guest->points -= $requiredPoints;
            $guest->save();

            \Log::info('Points deducted from guest', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'points_deducted' => $requiredPoints,
                'guest_points_after' => $guest->points
            ]);

            // Create a pending point transaction record
            $pendingTransaction = PointTransaction::create([
                'guest_id' => $guest->id,
                'cast_id' => null,
                'type' => 'pending',
                'amount' => $requiredPoints,
                'reservation_id' => $reservation->id,
                'description' => "Reservation created - {$reservation->duration} hours (pending)"
            ]);

            \Log::info('Pending point transaction created', [
                'transaction_id' => $pendingTransaction->id,
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'amount' => $requiredPoints
            ]);

            DB::commit();

            \Log::info('Reservation created successfully', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'points_deducted' => $requiredPoints,
                'remaining_points' => $guest->points
            ]);

            // Real-time ranking update for guest
            $rankingService = app(\App\Services\RankingService::class);
            $rankingService->updateRealTimeRankings($reservation->location ?? '全国');
            
            // Broadcast reservation creation
            event(new \App\Events\ReservationUpdated($reservation));
            
            return response()->json([
                'reservation' => $reservation,
                'points_deducted' => $requiredPoints,
                'remaining_points' => $guest->points
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('createReservation error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to create reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listReservations($guest_id)
    {
        $reservations = \App\Models\Reservation::where('guest_id', $guest_id)->orderBy('scheduled_at', 'desc')->get();
        return response()->json(['reservations' => $reservations]);
    }

    public function matchReservation(Request $request)
    {
        // This method is now deprecated - use ReservationApplicationController::apply instead
        return response()->json([
            'message' => 'This endpoint is deprecated. Please use /reservation-applications/apply instead.',
            'error' => 'DEPRECATED_ENDPOINT'
        ], 400);
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
                    'created_at' => $chat->created_at ?? null,
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
                    'guest_age' => $guest ? $guest->birth_year : null,
                    'last_message' => $chat->messages->last()->message ?? '',
                    'updated_at' => $chat->created_at ?? null,
                    'created_at' => $chat->created_at ?? null,
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
        \Log::info('Fetching notifications', ['user_type' => $userType, 'user_id' => $userId]);
        
        try {
            $notifications = Notification::where('user_type', $userType)
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            \Log::info('Found notifications', ['count' => $notifications->count()]);

            // For notifications with cast_id, fetch cast information
            foreach ($notifications as $notification) {
                if ($notification->cast_id) {
                    $cast = \App\Models\Cast::find($notification->cast_id);
                    if ($cast) {
                        $notification->cast = [
                            'id' => $cast->id,
                            'nickname' => $cast->nickname,
                            'avatar' => $cast->avatar,
                        ];
                    }
                }
            }

            \Log::info('Returning notifications', ['notifications' => $notifications->toArray()]);
            return response()->json(['notifications' => $notifications]);
        } catch (\Exception $e) {
            \Log::error('Error fetching notifications', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to fetch notifications'], 500);
        }
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

    // Delete a notification
    public function deleteNotification($id)
    {
        $notification = Notification::find($id);
        if ($notification) {
            $notification->delete();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
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
            $reservation->fill($request->only(['scheduled_at', 'duration', 'location', 'details', 'time', 'started_at', 'ended_at']));

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



            $reservation->save();
            // Broadcast reservation update
            event(new \App\Events\ReservationUpdated($reservation));
            return response()->json(['reservation' => $reservation]);
        } catch (\Throwable $e) {
            \Log::error('updateReservation error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    /**
     * Complete reservation and process point transactions
     */
    public function completeReservation(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $reservation = Reservation::with(['guest', 'cast'])->findOrFail($id);
            
            // Check if reservation has a cast assigned
            if (!$reservation->cast_id) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Reservation has no cast assigned'
                ], 400);
            }

            // Mark reservation as completed
            $reservation->ended_at = now();
            $reservation->save();

            // Get the cast
            $cast = $reservation->cast;
            if (!$cast) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Cast not found'
                ], 404);
            }

            // Use the service to process the reservation completion
            $success = $this->pointTransactionService->processReservationCompletion($reservation);
            
            if (!$success) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Failed to process point transaction'
                ], 500);
            }

            DB::commit();

            // Get the updated reservation with points_earned
            $reservation->refresh();

            // Find the pending transaction to get refund information
            $pendingTransaction = PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->first();
            
            $refundTransaction = PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'convert')
                ->where('description', 'like', '%refunded unused points%')
                ->first();

            $refundAmount = $refundTransaction ? $refundTransaction->amount : 0;
            $reservedAmount = $pendingTransaction ? $pendingTransaction->amount : 0;

            \Log::info('Reservation completion processed successfully', [
                'reservation_id' => $reservation->id,
                'guest_id' => $reservation->guest_id,
                'cast_id' => $cast->id,
                'points_earned' => $reservation->points_earned,
                'points_reserved' => $reservedAmount,
                'points_refunded' => $refundAmount
            ]);

            return response()->json([
                'message' => 'Reservation completed successfully',
                'reservation' => $reservation,
                'points_transferred' => $reservation->points_earned,
                'points_reserved' => $reservedAmount,
                'points_refunded' => $refundAmount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('completeReservation error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to complete reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel reservation and refund pending points
     */
    public function cancelReservation(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $reservation = Reservation::with(['guest'])->findOrFail($id);
            
            // Check if reservation is still active and not completed
            if (!$reservation->active || $reservation->ended_at) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Reservation cannot be cancelled'
                ], 400);
            }

            // Find the pending point transaction for this reservation
            $pendingTransaction = PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->first();

            if (!$pendingTransaction) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No pending point transaction found for this reservation'
                ], 400);
            }

            // Get the guest
            $guest = $reservation->guest;
            if (!$guest) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Guest not found'
                ], 404);
            }

            // Refund points to guest
            $guest->points += $pendingTransaction->amount;
            $guest->save();

            // Update the pending transaction to cancelled
            $pendingTransaction->type = 'convert';
            $pendingTransaction->description = "Reservation cancelled - refunded {$pendingTransaction->amount} points";
            $pendingTransaction->save();

            // Mark reservation as cancelled
            $reservation->active = false;
            $reservation->save();

            DB::commit();

            \Log::info('Reservation cancelled and points refunded', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'points_refunded' => $pendingTransaction->amount
            ]);

            return response()->json([
                'message' => 'Reservation cancelled successfully',
                'reservation' => $reservation,
                'points_refunded' => $pendingTransaction->amount,
                'remaining_points' => $guest->points
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('cancelReservation error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to cancel reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refund unused points for a completed reservation
     * This can be used to fix reservations that were completed before the refund logic was implemented
     */
    public function refundUnusedPoints(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $reservation = Reservation::with(['guest'])->findOrFail($id);
            
            // Check if reservation is completed
            if (!$reservation->ended_at) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Reservation is not completed yet'
                ], 400);
            }

            // Use the service to refund unused points
            $success = $this->pointTransactionService->refundUnusedPoints($reservation);
            
            if (!$success) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Failed to refund unused points or no unused points to refund'
                ], 400);
            }

            DB::commit();

            // Get the guest to return updated points
            $guest = $reservation->guest;
            $guest->refresh();

            \Log::info('Unused points refunded successfully', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'guest_points_after_refund' => $guest->points
            ]);

            return response()->json([
                'message' => 'Unused points refunded successfully',
                'reservation_id' => $reservation->id,
                'guest_points' => $guest->points
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('refundUnusedPoints error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to refund unused points',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload avatar for guest
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:20480',
            'phone' => 'required|string',
        ]);

        try {
            $file = $request->file('avatar');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('avatars', $fileName, 'public');
            
            // Find the guest by phone number
            $guest = Guest::where('phone', $request->phone)->first();
            if (!$guest) {
                return response()->json(['error' => 'Guest not found'], 404);
            }

            // If guest already has an avatar, delete the old one
            if ($guest->avatar) {
                $oldAvatarPath = $guest->avatar;
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldAvatarPath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldAvatarPath);
                }
            }

            // Update the guest with the new avatar path
            $guest->update(['avatar' => $path]);

            return response()->json([
                'message' => 'Avatar uploaded successfully',
                'avatar' => $path
            ]);

        } catch (\Exception $e) {
            \Log::error('Avatar upload error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to upload avatar',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete avatar for guest
     */
    public function deleteAvatar(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        try {
            // Find the guest by phone number
            $guest = Guest::where('phone', $request->phone)->first();
            if (!$guest) {
                return response()->json(['error' => 'Guest not found'], 404);
            }

            // If guest has an avatar, delete it from storage
            if ($guest->avatar) {
                $avatarPath = $guest->avatar;
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($avatarPath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($avatarPath);
                }
                
                // Clear the avatar field in database
                $guest->update(['avatar' => null]);
            }

            return response()->json([
                'message' => 'Avatar deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Avatar delete error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete avatar',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Add this method to return all badges
    public function getAllBadges()
    {
        return response()->json(['badges' => Badge::all()]);
    }

    // Add a chat to guest's favorites
    public function favoriteChat(Request $request)
    {
        $guestId = $request->input('guest_id');
        $chatId = $request->input('chat_id');
        
        $guest = Guest::findOrFail($guestId);
        $chat = \App\Models\Chat::findOrFail($chatId);
        
        if ($guest->chatFavorites()->where('chat_id', $chatId)->exists()) {
            return response()->json(['favorited' => true, 'message' => 'Already favorited']);
        }
        
        $guest->chatFavorites()->create([
            'chat_id' => $chatId,
            'created_at' => now()
        ]);
        
        return response()->json(['favorited' => true]);
    }

    // Remove a chat from guest's favorites
    public function unfavoriteChat(Request $request)
    {
        $guestId = $request->input('guest_id');
        $chatId = $request->input('chat_id');
        
        $guest = Guest::findOrFail($guestId);
        $guest->chatFavorites()->where('chat_id', $chatId)->delete();
        
        return response()->json(['favorited' => false]);
    }

    // List all favorite chats for a guest
    public function favoriteChats($guestId)
    {
        $guest = Guest::with(['favoritedChats.cast', 'favoritedChats.messages' => function($q) {
            $q->orderBy('created_at', 'desc');
        }])->findOrFail($guestId);
        
        $favoritedChats = $guest->favoritedChats->map(function ($chat) {
            $cast = $chat->cast;
            $lastMessage = $chat->messages->first();
            
            return [
                'id' => $chat->id,
                'cast_id' => $chat->cast_id,
                'cast_nickname' => $cast ? $cast->nickname : 'Unknown',
                'cast_avatar' => $cast ? $cast->avatar : null,
                'last_message' => $lastMessage ? $lastMessage->message : null,
                'last_message_time' => $lastMessage ? $lastMessage->created_at : null,
                'unread' => $chat->messages->where('is_read', false)->count(),
                'created_at' => $chat->created_at,
                'favorited_at' => $chat->pivot->created_at ?? null
            ];
        });
        
        return response()->json(['chats' => $favoritedChats]);
    }

    // Check if a chat is favorited by a guest
    public function isChatFavorited($chatId, $guestId)
    {
        $favorite = ChatFavorite::where('chat_id', $chatId)
            ->where('guest_id', $guestId)
            ->first();
        
        return response()->json(['favorited' => $favorite ? true : false]);
    }

    /**
     * Get admin news based on user type and target_type filtering
     */
    public function getAdminNews($userType, $userId = null)
    {
        // Import AdminNews model
        $adminNews = \App\Models\AdminNews::where('status', 'published')
            ->where(function($query) use ($userType) {
                $query->where('target_type', 'all')
                      ->orWhere('target_type', $userType);
            })
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'content' => $item->content,
                    'target_type' => $item->target_type,
                    'published_at' => $item->published_at ? $item->published_at->format('Y/m/d') : null,
                    'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json(['news' => $adminNews]);
    }

    /**
     * Format phone number for Twilio
     * Remove leading 0 and add 81 for Japanese phone numbers
     */
    private function formatPhoneNumberForTwilio($phoneNumber)
    {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Ensure it starts with 0 (Japanese format)
        if (!str_starts_with($phoneNumber, '0')) {
            throw new \InvalidArgumentException('Phone number must start with 0');
        }
        
        // Remove leading 0 and add +81
        $formattedNumber = '+81' . ltrim($phoneNumber, '0');
        
        return $formattedNumber;
    }
} 