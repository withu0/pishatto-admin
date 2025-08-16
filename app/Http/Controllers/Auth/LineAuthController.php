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
     * Get normalized frontend base URL.
     * Ensures scheme is present to avoid relative redirects like "/host:port/...".
     */
    private function getFrontendUrl(): string
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        if (!preg_match('#^https?://#i', $frontendUrl)) {
            $frontendUrl = 'http://' . ltrim($frontendUrl, '/');
        }
        return rtrim($frontendUrl, '/');
    }

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
        return Socialite::driver('line-custom')->redirect();
    }

    /**
     * Handle Line OAuth callback
     */
    public function handleLineCallback(Request $request)
    {
        try {
            $lineUser = Socialite::driver('line-custom')->user();
            $userType = session('line_user_type', 'guest');
            
            // Extract Line user information
            $lineId = $lineUser->getId();
            $lineEmail = $lineUser->getEmail();
            $lineName = $lineUser->getName();
            $lineAvatar = $lineUser->getAvatar();
            
            // Store Line authentication data in session for persistent auth
            session([
                'line_user_id' => $lineId,
                'line_user_type' => $userType,
                'line_user_email' => $lineEmail,
                'line_user_name' => $lineName,
                'line_user_avatar' => $lineAvatar
            ]);
            
            // Check if user exists by line_id
            $guest = Guest::where('line_id', $lineId)->first();
            $cast = Cast::where('line_id', $lineId)->first();

            // Prefer user type based on the session intent
            if ($userType === 'cast') {
                if ($cast) {
                    // Cast exists, log them in
                    Auth::guard('cast')->login($cast);

                    $frontendUrl = $this->getFrontendUrl();
                    if (!($request->expectsJson() || $request->wantsJson())) {
                        return redirect()->away($frontendUrl . '/cast/dashboard');
                    }

                    return response()->json([
                        'success' => true,
                        'user_type' => 'cast',
                        'user' => $cast,
                        'line_data' => [
                            'line_id' => $lineId,
                            'line_email' => $lineEmail,
                            'line_name' => $lineName,
                            'line_avatar' => $lineAvatar
                        ],
                        'message' => 'Cast logged in successfully'
                    ]);
                }

                // Cast not found for this LINE ID
                return response()->json([
                    'success' => false,
                    'message' => 'Cast account not found for this LINE ID. Please log in using phone.'
                ], 404);

            } else { // guest intent
                if ($guest) {
                    // Guest exists, log them in
                    Auth::guard('guest')->login($guest);

                    $frontendUrl = $this->getFrontendUrl();
                    if (!($request->expectsJson() || $request->wantsJson())) {
                        return redirect()->away($frontendUrl . '/dashboard');
                    }

                    return response()->json([
                        'success' => true,
                        'user_type' => 'guest',
                        'user' => $guest,
                        'line_data' => [
                            'line_id' => $lineId,
                            'line_email' => $lineEmail,
                            'line_name' => $lineName,
                            'line_avatar' => $lineAvatar
                        ],
                        'message' => 'Guest logged in successfully'
                    ]);
                }

                // No existing guest linked to this LINE account, create placeholder and proceed to registration
                $placeholderGuest = Guest::create([
                    'line_id' => $lineId,
                    'nickname' => $lineName ?: 'Guest',
                    'avatar' => $lineAvatar,
                    'status' => 'active'
                ]);

                $frontendUrl = $this->getFrontendUrl();
                if (!($request->expectsJson() || $request->wantsJson())) {
                    $query = http_build_query([
                        'line_id' => $lineId,
                        'line_email' => $lineEmail,
                        'line_name' => $lineName,
                        'line_avatar' => $lineAvatar,
                        'user_type' => 'guest',
                    ]);
                    return redirect()->away($frontendUrl . '/line-register-steps?' . $query);
                }

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
            }
            
        } catch (\Exception $e) {
            // Clear the stored Line authentication data on error
            session()->forget([
                'line_user_id',
                'line_user_type',
                'line_user_email',
                'line_user_name',
                'line_user_avatar'
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Line authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check Line authentication status
     */
    public function checkLineAuth(Request $request)
    {
        $lineId = session('line_user_id');
        $userType = session('line_user_type');
        
        if (!$lineId || !$userType) {
            return response()->json([
                'success' => false,
                'authenticated' => false,
                'message' => 'No Line authentication found'
            ]);
        }
        
        if ($userType === 'guest') {
            $guest = Guest::where('line_id', $lineId)->first();
            if ($guest) {
                return response()->json([
                    'success' => true,
                    'authenticated' => true,
                    'user_type' => 'guest',
                    'user' => $guest,
                    'line_data' => [
                        'line_id' => $lineId,
                        'line_email' => session('line_user_email'),
                        'line_name' => session('line_user_name'),
                        'line_avatar' => session('line_user_avatar')
                    ]
                ]);
            }
        } elseif ($userType === 'cast') {
            $cast = Cast::where('line_id', $lineId)->first();
            if ($cast) {
                return response()->json([
                    'success' => true,
                    'authenticated' => true,
                    'user_type' => 'cast',
                    'user' => $cast,
                    'line_data' => [
                        'line_id' => $lineId,
                        'line_email' => session('line_user_email'),
                        'line_name' => session('line_user_name'),
                        'line_avatar' => session('line_user_avatar')
                    ]
                ]);
            }
        }
        
        return response()->json([
            'success' => false,
            'authenticated' => false,
            'message' => 'Line user not found in database'
        ]);
    }

    /**
     * Check Line authentication status for guest only
     */
    public function checkLineAuthGuest(Request $request)
    {
        $lineId = session('line_user_id');
        $userType = session('line_user_type');
        if (!$lineId) {
            return response()->json([
                'success' => false,
                'authenticated' => false,
                'message' => 'No Line authentication found'
            ]);
        }

        if ($userType !== 'guest') {
            return response()->json([
                'success' => false,
                'authenticated' => false,
                'message' => 'Line session is not for guest'
            ]);
        }

        $guest = Guest::where('line_id', $lineId)->first();
        if ($guest) {
            if (!Auth::guard('guest')->check()) {
                Auth::guard('guest')->login($guest);
            }
            return response()->json([
                'success' => true,
                'authenticated' => true,
                'user_type' => 'guest',
                'user' => $guest,
                'line_data' => [
                    'line_id' => $lineId,
                    'line_email' => session('line_user_email'),
                    'line_name' => session('line_user_name'),
                    'line_avatar' => session('line_user_avatar')
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'authenticated' => false,
            'message' => 'Guest not found for this LINE ID'
        ]);
    }

    /**
     * Check Line authentication status for cast only
     */
    public function checkLineAuthCast(Request $request)
    {
        $lineId = session('line_user_id');
        $userType = session('line_user_type');
        if (!$lineId) {
            return response()->json([
                'success' => false,
                'authenticated' => false,
                'message' => 'No Line authentication found'
            ]);
        }

        if ($userType !== 'cast') {
            return response()->json([
                'success' => false,
                'authenticated' => false,
                'message' => 'Line session is not for cast'
            ]);
        }

        $cast = Cast::where('line_id', $lineId)->first();
        if ($cast) {
            if (!Auth::guard('cast')->check()) {
                Auth::guard('cast')->login($cast);
            }
            return response()->json([
                'success' => true,
                'authenticated' => true,
                'user_type' => 'cast',
                'user' => $cast,
                'line_data' => [
                    'line_id' => $lineId,
                    'line_email' => session('line_user_email'),
                    'line_name' => session('line_user_name'),
                    'line_avatar' => session('line_user_avatar')
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'authenticated' => false,
            'message' => 'Cast not found for this LINE ID'
        ]);
    }

    /**
     * Logout from Line authentication
     */
    public function logout(Request $request)
    {
        // Clear Line authentication session data
        session()->forget([
            'line_user_id',
            'line_user_type',
            'line_user_email',
            'line_user_name',
            'line_user_avatar'
        ]);
        
        // Logout from the appropriate guard
        if (Auth::guard('guest')->check()) {
            Auth::guard('guest')->logout();
        }
        if (Auth::guard('cast')->check()) {
            Auth::guard('cast')->logout();
        }
        
        // Invalidate and regenerate session
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
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
            'additional_data' => 'required|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:20480'
        ]);

        try {
            $lineId = $request->line_id;
            $lineEmail = $request->line_email;
            $lineName = $request->line_name;
            $lineAvatar = $request->line_avatar;
            $additionalData = json_decode($request->additional_data, true);
            $userType = $request->user_type;
            
            if (!$additionalData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid additional_data format'
                ], 422);
            }

            // Find any existing accounts linked to this LINE ID
            $existingGuest = Guest::where('line_id', $lineId)->first();
            $existingCast = Cast::where('line_id', $lineId)->first();

            if ($userType === 'guest') {
                // Handle profile photo upload if present
                $avatarPath = $lineAvatar; // Default to LINE avatar
                
                // Handle file upload if profile_photo is sent as a file
                if ($request->hasFile('profile_photo')) {
                    $file = $request->file('profile_photo');
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs('avatars', $fileName, 'public');
                    $avatarPath = 'avatars/' . $fileName;
                }

                if ($existingGuest) {
                    // Update existing guest with additional data
                    $updateData = $additionalData;
                    if ($avatarPath && $avatarPath !== $lineAvatar) {
                        $updateData['avatar'] = $avatarPath;
                    }
                    $existingGuest->update($updateData);
                    $guest = $existingGuest;
                } else {
                    // Create new guest
                    $guestData = array_merge([
                        'line_id' => $lineId,
                        'nickname' => $lineName ?: 'Guest',
                        'avatar' => $avatarPath,
                        'status' => 'active'
                    ], $additionalData);
                    
                    $guest = Guest::create($guestData);
                }

                // Log the guest in
                Auth::guard('guest')->login($guest);

                return response()->json([
                    'success' => true,
                    'user_type' => 'guest',
                    'user' => $guest,
                    'message' => 'Guest registered and logged in successfully'
                ]);
            } else {
                // Cast registration via LINE is not allowed
                return response()->json([
                    'success' => false,
                    'message' => 'Cast registration via LINE is not allowed. Please use phone number registration.'
                ], 422);
            }

        } catch (\Exception $e) {
            \Log::error('LINE registration error: ' . $e->getMessage());
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
                $guard = 'guest';
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

            // Update session data
            session([
                'line_user_id' => $lineId,
                'line_user_type' => $userType
            ]);

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
