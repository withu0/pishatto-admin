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
        // Log broadcast service initialization
        Log::info('BroadcastServiceProvider: Initializing broadcast service', [
            'broadcast_driver' => config('broadcasting.default'),
            'reverb_host' => config('broadcasting.connections.reverb.options.host'),
            'reverb_port' => config('broadcasting.connections.reverb.options.port'),
            'reverb_scheme' => config('broadcasting.connections.reverb.options.scheme'),
            'reverb_use_tls' => config('broadcasting.connections.reverb.options.useTLS'),
        ]);

        // Enable broadcasting routes with both guest and cast guards
        Broadcast::routes(['middleware' => ['web', 'auth:guest,cast']]);

        // Log route registration
        Log::info('BroadcastServiceProvider: Broadcasting routes registered', [
            'middleware' => ['web', 'auth:guest,cast'],
            'routes_file' => 'routes/channels.php'
        ]);

        require base_path('routes/channels.php');

        // Log successful initialization
        Log::info('BroadcastServiceProvider: Broadcast service initialized successfully');
    }
}
