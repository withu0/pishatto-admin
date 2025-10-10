<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\RealtimeLogService;

class LogRealtimeRequests
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
        // Log incoming realtime requests
        if ($this->isRealtimeRequest($request)) {
            RealtimeLogService::logConnection('Request Received', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'headers' => $this->getRelevantHeaders($request),
                'user_id' => auth()->id(),
                'user_type' => auth()->user() ? get_class(auth()->user()) : 'guest'
            ]);
        }

        $response = $next($request);

        // Log outgoing realtime responses
        if ($this->isRealtimeRequest($request)) {
            RealtimeLogService::logConnection('Response Sent', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status_code' => $response->getStatusCode(),
                'response_size' => strlen($response->getContent()),
                'user_id' => auth()->id()
            ]);
        }

        return $response;
    }

    /**
     * Check if this is a realtime-related request
     */
    private function isRealtimeRequest(Request $request): bool
    {
        $realtimePaths = [
            '/broadcasting/auth',
            '/broadcasting/socket',
            '/api/chats',
            '/api/messages',
            '/api/chats/create',
            '/api/chats/group-message'
        ];

        foreach ($realtimePaths as $path) {
            if (str_contains($request->path(), $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get relevant headers for logging
     */
    private function getRelevantHeaders(Request $request): array
    {
        $relevantHeaders = [
            'Authorization',
            'X-Requested-With',
            'Content-Type',
            'Accept',
            'Origin',
            'Referer'
        ];

        $headers = [];
        foreach ($relevantHeaders as $header) {
            if ($request->hasHeader($header)) {
                $headers[$header] = $request->header($header);
            }
        }

        return $headers;
    }
}
