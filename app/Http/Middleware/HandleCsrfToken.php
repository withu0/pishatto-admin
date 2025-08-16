<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HandleCsrfToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip CSRF validation for excluded routes
        if ($this->shouldSkipCsrfValidation($request)) {
            return $next($request);
        }

        // For AJAX requests, ensure CSRF token is present
        if ($request->ajax() || $request->wantsJson()) {
            $token = $request->header('X-CSRF-TOKEN') ?: $request->input('_token');
            
            // Log CSRF token information for debugging
            Log::info('CSRF Token Check', [
                'url' => $request->url(),
                'method' => $request->method(),
                'has_token' => !empty($token),
                'token_length' => $token ? strlen($token) : 0,
                'session_id' => Session::getId(),
                'session_has_token' => Session::has('_token')
            ]);
            
            if (!$token) {
                Log::warning('CSRF token missing', [
                    'url' => $request->url(),
                    'headers' => $request->headers->all()
                ]);
                
                return response()->json([
                    'message' => 'CSRF token missing.',
                    'error' => 'csrf_missing',
                    'debug' => [
                        'session_id' => Session::getId(),
                        'session_has_token' => Session::has('_token')
                    ]
                ], 419);
            }
            
            if (!$this->tokensMatch($token)) {
                Log::warning('CSRF token mismatch', [
                    'url' => $request->url(),
                    'provided_token_length' => strlen($token),
                    'session_token_length' => Session::has('_token') ? strlen(Session::token()) : 0
                ]);
                
                // Try to refresh the token
                $this->regenerateToken();
                
                // If it's still a mismatch, return error
                if (!$this->tokensMatch($token)) {
                    return response()->json([
                        'message' => 'CSRF token mismatch.',
                        'error' => 'csrf_mismatch',
                        'debug' => [
                            'session_id' => Session::getId(),
                            'session_has_token' => Session::has('_token'),
                            'token_refreshed' => true
                        ]
                    ], 419);
                }
            }
        }

        return $next($request);
    }

    /**
     * Check if CSRF validation should be skipped for this request
     */
    protected function shouldSkipCsrfValidation(Request $request): bool
    {
        $excludedPaths = [
            'admin/casts/upload-avatar',
            'line/*',
            'sanctum/csrf-cookie',
            'csrf-token',
            'api/*', // Exclude API routes from CSRF protection
        ];

        foreach ($excludedPaths as $path) {
            if (str_contains($path, '*')) {
                $pattern = str_replace('*', '.*', $path);
                if (preg_match("#^{$pattern}#", $request->path())) {
                    return true;
                }
            } elseif ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the provided token matches the session token
     */
    protected function tokensMatch(?string $token): bool
    {
        if (!$token) {
            return false;
        }

        $sessionToken = Session::token();
        
        // Ensure session token exists
        if (!$sessionToken) {
            Log::error('Session token is null', [
                'session_id' => Session::getId(),
                'session_status' => Session::isStarted()
            ]);
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }

    /**
     * Regenerate the CSRF token
     */
    protected function regenerateToken(): void
    {
        Session::regenerateToken();
        Log::info('CSRF token regenerated', [
            'session_id' => Session::getId()
        ]);
    }
}
