<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class RealtimeLogService
{
    /**
     * Log realtime connection events
     */
    public static function logConnection($type, $data = [])
    {
        Log::channel('realtime')->info("Realtime Connection: {$type}", array_merge([
            'timestamp' => now()->toISOString(),
            'type' => $type
        ], $data));
    }

    /**
     * Log realtime broadcasting events
     */
    public static function logBroadcast($event, $data = [])
    {
        Log::channel('realtime')->info("Realtime Broadcast: {$event}", array_merge([
            'timestamp' => now()->toISOString(),
            'event' => $event
        ], $data));
    }

    /**
     * Log realtime channel authorization
     */
    public static function logChannelAuth($channel, $user, $authorized, $data = [])
    {
        $level = $authorized ? 'info' : 'warning';
        Log::channel('realtime')->{$level}("Realtime Channel Auth: {$channel}", array_merge([
            'timestamp' => now()->toISOString(),
            'channel' => $channel,
            'user_type' => get_class($user),
            'user_id' => $user->id ?? 'unknown',
            'authorized' => $authorized
        ], $data));
    }

    /**
     * Log realtime message events
     */
    public static function logMessage($action, $message, $data = [])
    {
        Log::channel('realtime')->info("Realtime Message: {$action}", array_merge([
            'timestamp' => now()->toISOString(),
            'action' => $action,
            'message_id' => $message->id,
            'chat_id' => $message->chat_id,
            'sender_guest_id' => $message->sender_guest_id,
            'sender_cast_id' => $message->sender_cast_id,
            'recipient_type' => $message->recipient_type,
            'message_preview' => substr($message->message ?? '', 0, 50),
            'has_image' => !is_null($message->image),
            'has_gift' => !is_null($message->gift_id)
        ], $data));
    }

    /**
     * Log realtime chat events
     */
    public static function logChat($action, $chat, $data = [])
    {
        Log::channel('realtime')->info("Realtime Chat: {$action}", array_merge([
            'timestamp' => now()->toISOString(),
            'action' => $action,
            'chat_id' => $chat->id,
            'guest_id' => $chat->guest_id,
            'cast_id' => $chat->cast_id,
            'reservation_id' => $chat->reservation_id,
            'group_id' => $chat->group_id
        ], $data));
    }

    /**
     * Log realtime errors
     */
    public static function logError($error, $context = [])
    {
        Log::channel('realtime')->error("Realtime Error: {$error}", array_merge([
            'timestamp' => now()->toISOString(),
            'error' => $error
        ], $context));
    }

    /**
     * Log realtime configuration
     */
    public static function logConfig($config)
    {
        Log::channel('realtime')->info("Realtime Config", array_merge([
            'timestamp' => now()->toISOString(),
            'broadcast_driver' => config('broadcasting.default'),
            'reverb_host' => config('broadcasting.connections.reverb.options.host'),
            'reverb_port' => config('broadcasting.connections.reverb.options.port'),
            'reverb_scheme' => config('broadcasting.connections.reverb.options.scheme'),
            'reverb_use_tls' => config('broadcasting.connections.reverb.options.useTLS'),
        ], $config));
    }
}
