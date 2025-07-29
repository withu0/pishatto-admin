<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Reservation;

class CastAuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }
        $cast = Cast::where('phone', $request->phone)->first();
        if (!$cast) {
            $cast = Cast::create(['phone' => $request->phone]);
        }
        // Log the cast in using Laravel session (cast guard)
        \Illuminate\Support\Facades\Auth::guard('cast')->login($cast);
        return response()->json([
            'cast' => $cast,
            'token' => base64_encode('cast|' . $cast->id . '|' . now()), // placeholder token
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->only([
            'id', 'phone', 'line_id', 'password', 'nickname', 'avatar', 'birth_year', 'height', 'residence',
            'birthplace', 'profile_text', 'created_at', 'updated_at'
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
        $reservations = \App\Models\Reservation::orderBy('scheduled_at', 'desc')->get();
        return response()->json(['reservations' => $reservations]);
    }

    public function getProfile($id)
    {
        $cast = \App\Models\Cast::with(['badges', 'receivedGifts'])->find($id);
        if (!$cast) {
            return response()->json(['message' => 'Cast not found'], 404);
        }
        // Get recommended casts (top 3 by recent, excluding self)
        $recommended = \App\Models\Cast::where('id', '!=', $id)->orderBy('created_at', 'desc')->limit(3)->get();
        // Get badges with counts for this cast
        $badgesWithCounts = $cast->badges()
            ->select('badges.*', \DB::raw('COUNT(*) as count'))
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
        $cast = \App\Models\Cast::find($id);
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

        // Calculate gift points from guest gifts (this is the main source of points for casts)
        $giftPoints = \App\Models\GuestGift::where('receiver_cast_id', $id)
            ->whereBetween('guest_gifts.created_at', [$currentMonth, $nextMonth])
            ->join('gifts', 'guest_gifts.gift_id', '=', 'gifts.id')
            ->sum('gifts.points');

        // For now, we'll use gift points as the main source since reservations don't have cast_id
        // In a real implementation, you might have a separate cast_earnings table
        $monthlyTotalPoints = $giftPoints;

        // Calculate copat-back rate based on gift transactions
        $totalGiftTransactions = \App\Models\GuestGift::where('receiver_cast_id', $id)
            ->whereBetween('guest_gifts.created_at', [$currentMonth, $nextMonth])
            ->count();
        
        $successfulGiftTransactions = \App\Models\GuestGift::where('receiver_cast_id', $id)
            ->whereBetween('guest_gifts.created_at', [$currentMonth, $nextMonth])
            ->join('gifts', 'guest_gifts.gift_id', '=', 'gifts.id')
            ->where('gifts.points', '>', 0)
            ->count();

        $copatBackRate = $totalGiftTransactions > 0 ? round(($successfulGiftTransactions / $totalGiftTransactions) * 100) : 0;

        return response()->json([
            'monthly_total_points' => $monthlyTotalPoints,
            'gift_points' => $giftPoints,
            'copat_back_rate' => $copatBackRate,
            'total_reservations' => $totalGiftTransactions,
            'completed_reservations' => $successfulGiftTransactions
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
        // Optional filter: area (favorite_area or location)
        if ($request->has('area')) {
            $query->where('location', $request->area);
        }
        // Optional sort
        if ($request->has('sort')) {
            if ($request->sort === 'newest') {
                $query->orderBy('created_at', 'desc');
            } elseif ($request->sort === 'oldest') {
                $query->orderBy('created_at', 'asc');
            } elseif ($request->sort === 'most_liked') {
                $query->withCount('likes')->orderBy('likes_count', 'desc');
            } elseif ($request->sort === 'most_active') {
                $query->orderBy('updated_at', 'desc');
            }
        }
        $casts = $query->get();
        return response()->json(['casts' => $casts]);
    }

    // Like or unlike a cast
    public function like(Request $request)
    {
        $guestId = $request->input('guest_id');
        $castId = $request->input('cast_id');
        $like = \App\Models\Like::where('guest_id', $guestId)->where('cast_id', $castId)->first();
        if ($like) {
            $like->delete();
            return response()->json(['liked' => false]);
        } else {
            \App\Models\Like::create(['guest_id' => $guestId, 'cast_id' => $castId]);
            return response()->json(['liked' => true]);
        }
    }

    // Get all liked casts for a guest
    public function likedCasts($guestId)
    {
        $castIds = \App\Models\Like::where('guest_id', $guestId)->pluck('cast_id');
        $casts = \App\Models\Cast::whereIn('id', $castIds)->get();
        return response()->json(['casts' => $casts]);
    }

    // Record when a cast visits a guest profile
    public function recordGuestVisit(Request $request)
    {
        $validator = \Validator::make($request->all(), [
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
        $validator = \Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'cast_id' => 'required|exists:casts,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        $reservation = \App\Models\Reservation::find($request->reservation_id);
        // Optionally: check if this cast is allowed to start this reservation
        if ($reservation->started_at) {
            return response()->json(['message' => 'Reservation already started'], 400);
        }
        $reservation->started_at = now();
        $reservation->save();
        return response()->json(['reservation' => $reservation]);
    }

    // Stop reservation (cast triggers this)
    public function stopReservation(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'cast_id' => 'required|exists:casts,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        $reservation = \App\Models\Reservation::find($request->reservation_id);
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
        
        // Calculate points and process transaction
        $pointService = app(\App\Services\PointTransactionService::class);
        $pointsAmount = $pointService->calculateReservationPoints($reservation);
        
        $success = $pointService->processReservationCompletion($reservation, $pointsAmount);
        
        if (!$success) {
            return response()->json(['message' => 'Failed to process point transaction'], 500);
        }
        
        return response()->json([
            'reservation' => $reservation, 
            'points' => $pointsAmount,
            'message' => 'Reservation completed and points transferred successfully'
        ]);
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
} 