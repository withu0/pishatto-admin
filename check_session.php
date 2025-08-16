<?php

/**
 * Session Configuration Check Script
 * This script checks session configuration and identifies potential issues
 */

echo "=== Session Configuration Check ===\n\n";

// Check if session is started
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Session is active\n";
} else {
    echo "❌ Session is not active\n";
}

// Check session configuration
echo "\n--- Session Configuration ---\n";
echo "Session save path: " . session_save_path() . "\n";
echo "Session name: " . session_name() . "\n";
echo "Session lifetime: " . ini_get('session.gc_maxlifetime') . " seconds\n";
echo "Session cookie lifetime: " . ini_get('session.cookie_lifetime') . " seconds\n";
echo "Session cookie path: " . ini_get('session.cookie_path') . "\n";
echo "Session cookie domain: " . ini_get('session.cookie_domain') . "\n";
echo "Session cookie secure: " . (ini_get('session.cookie_secure') ? 'Yes' : 'No') . "\n";
echo "Session cookie httponly: " . (ini_get('session.cookie_httponly') ? 'Yes' : 'No') . "\n";
echo "Session use cookies: " . (ini_get('session.use_cookies') ? 'Yes' : 'No') . "\n";
echo "Session use only cookies: " . (ini_get('session.use_only_cookies') ? 'Yes' : 'No') . "\n";

// Check session directory permissions
$sessionPath = session_save_path();
if (is_dir($sessionPath)) {
    echo "\n--- Session Directory Permissions ---\n";
    echo "Session directory exists: ✅\n";
    echo "Session directory readable: " . (is_readable($sessionPath) ? '✅' : '❌') . "\n";
    echo "Session directory writable: " . (is_writable($sessionPath) ? '✅' : '❌') . "\n";
    
    // Check session files
    $sessionFiles = glob($sessionPath . '/sess_*');
    echo "Number of session files: " . count($sessionFiles) . "\n";
    
    if (count($sessionFiles) > 0) {
        echo "Sample session files:\n";
        foreach (array_slice($sessionFiles, 0, 5) as $file) {
            echo "  - " . basename($file) . " (" . filesize($file) . " bytes)\n";
        }
    }
} else {
    echo "\n❌ Session directory does not exist: {$sessionPath}\n";
}

// Check Laravel session configuration
echo "\n--- Laravel Session Configuration ---\n";

// Try to load Laravel environment
$laravelPath = __DIR__;
$bootstrapPath = $laravelPath . '/bootstrap/app.php';

if (file_exists($bootstrapPath)) {
    try {
        // Load Laravel application
        $app = require $bootstrapPath;
        
        // Check session driver
        $sessionDriver = config('session.driver');
        echo "Session driver: {$sessionDriver}\n";
        
        // Check session lifetime
        $sessionLifetime = config('session.lifetime');
        echo "Session lifetime: {$sessionLifetime} minutes\n";
        
        // Check session expire on close
        $expireOnClose = config('session.expire_on_close');
        echo "Expire on close: " . ($expireOnClose ? 'Yes' : 'No') . "\n";
        
        // Check session encryption
        $encrypt = config('session.encrypt');
        echo "Session encryption: " . ($encrypt ? 'Yes' : 'No') . "\n";
        
        // Check session table (if using database)
        if ($sessionDriver === 'database') {
            $sessionTable = config('session.table');
            echo "Session table: {$sessionTable}\n";
            
            // Check if table exists
            try {
                $tableExists = \Illuminate\Support\Facades\Schema::hasTable($sessionTable);
                echo "Session table exists: " . ($tableExists ? '✅' : '❌') . "\n";
                
                if ($tableExists) {
                    $sessionCount = \Illuminate\Support\Facades\DB::table($sessionTable)->count();
                    echo "Number of sessions in database: {$sessionCount}\n";
                }
            } catch (Exception $e) {
                echo "❌ Error checking session table: " . $e->getMessage() . "\n";
            }
        }
        
        // Check session files location (if using file driver)
        if ($sessionDriver === 'file') {
            $sessionFiles = config('session.files');
            echo "Session files path: {$sessionFiles}\n";
            
            if (is_dir($sessionFiles)) {
                $fileCount = count(glob($sessionFiles . '/*'));
                echo "Number of session files: {$fileCount}\n";
            } else {
                echo "❌ Session files directory does not exist\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error loading Laravel application: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Laravel bootstrap file not found\n";
}

// Test session functionality
echo "\n--- Session Functionality Test ---\n";

try {
    // Start session
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Test session write
    $_SESSION['test_key'] = 'test_value_' . time();
    echo "✅ Session write test passed\n";
    
    // Test session read
    $testValue = $_SESSION['test_key'] ?? null;
    if ($testValue) {
        echo "✅ Session read test passed\n";
    } else {
        echo "❌ Session read test failed\n";
    }
    
    // Test session persistence
    session_write_close();
    session_start();
    $persistedValue = $_SESSION['test_key'] ?? null;
    if ($persistedValue) {
        echo "✅ Session persistence test passed\n";
    } else {
        echo "❌ Session persistence test failed\n";
    }
    
    // Clean up test data
    unset($_SESSION['test_key']);
    
} catch (Exception $e) {
    echo "❌ Session functionality test failed: " . $e->getMessage() . "\n";
}

echo "\n=== Check completed ===\n";
echo "\nRecommendations:\n";
echo "1. Ensure session directory has proper permissions (755)\n";
echo "2. Check that session files are being created and are writable\n";
echo "3. Verify session configuration in .env file\n";
echo "4. Make sure session driver is properly configured\n";
echo "5. Check for any session-related errors in Laravel logs\n";

