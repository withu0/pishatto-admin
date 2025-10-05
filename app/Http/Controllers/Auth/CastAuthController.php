<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cast;
use App\Models\Badge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Like;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Reservation;
use App\Services\InfobipService;
use App\Events\NotificationSent;

class CastAuthController extends Controller
{
    protected $infobipService;

    public function __construct(InfobipService $infobipService)
    {
        $this->infobipService = $infobipService;
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            // Accept code if provided, but do not require it because phone may already be verified
            'verification_code' => 'nullable|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        // Verify via cached verified state first. If not verified yet, attempt direct code verification when provided.
        $phoneNumber = $request->phone;
        $phoneNumber = $this->formatPhoneNumberForInfobip($phoneNumber);

        $isVerified = $this->infobipService->isPhoneVerified($phoneNumber);
        $errorMessage = 'Phone number not verified. Please verify your number again.';

        if (!$isVerified && $request->filled('verification_code')) {
            $verificationResult = $this->infobipService->verifyCode($phoneNumber, $request->verification_code);
            $isVerified = $verificationResult['success'];
            if (!$isVerified) {
                $errorMessage = $verificationResult['message'] ?? $errorMessage;
            }
        }

        if (!$isVerified) {
            return response()->json(['message' => $errorMessage], 422);
        }

        $cast = Cast::where('phone', $request->phone)->first();
        if (!$cast) {
            return response()->json([
                'message' => 'お客様の情報は存在しません。管理者までご連絡ください。',
                'cast' => null
            ], 404);
        }

        // Log the cast in using Laravel session (cast guard)
        \Illuminate\Support\Facades\Auth::guard('cast')->login($cast);
        return response()->json([
            'cast' => $cast,
            'token' => base64_encode('cast|' . $cast->id . '|' . now()), // placeholder token
        ]);
    }

    /**
     * Check if cast exists by phone number
     */
    public function checkCastExists(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid phone number'], 422);
        }

        $cast = Cast::where('phone', $request->phone)->first();

        if (!$cast) {
            return response()->json([
                'exists' => false,
                'message' => 'お客様の情報は存在しません。管理者までご連絡ください。'
            ], 404);
        }

        return response()->json([
            'exists' => true,
            'cast' => $cast
        ]);
    }

    /**
     * Check if cast is authenticated
     */
    public function checkAuth(Request $request)
    {
        if (Auth::guard('cast')->check()) {
            $cast = Auth::guard('cast')->user();
            return response()->json([
                'authenticated' => true,
                'cast' => $cast,
            ]);
        }

        return response()->json([
            'authenticated' => false,
            'cast' => null,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->only([
            'id', 'phone', 'line_id', 'password', 'nickname', 'avatar', 'birth_year', 'height', 'residence',
            'birthplace', 'profile_text', 'created_at', 'updated_at', 'grade_points'
        ]);

        if (!empty($data['id'])) {
            $cast = \App\Models\Cast::find($data['id']);
            if (!$cast) {
                return response()->json(['message' => 'Cast not found'], 404);
            }
            // Only update fields that are present in the request
            foreach ($data as $key => $value) {
                if ($key !== 'id' && $value !== null) {
                    $cast->$key = $value;
                }
            }
            $cast->save();
        } else if (!empty($data['phone'])) {
            $cast = \App\Models\Cast::updateOrCreate(
                ['phone' => $data['phone']],
                $data
            );
        } else {
            return response()->json(['message' => 'ID or Phone is required'], 422);
        }
        return response()->json(['cast' => $cast]);
    }


    public function allReservations()
    {
        try {
            $reservations = \App\Models\Reservation::where('type', 'free')
                ->orderBy('scheduled_at', 'desc')
                ->get();

            // Get all casts to calculate points based on their categories
            $casts = \App\Models\Cast::all()->keyBy('id');

            // Add calculated points to each reservation
            $reservations->each(function ($reservation) use ($casts) {
                // Calculate points based on cast category and duration
                // Formula: category_points * duration * 60 / 30
                $totalPoints = 0;

                // Check if reservation has cast_ids
                if ($reservation->cast_ids) {
                    $castIds = $reservation->cast_ids;
                    if (is_array($castIds) && !empty($castIds)) {
                        // Calculate points based on the first cast's category (or average if multiple)
                        $categoryPoints = 0;
                        $castCount = 0;

                        foreach ($castIds as $castId) {
                            if (isset($casts[$castId])) {
                                $categoryPoints += $casts[$castId]->getCategoryPointsAttribute();
                                $castCount++;
                            }
                        }

                        if ($castCount > 0) {
                            $averageCategoryPoints = $categoryPoints / $castCount;
                            $duration = $reservation->duration ?? 1;
                            $totalPoints = $averageCategoryPoints * $duration * 60 / 30;
                        }
                    }
                }

                // Fallback to default calculation if no cast_ids or casts not found
                if ($totalPoints === 0) {
                    $defaultCategoryPoints = 12000; // Default to プレミアム
                    $duration = $reservation->duration ?? 1;
                    $totalPoints = $defaultCategoryPoints * $duration * 60 / 30;
                }

                $reservation->calculated_points = $totalPoints;
            });

            return response()->json(['reservations' => $reservations]);
        } catch (\Exception $e) {
            Log::error('Error in allReservations: ' . $e->getMessage());
            return response()->json(['reservations' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function getProfile($id)
    {
        $cast = Cast::with(['badges', 'receivedGifts'])->find($id);
        if (!$cast) {
            return response()->json(['message' => 'Cast not found'], 404);
        }
        // Get recommended casts (top 3 by recent, excluding self)
        $recommended = Cast::where('id', '!=', $id)->orderBy('created_at', 'desc')->limit(3)->get();
        // Get badges with counts for this cast from feedback table
        $badgesWithCounts = Badge::select('badges.*', DB::raw('COUNT(feedback.badge_id) as count'))
            ->join('feedback', 'badges.id', '=', 'feedback.badge_id')
            ->where('feedback.cast_id', $id)
            ->groupBy('badges.id', 'badges.name', 'badges.icon', 'badges.description', 'badges.created_at', 'badges.updated_at')
            ->get();

        return response()->json([
            'cast' => $cast,
            'reservations' => [],
            'badges' => $badgesWithCounts ?? [],
            'titles' => [],
            'recommended' => $recommended,
        ]);
    }

    public function getCastProfile($id)
    {
        $cast = Cast::find($id);
        if (!$cast) {
            return response()->json(['message' => 'Cast not found'], 404);
        }
        return response()->json(['cast' => $cast]);
    }

    public function getCastPointsData($id)
    {
        $cast = \App\Models\Cast::find($id);
        if (!$cast) {
            return response()->json(['message' => 'Cast not found'], 404);
        }

        // Get current month's start and end dates
        $currentMonth = now()->startOfMonth();
        $nextMonth = now()->addMonth()->startOfMonth();

        // Calculate total points earned from point_transactions (gift + transfer)
        $monthlyTotalPoints = \App\Models\PointTransaction::where('cast_id', $id)
            ->whereIn('type', ['gift', 'transfer'])
            ->whereBetween('created_at', [$currentMonth, $nextMonth])
            ->sum('amount');

        // Calculate gift points specifically
        $giftPoints = \App\Models\PointTransaction::where('cast_id', $id)
            ->where('type', 'gift')
            ->whereBetween('created_at', [$currentMonth, $nextMonth])
            ->sum('amount');

        // Calculate transfer points (from completed reservations)
        $transferPoints = \App\Models\PointTransaction::where('cast_id', $id)
            ->where('type', 'transfer')
            ->whereBetween('created_at', [$currentMonth, $nextMonth])
            ->sum('amount');

        // Calculate copat-back rate based on gift transactions
        $totalGiftTransactions = \App\Models\PointTransaction::where('cast_id', $id)
            ->where('type', 'gift')
            ->whereBetween('created_at', [$currentMonth, $nextMonth])
            ->count();

        $successfulGiftTransactions = \App\Models\PointTransaction::where('cast_id', $id)
            ->where('type', 'gift')
            ->where('amount', '>', 0)
            ->whereBetween('created_at', [$currentMonth, $nextMonth])
            ->count();

        $copatBackRate = $totalGiftTransactions > 0 ? round(($successfulGiftTransactions / $totalGiftTransactions) * 100) : 0;

        return response()->json([
            'monthly_total_points' => $monthlyTotalPoints,
            'gift_points' => $giftPoints,
            'transfer_points' => $transferPoints,
            'copat_back_rate' => $copatBackRate,
            'total_reservations' => $totalGiftTransactions,
            'completed_reservations' => $successfulGiftTransactions
        ]);
    }

    public function getAvailableCasts(Request $request)
    {
        $location = $request->query('location', '東京都');
        $limit = $request->query('limit', 50);

        $casts = \App\Models\Cast::where('location', $location)
            ->where('status', 'active')
            ->inRandomOrder()
            ->limit($limit)
            ->get(['id', 'nickname', 'avatar', 'location', 'grade']);

        return response()->json([
            'casts' => $casts,
            'total' => $casts->count(),
            'location' => $location
        ]);
    }

    public function getCastPassportData($id)
    {
        $cast = \App\Models\Cast::find($id);
        if (!$cast) {
            return response()->json(['message' => 'Cast not found'], 404);
        }

        // For now, return mock passport data since there's no passport/shop model yet
        // In a real implementation, you would fetch from a passport/shop table
        $passportData = [
            [
                'id' => 1,
                'name' => 'Partner Shop 1',
                'image' => '/assets/avatar/AdobeStock_1095142160_Preview.jpeg',
                'description' => 'Special discount for cast members'
            ],
            [
                'id' => 2,
                'name' => 'Partner Shop 2',
                'image' => '/assets/avatar/AdobeStock_1067731649_Preview.jpeg',
                'description' => 'Exclusive offers'
            ],
            [
                'id' => 3,
                'name' => 'Partner Shop 3',
                'image' => '/assets/avatar/AdobeStock_1190678828_Preview.jpeg',
                'description' => 'Premium services'
            ],
            [
                'id' => 4,
                'name' => 'Partner Shop 4',
                'image' => '/assets/avatar/AdobeStock_1537463438_Preview.jpeg',
                'description' => 'Luxury experiences'
            ],
            [
                'id' => 5,
                'name' => 'Partner Shop 5',
                'image' => '/assets/avatar/AdobeStock_1537463446_Preview.jpeg',
                'description' => 'VIP treatment'
            ]
        ];

        return response()->json(['passport_data' => $passportData]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|unique:casts,phone',
            'nickname' => 'nullable|string|max:50',
            'avatar' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'phone' => $request->phone,
            'nickname' => $request->nickname ?? '',
        ];
        if ($request->has('avatar')) {
            $data['avatar'] = $request->avatar;
        }

        $cast = Cast::create($data);

        // Log the cast in using Laravel session (cast guard)
        \Illuminate\Support\Facades\Auth::guard('cast')->login($cast);

        return response()->json([
            'message' => 'Cast registered successfully',
            'cast' => $cast,
            'token' => base64_encode('cast|' . $cast->id . '|' . now()),
        ], 201);
    }

    // List all casts (with optional filters)
    public function list(Request $request)
    {
        $query = Cast::query();

        // Apply area filter if provided
        if ($request->has('area') && $request->area) {
            $area = $request->area;
            $query->where(function($q) use ($area) {
                $q->where('residence', $area)
                  ->orWhere('residence', 'LIKE', $area . '/%')
                  ->orWhereRaw("SUBSTRING_INDEX(residence, '/', 1) = ?", [$area]);
            });
        }

        // Apply prefecture filter if provided
        if ($request->has('prefecture') && $request->prefecture) {
            $prefecture = $request->prefecture;
            $query->where(function($q) use ($prefecture) {
                $q->where('residence', $prefecture)
                  ->orWhere('residence', 'LIKE', '%/' . $prefecture . '%');
            });
        }

        // Apply search filter if provided
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nickname', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');

        switch ($sort) {
            case 'created_at':
                $query->orderBy('created_at', $order);
                break;
            case 'nickname':
                $query->orderBy('nickname', $order);
                break;
            case 'popularity':
                // You can implement popularity sorting based on your requirements
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $casts = $query->get();

        return response()->json(['casts' => $casts]);
    }

    // public function getCastCountsByLocation()
    // {
    //     try {
    //         $counts = Cast::select('residence', \DB::raw('count(*) as count'))
    //             ->whereNotNull('residence')
    //             ->where('residence', '!=', '')
    //             ->groupBy('residence')
    //             ->pluck('count', 'residence')
    //             ->toArray();

    //         return response()->json($counts);
    //     } catch (\Exception $e) {
    //         \Log::error('Error in getCastCountsByLocation: ' . $e->getMessage());
    //         return response()->json([], 500);
    //     }
    // }
    public function getCastCountsByLocation()
    {
        try {
            $counts = Cast::select(
                    // Extract everything before first slash, or full residence if no slash.
                    DB::raw("SUBSTRING_INDEX(residence, '/', 1) as residence_group"),
                    DB::raw('count(*) as count')
                )
                ->whereNotNull('residence')
                ->where('residence', '!=', '')
                ->groupBy('residence_group')
                ->pluck('count', 'residence_group')
                ->toArray();

            return response()->json($counts);
        } catch (\Exception $e) {
            Log::error('Error in getCastCountsByLocation: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }


    // Like or unlike a cast
    public function like(Request $request)
    {
        $guestId = $request->input('guest_id');
        $castId = $request->input('cast_id');
        $like = Like::where('guest_id', $guestId)->where('cast_id', $castId)->first();
        if ($like) {
            $like->delete();
            return response()->json(['liked' => false]);
        } else {
            Like::create(['guest_id' => $guestId, 'cast_id' => $castId]);

            // Send notification if enabled
            $cast = Cast::find($castId);
            if ($cast) {
                \App\Services\NotificationService::sendLikeNotification($guestId, $castId, $cast->nickname);
            }

            return response()->json(['liked' => true]);
        }
    }

    // Get all liked casts for a guest
    public function likedCasts($guestId)
    {
        $castIds = Like::where('guest_id', $guestId)->pluck('cast_id');
        $casts = Cast::whereIn('id', $castIds)->get();
        return response()->json(['casts' => $casts]);
    }

    // Record when a cast visits a guest profile
    public function recordGuestVisit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cast_id' => 'required|exists:casts,id',
            'guest_id' => 'required|exists:guests,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $castId = $request->input('cast_id');
        $guestId = $request->input('guest_id');

        // Check if this cast has already visited this guest recently (within 24 hours)
        $existingVisit = \App\Models\VisitHistory::where('cast_id', $castId)
            ->where('guest_id', $guestId)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($existingVisit) {
            // Update the existing visit timestamp
            $existingVisit->touch();
            return response()->json(['success' => true]);
        }

        // Create new visit record
        \App\Models\VisitHistory::create(['cast_id' => $castId, 'guest_id' => $guestId]);

        // Create notification for the guest about the cast visit
        $cast = Cast::find($castId);
        if ($cast) {
            \App\Services\NotificationService::sendFootprintNotification($guestId, $castId, $cast->nickname);
        }

        return response()->json(['success' => true]);
    }

    // Get visit history for a guest (casts who visited this guest's profile)
    public function visitHistory($guestId)
    {
        // Get unique casts that have visited this guest, with the most recent visit for each cast
        $history = \App\Models\VisitHistory::where('visit_histories.guest_id', $guestId)
            ->join('casts', 'visit_histories.cast_id', '=', 'casts.id')
            ->select('visit_histories.*', 'casts.nickname', 'casts.avatar')
            ->orderBy('visit_histories.updated_at', 'desc')
            ->get()
            ->unique('cast_id'); // Ensure we only get one record per cast

        return response()->json(['history' => $history]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:20480',
        ]);
        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');
        // Return the relative path to be saved in DB and used by frontend
        return response()->json(['path' => $path]);
    }

    public function deleteAvatar(Request $request)
    {
        $request->validate([
            'cast_id' => 'required|integer|exists:casts,id',
            'avatar_index' => 'required|integer|min:0',
        ]);

        $cast = Cast::findOrFail($request->cast_id);
        $avatarIndex = $request->input('avatar_index');

        if (!$cast->avatar) {
            return response()->json(['error' => 'No avatars found'], 404);
        }

        $avatars = explode(',', $cast->avatar);

        if ($avatarIndex >= count($avatars)) {
            return response()->json(['error' => 'Avatar index out of range'], 404);
        }

        // Get the avatar path to delete from storage
        $avatarPath = trim($avatars[$avatarIndex]);

        // Delete the file from storage
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($avatarPath)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($avatarPath);
        }

        // Remove the avatar from the array
        unset($avatars[$avatarIndex]);
        $avatars = array_values($avatars); // Re-index array

        // Update the cast with the new avatar string
        $cast->update(['avatar' => implode(',', $avatars)]);

        return response()->json([
            'message' => 'Avatar deleted successfully',
            'remaining_avatars' => $avatars
        ]);
    }

    // Start reservation (cast triggers this)
    public function startReservation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'cast_id' => 'required|exists:casts,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        $reservation = Reservation::find($request->reservation_id);
        // Optionally: check if this cast is allowed to start this reservation
        if ($reservation->started_at) {
            return response()->json(['message' => 'Reservation already started'], 400);
        }
        $reservation->started_at = now();
        $reservation->save();

        // For pishatto calls, start monitoring for exceeded time
        if ($reservation->type === 'Pishatto') {
            $this->startExceededTimeMonitoring($reservation);
        }

        // Send admin message to group chat about session start
        $this->sendSessionStartMessage($reservation, $request->cast_id);

        // Broadcast the reservation update event
        event(new \App\Events\ReservationUpdated($reservation));

        return response()->json(['reservation' => $reservation]);
    }

    /**
     * Send admin message to group chat about session start
     */
    private function sendSessionStartMessage(Reservation $reservation, int $castId)
    {
        try {
            // Find the chat group for this reservation
            $chatGroup = \App\Models\ChatGroup::where('reservation_id', $reservation->id)->first();

            if ($chatGroup) {
                // Get cast information
                $cast = \App\Models\Cast::find($castId);
                $castName = $cast ? $cast->nickname : 'キャスト';

                // Format current time in JST
                $currentTime = \Carbon\Carbon::now()->setTimezone('Asia/Tokyo')->format('H:i');

                // Create admin message
                $adminMessage = "【セッション開始】{$castName}が合流しました。\n開始時間: {$currentTime}\n\nセッションが開始されました。お楽しみください！";

                // Find a chat within the group to send the message
                $targetChat = \App\Models\Chat::where('group_id', $chatGroup->id)->first();

                if ($targetChat) {
                    $message = \App\Models\Message::create([
                        'chat_id' => $targetChat->id,
                        'message' => $adminMessage,
                        'recipient_type' => 'both', // Visible to both guest and cast
                        'created_at' => now(),
                        'is_read' => false,
                    ]);

                    // Broadcast the message for real-time updates
                    event(new \App\Events\GroupMessageSent($message, $chatGroup->id));
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send session start message', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Start monitoring for exceeded time in pishatto calls
     */
    private function startExceededTimeMonitoring(Reservation $reservation)
    {
        // Ensure duration is a number (convert string to float if needed)
        $duration = is_numeric($reservation->duration) ? (float)$reservation->duration : 0;

        // Schedule a job to check for exceeded time after the scheduled duration
        $scheduledEndTime = \Carbon\Carbon::parse($reservation->started_at)
            ->addHours($duration);

        // Dispatch a job to check exceeded time after scheduled duration
        \App\Jobs\CheckExceededTime::dispatch($reservation->id)
            ->delay($scheduledEndTime);
    }

    // Stop reservation (cast triggers this)
    public function stopReservation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'cast_id' => 'required|exists:casts,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        $reservation = Reservation::find($request->reservation_id);
        if (!$reservation->started_at) {
            return response()->json(['message' => 'Reservation not started'], 400);
        }
        if ($reservation->ended_at) {
            return response()->json(['message' => 'Reservation already ended'], 400);
        }

        // Set the cast_id if not already set
        if (!$reservation->cast_id) {
            $reservation->cast_id = $request->cast_id;
        }

        $reservation->ended_at = now();
        $reservation->save();

        // Calculate points and process transaction using the service
        // Note: Exceeded time is now handled within processReservationCompletion
        $pointService = app(\App\Services\PointTransactionService::class);

        $success = $pointService->processReservationCompletion($reservation);

        if (!$success) {
            return response()->json(['message' => 'Failed to process point transaction'], 500);
        }

        // Get the updated reservation with points_earned
        $reservation->refresh();

        // Find the pending transaction to get refund information
        $pendingTransaction = \App\Models\PointTransaction::where('reservation_id', $reservation->id)
            ->where('type', 'pending')
            ->first();

        $refundTransaction = \App\Models\PointTransaction::where('reservation_id', $reservation->id)
            ->where('type', 'convert')
            ->where('description', 'like', '%refunded unused points%')
            ->first();

        $refundAmount = $refundTransaction ? $refundTransaction->amount : 0;
        $reservedAmount = $pendingTransaction ? $pendingTransaction->amount : 0;

        // Create receipt for the session
        $receipt = null;
        try {
            $receiptService = app(\App\Http\Controllers\PaymentController::class);
            $receiptResponse = $receiptService->createReceipt(new \Illuminate\Http\Request([
                'user_type' => 'guest',
                'user_id' => $reservation->guest_id,
                'recipient_name' => $reservation->guest->nickname ?? 'ゲスト',
                'amount' => abs($reservation->points_earned ?? 0),
                'purpose' => 'Pishatto利用料',
                'transaction_created_at' => $reservation->ended_at,
            ]));

            // Extract receipt from the response
            if ($receiptResponse && $receiptResponse->getData() && $receiptResponse->getData()->success) {
                $receipt = $receiptResponse->getData()->receipt;
            }
        } catch (\Exception $e) {
            Log::error('Failed to create receipt for reservation ' . $reservation->id . ': ' . $e->getMessage());
        }

        // Send notification to guest about session completion
        if ($receipt) {
            try {
                $notificationService = app(\App\Services\NotificationService::class);

                // Generate proper shareable receipt URL matching ReceiptConfirmationPage format
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
                if (!preg_match('#^https?://#i', $frontendUrl)) {
                    $frontendUrl = 'http://' . ltrim($frontendUrl, '/');
                }
                $frontendUrl = rtrim($frontendUrl, '/');

                // Generate random suffix like in ReceiptConfirmationPage for security
                $randomSuffix1 = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
                $randomSuffix2 = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
                $receiptUrl = $frontendUrl . '/receipt/' . $receipt->receipt_number . '-' . $randomSuffix1 . '_' . $randomSuffix2;

                $message = "🎉 解散が完了いたしました！\n\n📄 領収書は以下の通りとなります：\n🔗 {$receiptUrl}\n\n⚠️ 内容に相違ございましたら3日以内に運営にご連絡くださいませ。\n\n🙏 またのご利用を心よりお待ちしております。";

                $notification = $notificationService->sendMeetupDissolutionNotification(
                    $reservation->guest_id,
                    $message,
                    $reservation->id
                );

                // Broadcast the notification event for real-time delivery
                if ($notification) {
                    event(new \App\Events\NotificationSent($notification));
                }

                // Also notify involved casts with the required message format using existing notification service
                // Determine cast IDs (supports single cast_id and multiple cast_ids array/json)
                $castIds = [];
                if (!empty($reservation->cast_id)) {
                    $castIds[] = (int)$reservation->cast_id;
                }
                if (!empty($reservation->cast_ids)) {
                    $ids = $reservation->cast_ids;
                    if (is_string($ids)) {
                        // Attempt to decode JSON string to array
                        $decoded = json_decode($ids, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $ids = $decoded;
                        }
                    }
                    if (is_array($ids)) {
                        foreach ($ids as $cid) {
                            $castIds[] = (int)$cid;
                        }
                    }
                }
                $castIds = array_values(array_unique(array_filter($castIds)));

                if (!empty($castIds)) {
                    $castMessage = "解散が完了いたしました。\n今回の売上は以下の通りとなります。\nURL：{$receiptUrl}\n内容に相違ございましたら3日以内に運営にご連絡くださいませ。\n今後ともどうぞよろしくお願いいたします。";
                    foreach ($castIds as $castIdForNotice) {
                        $castNotification = \App\Services\NotificationService::sendNotificationIfEnabled(
                            (int)$castIdForNotice,
                            'cast',
                            'meetup_dissolution',
                            'meetup_dissolution',
                            $castMessage,
                            $reservation->id,
                            null
                        );
                        if ($castNotification) {
                            event(new \App\Events\NotificationSent($castNotification));
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send dissolution notification for reservation ' . $reservation->id . ': ' . $e->getMessage());
            }
        }

        // Send admin message to group chat about session end
        $this->sendSessionEndMessage($reservation, $request->cast_id);

        // Broadcast the reservation update event
        event(new \App\Events\ReservationUpdated($reservation));

        return response()->json([
            'reservation' => $reservation,
            'message' => 'Reservation completed and points transferred successfully',
            'points_transferred' => $reservation->points_earned,
            'points_reserved' => $reservedAmount,
            'points_refunded' => $refundAmount,
            'receipt' => $receipt
        ]);
    }

    /**
     * Send admin message to group chat about session end
     */
    private function sendSessionEndMessage(Reservation $reservation, int $castId)
    {
        try {
            // Find the chat group for this reservation
            $chatGroup = \App\Models\ChatGroup::where('reservation_id', $reservation->id)->first();

            if ($chatGroup) {
                // Get cast information
                $cast = \App\Models\Cast::find($castId);
                $castName = $cast ? $cast->nickname : 'キャスト';

                // Format end time in JST
                $endTime = \Carbon\Carbon::now()->setTimezone('Asia/Tokyo')->format('H:i');

                // Calculate session duration
                $startTime = \Carbon\Carbon::parse($reservation->started_at)->setTimezone('Asia/Tokyo');
                $endTimeCarbon = \Carbon\Carbon::parse($reservation->ended_at)->setTimezone('Asia/Tokyo');
                $duration = $startTime->diffInMinutes($endTimeCarbon);
                $hours = floor($duration / 60);
                $minutes = $duration % 60;
                $durationText = $hours > 0 ? "{$hours}時間{$minutes}分" : "{$minutes}分";

                // Create admin message
                $adminMessage = "【セッション終了】{$castName}がセッションを終了しました。\n終了時間: {$endTime}\nセッション時間: {$durationText}\n\nお疲れ様でした！";

                // Find a chat within the group to send the message
                $targetChat = \App\Models\Chat::where('group_id', $chatGroup->id)->first();

                if ($targetChat) {
                    $message = \App\Models\Message::create([
                        'chat_id' => $targetChat->id,
                        'message' => $adminMessage,
                        'recipient_type' => 'both', // Visible to both guest and cast
                        'created_at' => now(),
                        'is_read' => false,
                    ]);

                    // Broadcast the message for real-time updates
                    event(new \App\Events\GroupMessageSent($message, $chatGroup->id));
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send session end message', [
                'reservation_id' => $reservation->id,
                'cast_id' => $castId,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Add a cast to guest's favorites
    public function favorite(Request $request)
    {
        $guestId = $request->input('guest_id');
        $castId = $request->input('cast_id');
        $guest = \App\Models\Guest::findOrFail($guestId);
        if ($guest->favorites()->where('cast_id', $castId)->exists()) {
            return response()->json(['favorited' => true, 'message' => 'Already favorited']);
        }
        $guest->favorites()->attach($castId);
        return response()->json(['favorited' => true]);
    }

    // Remove a cast from guest's favorites
    public function unfavorite(Request $request)
    {
        $guestId = $request->input('guest_id');
        $castId = $request->input('cast_id');
        $guest = \App\Models\Guest::findOrFail($guestId);
        $guest->favorites()->detach($castId);
        return response()->json(['favorited' => false]);
    }

    // List all favorite casts for a guest
    public function favoriteCasts($guestId)
    {
        $guest = \App\Models\Guest::with('favorites')->findOrFail($guestId);
        return response()->json(['casts' => $guest->favorites]);
    }

    /**
     * Format phone number for Infobip
     * Remove leading 0 and add 81 for Japanese phone numbers
     */
    private function formatPhoneNumberForInfobip($phoneNumber)
    {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Ensure it starts with 0 (Japanese format)
        if (!str_starts_with($phoneNumber, '0')) {
            throw new \InvalidArgumentException('Phone number must start with 0');
        }

        // Remove leading 0 and add 81
        $formattedNumber = '81' . ltrim($phoneNumber, '0');

        return $formattedNumber;
    }
}
