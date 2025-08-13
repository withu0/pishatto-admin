<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Cast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class LineAuthController extends Controller
{
    /**
     * Redirect to Line OAuth
     */
    public function redirectToLine(Request $request)
    {
        $userType = $request->get('user_type', 'guest');
        
        // Store user_type in session for later use
        session(['line_user_type' => $userType]);
        
        // Redirect the user to LINE's authorization page.
        // Do not pass the callback URL here; Socialite reads it from config('services.line.redirect').
        return Socialite::driver('line')->redirect();
    }

    /**
     * Handle Line OAuth callback
     */
    public function handleLineCallback(Request $request)
    {
        try {
            $lineUser = Socialite::driver('line')->user();
            $userType = session('line_user_type', 'guest');
            
            // Extract Line user information
            $lineId = $lineUser->getId();
            $lineEmail = $lineUser->getEmail();
            $lineName = $lineUser->getName();
            $lineAvatar = $lineUser->getAvatar();
            
            // Check if user exists by line_id
            $guest = Guest::where('line_id', $lineId)->first();
            $cast = Cast::where('line_id', $lineId)->first();
            
            if ($guest) {
                // Guest exists, log them in (guest guard)
                Auth::guard('guest')->login($guest);
                
                // Clear the stored user_type
                session()->forget('line_user_type');
                
                return response()->json([
                    'success' => true,
                    'user_type' => 'guest',
                    'user' => $guest,
                    'message' => 'Guest logged in successfully'
                ]);
            } elseif ($cast) {
                // Cast exists, log them in
                Auth::guard('cast')->login($cast);
                
                // Clear the stored user_type
                session()->forget('line_user_type');
                
                return response()->json([
                    'success' => true,
                    'user_type' => 'cast',
                    'user' => $cast,
                    'message' => 'Cast logged in successfully'
                ]);
            } else {
                // No existing user linked to this LINE account
                if ($userType === 'guest') {
                    // Store LINE ID to database for a new guest and proceed to registration steps
                    $placeholderGuest = Guest::create([
                        'line_id' => $lineId,
                        'nickname' => $lineName ?: 'Guest',
                        'avatar' => $lineAvatar,
                        'status' => 'active'
                    ]);

                    // Clear the stored user_type
                    session()->forget('line_user_type');

                    return response()->json([
                        'success' => true,
                        'user_type' => 'new',
                        'line_data' => [
                            'line_id' => $lineId,
                            'line_email' => $lineEmail,
                            'line_name' => $lineName,
                            'line_avatar' => $lineAvatar
                        ],
                        'message' => 'New guest created with LINE. Continue registration.'
                    ]);
                } else {
                    // Cast via LINE: only login is allowed, do not create new
                    // Clear the stored user_type
                    session()->forget('line_user_type');

                    return response()->json([
                        'success' => false,
                        'message' => 'Cast account not found for this LINE ID. Please log in using phone.'
                    ], 404);
                }
            }
            
        } catch (\Exception $e) {
            // Clear the stored user_type on error
            session()->forget('line_user_type');
            
            return response()->json([
                'success' => false,
                'message' => 'Line authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register new user with Line data
     */
    public function registerWithLine(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:guest,cast',
            'line_id' => 'required|string',
            'line_email' => 'nullable|email',
            'line_name' => 'nullable|string',
            'line_avatar' => 'nullable|string',
            'additional_data' => 'required|array'
        ]);

        try {
            $lineId = $request->line_id;
            $lineEmail = $request->line_email;
            $lineName = $request->line_name;
            $lineAvatar = $request->line_avatar;
            $additionalData = $request->additional_data;
            $userType = $request->user_type;

            // Find any existing accounts linked to this LINE ID
            $existingGuest = Guest::where('line_id', $lineId)->first();
            $existingCast = Cast::where('line_id', $lineId)->first();

            if ($userType === 'guest') {
                // Update existing placeholder guest or create if not present
                if ($existingCast) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This LINE ID is already linked to a cast account'
                    ], 422);
                }

                $guest = $existingGuest ?: Guest::create([
                    'line_id' => $lineId,
                    'nickname' => $lineName ?: $additionalData['nickname'] ?? 'Guest',
                    'avatar' => $lineAvatar,
                    'status' => 'active'
                ]);

                // Update additional fields
                $guest->nickname = $lineName ?: ($additionalData['nickname'] ?? $guest->nickname);
                $guest->phone = $additionalData['phone'] ?? $guest->phone;
                $guest->location = $additionalData['location'] ?? $guest->location;
                $guest->birth_year = $additionalData['birth_year'] ?? $guest->birth_year;
                $guest->height = $additionalData['height'] ?? $guest->height;
                $guest->residence = $additionalData['residence'] ?? $guest->residence;
                $guest->birthplace = $additionalData['birthplace'] ?? $guest->birthplace;
                $guest->save();

                Auth::guard('guest')->login($guest);
                
                return response()->json([
                    'success' => true,
                    'user_type' => 'guest',
                    'user' => $guest,
                    'message' => 'Guest registered and logged in successfully'
                ]);

            } elseif ($userType === 'cast') {
                // Cast registration via LINE is not allowed – only login supported
                if ($existingCast) {
                    Auth::guard('cast')->login($existingCast);
                    return response()->json([
                        'success' => true,
                        'user_type' => 'cast',
                        'user' => $existingCast,
                        'message' => 'Cast logged in successfully'
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Cast registration via LINE is not allowed. Please log in using phone.'
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Link existing account with Line
     */
    public function linkAccount(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:guest,cast',
            'user_id' => 'required|integer',
            'line_id' => 'required|string'
        ]);

        try {
            $userType = $request->user_type;
            $userId = $request->user_id;
            $lineId = $request->line_id;

            if ($userType === 'guest') {
                $user = Guest::find($userId);
                $guard = 'web';
            } else {
                $user = Cast::find($userId);
                $guard = 'cast';
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Check if line_id is already taken
            $existingUser = null;
            if ($userType === 'guest') {
                $existingUser = Guest::where('line_id', $lineId)->first();
            } else {
                $existingUser = Cast::where('line_id', $lineId)->first();
            }

            if ($existingUser && $existingUser->id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Line account is already linked to another user'
                ], 422);
            }

            // Link the account
            $user->line_id = $lineId;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Account linked successfully',
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Linking failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
