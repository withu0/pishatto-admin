<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // // Log broadcast service initialization
        // Log::info('BroadcastServiceProvider: Initializing broadcast service', [
        //     'broadcast_driver' => config('broadcasting.default'),
        //     'pusher_key' => config('broadcasting.connections.pusher.key'),
        //     'pusher_cluster' => config('broadcasting.connections.pusher.options.cluster'),
        //     'pusher_use_tls' => config('broadcasting.connections.pusher.options.useTLS'),
        //     'pusher_encrypted' => config('broadcasting.connections.pusher.options.encrypted'),
        // ]);

        // Enable broadcasting routes with both guest and cast guards
        Broadcast::routes(['middleware' => ['web', 'auth:guest,cast']]);

        // Log route registration
        // Log::info('BroadcastServiceProvider: Broadcasting routes registered', [
        //     'middleware' => ['web', 'auth:guest,cast'],
        //     'routes_file' => 'routes/channels.php'
        // ]);

        require base_path('routes/channels.php');

        // Log successful initialization
        // Log::info('BroadcastServiceProvider: Broadcast service initialized successfully');
    }
}
