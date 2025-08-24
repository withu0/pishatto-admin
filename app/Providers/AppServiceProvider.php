<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure JSON responses handle multibyte text and invalid UTF-8 safely (if supported)
        if (method_exists(SymfonyJsonResponse::class, 'setDefaultEncodingOptions')) {
            SymfonyJsonResponse::setDefaultEncodingOptions(
                JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE
            );
        }
    }
}
