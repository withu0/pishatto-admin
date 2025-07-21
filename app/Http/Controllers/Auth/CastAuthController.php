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
        $cast = \App\Models\Cast::with('reservations')->find($id);
        if (!$cast) {
            return response()->json(['message' => 'Cast not found'], 404);
        }
        return response()->json(['cast' => $cast, 'reservations' => $cast->reservations]);
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
} 