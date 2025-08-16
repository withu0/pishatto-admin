<?php

/**
 * CSRF Token Test Script
 * This script tests the CSRF token functionality
 */

// Initialize cURL session
$ch = curl_init();

// Set the URL
$baseUrl = 'http://localhost:8000'; // Change this to your actual URL

// First, get a CSRF token
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/csrf-token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt'); // Save cookies
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt'); // Use cookies

$response = curl_exec($ch);
$csrfData = json_decode($response, true);

if (!$csrfData || !isset($csrfData['token'])) {
    echo "Failed to get CSRF token\n";
    echo "Response: " . $response . "\n";
    exit(1);
}

$csrfToken = $csrfData['token'];
echo "CSRF Token obtained: " . substr($csrfToken, 0, 10) . "...\n";

// Test the CSRF token with a POST request
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

echo "Test CSRF Response Code: " . $httpCode . "\n";
echo "Test CSRF Response: " . $response . "\n";

if ($httpCode === 200) {
    echo "✅ CSRF token test PASSED\n";
} else {
    echo "❌ CSRF token test FAILED\n";
}

// Test without CSRF token (should fail)
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest',
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Test without CSRF token Response Code: " . $httpCode . "\n";
echo "Test without CSRF token Response: " . $response . "\n";

if ($httpCode === 419) {
    echo "✅ CSRF protection test PASSED (correctly rejected request without token)\n";
} else {
    echo "❌ CSRF protection test FAILED (should have rejected request without token)\n";
}

curl_close($ch);

// Clean up
if (file_exists('cookies.txt')) {
    unlink('cookies.txt');
}

echo "Test completed.\n";
