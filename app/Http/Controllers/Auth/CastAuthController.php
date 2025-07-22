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
        return response()->json([
            'cast' => $cast,
            'token' => base64_encode('cast|' . $cast->id . '|' . now()), // placeholder token
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->only([
            'phone', 'line_id', 'password', 'nickname', 'avatar', 'birth_year', 'height', 'residence',
            'birthplace', 'profile_text', 'created_at', 'updated_at'
        ]);
        if (empty($data['phone'])) {
            return response()->json(['message' => 'Phone is required'], 422);
        }
        $cast = \App\Models\Cast::updateOrCreate(
            ['phone' => $data['phone']],
            $data
        );
        return response()->json(['cast' => $cast]);
    }


    public function allReservations()
    {
        $reservations = \App\Models\Reservation::orderBy('scheduled_at', 'desc')->get();
        return response()->json(['reservations' => $reservations]);
    }

    public function getProfile($id)
    {
        $cast = \App\Models\Cast::with(['reservations', 'badges', 'titles'])->find($id);
        if (!$cast) {
            return response()->json(['message' => 'Cast not found'], 404);
        }
        // Get recommended casts (top 3 by recent, excluding self)
        $recommended = \App\Models\Cast::where('id', '!=', $id)->orderBy('created_at', 'desc')->limit(3)->get();
        return response()->json([
            'cast' => $cast,
            'reservations' => $cast->reservations,
            'badges' => $cast->badges ?? [],
            'titles' => $cast->titles ?? [],
            'recommended' => $recommended,
        ]);
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

    // Record a visit to a cast profile
    public function recordVisit(Request $request)
    {
        $guestId = $request->input('guest_id');
        $castId = $request->input('cast_id');
        \App\Models\VisitHistory::create(['guest_id' => $guestId, 'cast_id' => $castId]);
        return response()->json(['success' => true]);
    }

    // Get visit history for a guest
    public function visitHistory($guestId)
    {
        $history = \App\Models\VisitHistory::where('guest_id', $guestId)->orderBy('created_at', 'desc')->with('cast')->get();
        return response()->json(['history' => $history]);
    }
} 