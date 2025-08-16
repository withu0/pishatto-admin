<?php

/**
 * CSRF Debug Script
 * This script helps debug CSRF token issues
 */

// Initialize cURL session
$ch = curl_init();

// Set the URL
$baseUrl = 'http://localhost:8000'; // Change this to your actual URL

echo "=== CSRF Debug Script ===\n";
echo "Base URL: {$baseUrl}\n\n";

// Test 1: Get CSRF token
echo "1. Testing CSRF token retrieval...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/csrf-token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'debug_cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'debug_cookies.txt');
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Response Code: {$httpCode}\n";
echo "Response: {$response}\n";

if ($httpCode === 200) {
    $csrfData = json_decode($response, true);
    if ($csrfData && isset($csrfData['token'])) {
        $csrfToken = $csrfData['token'];
        echo "✅ CSRF token obtained successfully\n";
        echo "Token (first 10 chars): " . substr($csrfToken, 0, 10) . "...\n";
        echo "Token length: " . strlen($csrfToken) . "\n\n";
    } else {
        echo "❌ Failed to parse CSRF token response\n\n";
        exit(1);
    }
} else {
    echo "❌ Failed to get CSRF token\n\n";
    exit(1);
}

// Test 2: Test with valid CSRF token
echo "2. Testing with valid CSRF token...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/test-csrf');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-CSRF-TOKEN: ' . $csrfToken,
    'X-Requested-With: XMLHttpRequest',
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Response Code: {$httpCode}\n";
echo "Response: {$response}\n";

if ($httpCode === 200) {
    echo "✅ CSRF token validation PASSED\n\n";
} else {
    echo "❌ CSRF token validation FAILED\n\n";
}

// Test 3: Test without CSRF token
echo "3. Testing without CSRF token (should fail)...\n";
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest',
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Response Code: {$httpCode}\n";
echo "Response: {$response}\n";

if ($httpCode === 419) {
    echo "✅ CSRF protection working correctly (rejected request without token)\n\n";
} else {
    echo "❌ CSRF protection not working as expected\n\n";
}

// Test 4: Test with invalid CSRF token
echo "4. Testing with invalid CSRF token...\n";
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-CSRF-TOKEN: invalid_token_12345',
    'X-Requested-With: XMLHttpRequest',
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Response Code: {$httpCode}\n";
echo "Response: {$response}\n";

if ($httpCode === 419) {
    echo "✅ CSRF protection working correctly (rejected invalid token)\n\n";
} else {
    echo "❌ CSRF protection not working as expected\n\n";
}

// Test 5: Test session persistence
echo "5. Testing session persistence...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/csrf-token');
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Response Code: {$httpCode}\n";
echo "Response: {$response}\n";

if ($httpCode === 200) {
    $newCsrfData = json_decode($response, true);
    if ($newCsrfData && isset($newCsrfData['token'])) {
        $newCsrfToken = $newCsrfData['token'];
        if ($newCsrfToken === $csrfToken) {
            echo "✅ Session persistence working (same token)\n\n";
        } else {
            echo "⚠️  Session token changed (this might be expected)\n\n";
        }
    }
}

// Test 6: Check cookies
echo "6. Checking cookies...\n";
if (file_exists('debug_cookies.txt')) {
    $cookies = file_get_contents('debug_cookies.txt');
    echo "Cookies file contents:\n{$cookies}\n\n";
} else {
    echo "No cookies file found\n\n";
}

curl_close($ch);

// Clean up
if (file_exists('debug_cookies.txt')) {
    unlink('debug_cookies.txt');
}

echo "=== Debug completed ===\n";
echo "Check the Laravel logs for additional CSRF debugging information.\n";
echo "Log file location: storage/logs/laravel.log\n";
