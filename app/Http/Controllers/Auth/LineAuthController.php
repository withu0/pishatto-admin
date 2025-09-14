<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Cast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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
        $disableAutoLogin = $request->boolean('disable_auto_login', false);
        
        // Validate that LINE config is set
        $clientId = config('services.line.client_id');
        $clientSecret = config('services.line.client_secret');
        $redirectUri = config('services.line.redirect');
        if (!$clientId || !$clientSecret || !$redirectUri) {
            return response()->json([
                'success' => false,
                'message' => 'LINE Login is not configured. Please set LINE_CHANNEL_ID, LINE_CHANNEL_SECRET, and LINE_REDIRECT_URI.'
            ], 500);
        }
        
        // Store user_type in session for later use
        session(['line_user_type' => $userType]);
        
        // Check if this is for cast registration (from frontend sessionStorage)
        $isCastRegistration = $request->has('cast_registration') && $request->boolean('cast_registration');
        if ($isCastRegistration) {
            session(['cast_registration' => true]);
        }
        
        // Redirect the user to LINE's authorization page.
        // Do not pass the callback URL here; Socialite reads it from config('services.line.redirect').
        // Note: Passing additional parameters like disable_auto_login is optional; Socialite supports with(),
        // but to avoid static analysis issues in this codebase, we simply ignore it server-side.
        return Socialite::driver('line-custom')->redirect();
    }

    /**
     * Handle Line OAuth callback
     */
    public function handleLineCallback(Request $request)
    {
        $userType = session('line_user_type', 'guest');
        
        try {
            // Validate session before proceeding
            if (!$request->session()->isStarted()) {
                throw new \Exception('Session not started');
            }

            $lineUser = Socialite::driver('line-custom')->user();
            
            // Validate LINE user data
            $lineId = $lineUser->getId();
            if (empty($lineId)) {
                throw new \Exception('Invalid LINE user ID received');
            }
            
            $lineEmail = $lineUser->getEmail();
            $lineName = $lineUser->getName();
            $lineAvatar = $lineUser->getAvatar();
            
            // Validate LINE ID format (basic validation)
            if (!is_string($lineId) || strlen($lineId) > 50) {
                throw new \Exception('Invalid LINE ID format');
            }
            
            // Sanitize LINE data
            $lineId = trim($lineId);
            $lineEmail = $lineEmail ? trim($lineEmail) : null;
            $lineName = $lineName ? trim($lineName) : null;
            $lineAvatar = $lineAvatar ? trim($lineAvatar) : null;
            
            Log::info('LineAuthController: LINE authentication started', [
                'line_id' => substr($lineId, 0, 8) . '...', // Partial ID for logging
                'user_type' => $userType,
                'has_email' => !empty($lineEmail),
                'has_name' => !empty($lineName),
                'has_avatar' => !empty($lineAvatar)
            ]);

            // Use database transaction for consistency
            return DB::transaction(function () use ($request, $lineId, $lineEmail, $lineName, $lineAvatar, $userType) {
                // Store Line authentication data in session for persistent auth
                session([
                    'line_user_id' => $lineId,
                    'line_user_type' => $userType,
                    'line_user_email' => $lineEmail,
                    'line_user_name' => $lineName,
                    'line_user_avatar' => $lineAvatar
                ]);

                // Only query the relevant table based on user type to optimize performance
                if ($userType === 'cast') {
                    return $this->handleCastAuthentication($request, $lineId, $lineEmail, $lineName, $lineAvatar);
                } else {
                    return $this->handleGuestAuthentication($request, $lineId, $lineEmail, $lineName, $lineAvatar);
                }
            });
            
        } catch (\Exception $e) {
            // Clear the stored Line authentication data on error
            session()->forget([
                'line_user_id',
                'line_user_type',
                'line_user_email',
                'line_user_name',
                'line_user_avatar'
            ]);
            
            Log::error('LineAuthController: Authentication failed', [
                'error' => $e->getMessage(),
                'user_type' => $userType,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Handle errors consistently for both user types
            return $this->handleAuthenticationError($request, $userType, $e->getMessage());
        }
    }

    /**
     * Handle cast authentication
     */
    private function handleCastAuthentication(Request $request, string $lineId, ?string $lineEmail, ?string $lineName, ?string $lineAvatar)
    {
        $cast = Cast::where('line_id', $lineId)->first();

        if ($cast) {
            // Cast exists, log them in
            Auth::guard('cast')->login($cast);

            Log::info('LineAuthController: Cast logged in successfully', [
                'cast_id' => $cast->id,
                'line_id' => substr($lineId, 0, 8) . '...'
            ]);

            $responseData = [
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
            ];

            if (!($request->expectsJson() || $request->wantsJson())) {
                $frontendUrl = $this->getFrontendUrl();
                return redirect()->away($frontendUrl . '/cast/dashboard');
            }

            return response()->json($responseData);
        }

        // Cast not found for this LINE ID
        Log::info('LineAuthController: Cast account not found for LINE ID', [
            'line_id' => substr($lineId, 0, 8) . '...'
        ]);

        $errorMessage = 'このLINEアカウントに紐づくキャストアカウントが見つかりません。電話番号でログインしてください。';
        
        if (!($request->expectsJson() || $request->wantsJson())) {
            $frontendUrl = $this->getFrontendUrl();
            return redirect()->away($frontendUrl . '/cast/login?error=' . urlencode($errorMessage));
        }

        return response()->json([
            'success' => false,
            'message' => 'Cast account not found for this LINE ID. Please log in using phone.'
        ], 404);
    }

    /**
     * Handle guest authentication
     */
    private function handleGuestAuthentication(Request $request, string $lineId, ?string $lineEmail, ?string $lineName, ?string $lineAvatar)
    {
        $guest = Guest::where('line_id', $lineId)->first();

        if ($guest) {
            // Guest exists, log them in
            Auth::guard('guest')->login($guest);

            Log::info('LineAuthController: Guest logged in successfully', [
                'guest_id' => $guest->id,
                'line_id' => substr($lineId, 0, 8) . '...'
            ]);

            $responseData = [
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
            ];

            if (!($request->expectsJson() || $request->wantsJson())) {
                $frontendUrl = $this->getFrontendUrl();
                return redirect()->away($frontendUrl . '/dashboard');
            }

            return response()->json($responseData);
        }

        // Check if this is for cast registration
        $isCastRegistration = session('cast_registration', false);
        
        if ($isCastRegistration) {
            // For cast registration, return LINE data without requiring existing account
            Log::info('LineAuthController: LINE auth for cast registration', [
                'line_id' => substr($lineId, 0, 8) . '...'
            ]);

            $responseData = [
                'success' => true,
                'user_type' => 'cast_registration',
                'line_data' => [
                    'line_id' => $lineId,
                    'line_email' => $lineEmail,
                    'line_name' => $lineName,
                    'line_avatar' => $lineAvatar
                ],
                'message' => 'LINE authentication successful for cast registration'
            ];

            if (!($request->expectsJson() || $request->wantsJson())) {
                $frontendUrl = $this->getFrontendUrl();
                return redirect()->away($frontendUrl . '/cast/register');
            }

            return response()->json($responseData);
        }

        // No existing guest linked to this LINE account, redirect to registration
        Log::info('LineAuthController: New guest with LINE, redirecting to registration', [
            'line_id' => substr($lineId, 0, 8) . '...'
        ]);

        $responseData = [
            'success' => true,
            'user_type' => 'new',
            'line_data' => [
                'line_id' => $lineId,
                'line_email' => $lineEmail,
                'line_name' => $lineName,
                'line_avatar' => $lineAvatar
            ],
            'message' => 'New guest with LINE. Continue registration.'
        ];

        if (!($request->expectsJson() || $request->wantsJson())) {
            $frontendUrl = $this->getFrontendUrl();
            $query = http_build_query([
                'line_id' => $lineId,
                'line_email' => $lineEmail,
                'line_name' => $lineName,
                'line_avatar' => $lineAvatar,
                'user_type' => 'guest',
            ]);
            return redirect()->away($frontendUrl . '/line-register?' . $query);
        }

        return response()->json($responseData);
    }

    /**
     * Handle authentication errors consistently
     */
    private function handleAuthenticationError(Request $request, string $userType, string $errorMessage)
    {
        $frontendUrl = $this->getFrontendUrl();
        
        if ($userType === 'cast') {
            $errorUrl = $frontendUrl . '/cast/login?error=' . urlencode('LINE認証に失敗しました。電話番号でログインしてください。');
        } else {
            $errorUrl = $frontendUrl . '/login?error=' . urlencode('LINE認証に失敗しました。もう一度お試しください。');
        }

        if (!($request->expectsJson() || $request->wantsJson())) {
            return redirect()->away($errorUrl);
        }

        return response()->json([
            'success' => false,
            'message' => 'Line authentication failed: ' . $errorMessage
        ], 500);
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
            // Individual field validation instead of additional_data array
            'phone' => 'nullable|string',
            'verification_code' => 'nullable|string',
            'nickname' => 'nullable|string',
            'favorite_area' => 'nullable|string',
            'location' => 'nullable|string',
            'age' => 'nullable|string',
            'shiatsu' => 'nullable|string',
            'interests' => 'nullable|array',
            'profile_photo' => 'nullable|file|image|max:2048'
        ]);

        try {
            $lineId = $request->line_id;
            $lineEmail = $request->line_email;
            $lineName = $request->line_name;
            $lineAvatar = $request->line_avatar;
            $userType = $request->user_type;

            // Find any existing accounts linked to this LINE ID
            $existingGuest = Guest::where('line_id', $lineId)->first();
            $existingCast = Cast::where('line_id', $lineId)->first();

            if ($userType === 'guest') {
                // Prepare guest data mapping to database fields
                $guestData = [
                    'line_id' => $lineId,
                    'nickname' => $request->nickname ?: $lineName ?: 'Guest',
                    'avatar' => $lineAvatar,
                    'status' => 'active',
                    'phone' => $request->phone,
                    'favorite_area' => $request->favorite_area,
                    'location' => $request->location,
                    'age' => $request->age,
                    'shiatsu' => $request->shiatsu,
                ];

                // Handle interests array
                if ($request->has('interests') && is_array($request->interests)) {
                    $guestData['interests'] = $request->interests;
                }

                // Handle profile photo upload
                if ($request->hasFile('profile_photo')) {
                    $profilePhoto = $request->file('profile_photo');
                    $filename = time() . '_' . $profilePhoto->getClientOriginalName();
                    $path = $profilePhoto->storeAs('avatars', $filename, 'public');
                    $guestData['avatar'] = $path;
                }

                if ($existingGuest) {
                    // Update existing guest with new data
                    $existingGuest->update($guestData);
                    $guest = $existingGuest;
                } else {
                    // Create new guest
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