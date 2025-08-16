<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
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
            
            if (!$token || !$this->tokensMatch($token)) {
                // Try to refresh the token
                $this->regenerateToken();
                
                // If it's still a mismatch, return error
                if (!$this->tokensMatch($token)) {
                    return response()->json([
                        'message' => 'CSRF token mismatch.',
                        'error' => 'csrf_mismatch'
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
        return hash_equals($sessionToken, $token);
    }

    /**
     * Regenerate the CSRF token
     */
    protected function regenerateToken(): void
    {
        Session::regenerateToken();
    }
}
