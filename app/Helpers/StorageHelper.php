<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    /**
     * Generate a URL for a file stored in public disk with proper port handling
     */
    public static function publicUrl($path)
    {
        // if (config('app.env') === 'local') {
        //     return 'http://localhost:8000/storage/' . $path;
        // }
        
        return Storage::disk('public')->url($path);
    }
    
    /**
     * Check if a file exists in public storage
     */
    public static function publicExists($path)
    {
        return Storage::disk('public')->exists($path);
    }
    
    /**
     * Get the full path to a file in public storage
     */
    public static function publicPath($path)
    {
        return storage_path('app/public/' . $path);
    }
} 