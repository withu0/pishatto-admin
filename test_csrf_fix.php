<?php

/**
 * Test CSRF Fix Script
 * This script tests the CSRF token functionality after implementing the fix
 */

// Initialize cURL session
$ch = curl_init();

// Set the URL
$baseUrl = 'http://localhost:8000'; // Change this to your actual URL

echo "=== Testing CSRF Fix ===\n";
echo "Base URL: {$baseUrl}\n\n";

// Test 1: Get CSRF token
echo "1. Getting CSRF token...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/csrf-token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'test_cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'test_cookies.txt');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode === 200) {
    $csrfData = json_decode($response, true);
    if ($csrfData && isset($csrfData['token'])) {
        $csrfToken = $csrfData['token'];
        echo "✅ CSRF token obtained: " . substr($csrfToken, 0, 10) . "...\n\n";
    } else {
        echo "❌ Failed to parse CSRF token\n";
        exit(1);
    }
} else {
    echo "❌ Failed to get CSRF token (HTTP {$httpCode})\n";
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

if ($httpCode === 200) {
    echo "✅ CSRF token validation PASSED\n\n";
} else {
    echo "❌ CSRF token validation FAILED (HTTP {$httpCode})\n";
    echo "Response: {$response}\n\n";
}

// Test 3: Test without CSRF token (should fail)
echo "3. Testing without CSRF token (should fail)...\n";
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest',
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode === 419) {
    echo "✅ CSRF protection working correctly (rejected request without token)\n\n";
} else {
    echo "❌ CSRF protection not working as expected (HTTP {$httpCode})\n";
    echo "Response: {$response}\n\n";
}

// Test 4: Test with invalid CSRF token (should fail)
echo "4. Testing with invalid CSRF token (should fail)...\n";
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-CSRF-TOKEN: invalid_token_12345',
    'X-Requested-With: XMLHttpRequest',
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode === 419) {
    echo "✅ CSRF protection working correctly (rejected invalid token)\n\n";
} else {
    echo "❌ CSRF protection not working as expected (HTTP {$httpCode})\n";
    echo "Response: {$response}\n\n";
}

curl_close($ch);

// Clean up
if (file_exists('test_cookies.txt')) {
    unlink('test_cookies.txt');
}

echo "=== Test completed ===\n";
echo "\nIf all tests passed, your CSRF fix is working correctly!\n";
echo "\nNext steps:\n";
echo "1. Make sure your frontend includes the CSRF setup script\n";
echo "2. Test your actual application functionality\n";
echo "3. Check browser console for any CSRF-related errors\n";
