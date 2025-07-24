<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

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
        // Enable broadcasting routes with both guest and cast guards
        Broadcast::routes(['middleware' => ['web', 'auth:guest,cast']]);
        require base_path('routes/channels.php');
    }
}
