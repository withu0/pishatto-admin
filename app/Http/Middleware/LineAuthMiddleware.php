<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Guest;
use App\Models\Cast;

class LineAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if this is a Line authentication callback
        if ($request->has('code') && $request->is('api/line/callback')) {
            return $next($request);
        }

        // Check if user is already authenticated via Line
        $lineId = session('line_user_id');
        $userType = session('line_user_type');
        
        if ($lineId && $userType) {
            // User has Line authentication, check if they exist in database
            if ($userType === 'guest') {
                $guest = Guest::where('line_id', $lineId)->first();
                if ($guest && !Auth::guard('guest')->check()) {
                    Auth::guard('guest')->login($guest);
                }
            } elseif ($userType === 'cast') {
                $cast = Cast::where('line_id', $lineId)->first();
                if ($cast && !Auth::guard('cast')->check()) {
                    Auth::guard('cast')->login($cast);
                }
            }
        }

        return $next($request);
    }
}
