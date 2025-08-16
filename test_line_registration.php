<?php

/**
 * Test LINE Registration with Avatar Upload
 * This script tests the LINE registration workflow with avatar upload
 */

// Initialize cURL session
$ch = curl_init();

// Set the URL
$baseUrl = 'http://localhost:8000'; // Change this to your actual URL

echo "=== Testing LINE Registration with Avatar Upload ===\n";
echo "Base URL: {$baseUrl}\n\n";

// Test 1: Get CSRF token first
echo "1. Getting CSRF token...\n";
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/csrf-token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'line_test_cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'line_test_cookies.txt');

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

// Test 2: Test LINE registration without avatar (should work)
echo "2. Testing LINE registration without avatar...\n";

$postData = [
    'user_type' => 'guest',
    'line_id' => 'test_line_id_' . time(),
    'line_email' => 'test@example.com',
    'line_name' => 'Test User',
    'line_avatar' => 'https://example.com/avatar.jpg',
    'additional_data' => json_encode([
        'phone' => '1234567890',
        'verificationCode' => '123456',
        'nickname' => 'Test User',
        'favorite_area' => 'Tokyo',
        'location' => 'Tokyo',
        'interests' => [],
        'age' => '25',
        'shiatsu' => 'medium'
    ])
];

curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/line/register');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-CSRF-TOKEN: ' . $csrfToken,
    'X-Requested-With: XMLHttpRequest',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Response Code: {$httpCode}\n";
echo "Response: {$response}\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "✅ LINE registration without avatar PASSED\n\n";
    } else {
        echo "❌ LINE registration without avatar FAILED\n";
        echo "Error: " . ($data['message'] ?? 'Unknown error') . "\n\n";
    }
} else {
    echo "❌ LINE registration without avatar FAILED (HTTP {$httpCode})\n";
    echo "Response: {$response}\n\n";
}

// Test 3: Test LINE registration with avatar (using FormData simulation)
echo "3. Testing LINE registration with avatar...\n";

// Create a simple test image file
$testImagePath = 'test_avatar.jpg';
$testImageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
file_put_contents($testImagePath, $testImageData);

// Create multipart form data
$boundary = '----WebKitFormBoundary' . uniqid();
$postData = '';

// Add form fields
$postData .= "--{$boundary}\r\n";
$postData .= "Content-Disposition: form-data; name=\"user_type\"\r\n\r\n";
$postData .= "guest\r\n";

$postData .= "--{$boundary}\r\n";
$postData .= "Content-Disposition: form-data; name=\"line_id\"\r\n\r\n";
$postData .= "test_line_id_avatar_" . time() . "\r\n";

$postData .= "--{$boundary}\r\n";
$postData .= "Content-Disposition: form-data; name=\"line_email\"\r\n\r\n";
$postData .= "test_avatar@example.com\r\n";

$postData .= "--{$boundary}\r\n";
$postData .= "Content-Disposition: form-data; name=\"line_name\"\r\n\r\n";
$postData .= "Test Avatar User\r\n";

$postData .= "--{$boundary}\r\n";
$postData .= "Content-Disposition: form-data; name=\"line_avatar\"\r\n\r\n";
$postData .= "https://example.com/avatar.jpg\r\n";

$postData .= "--{$boundary}\r\n";
$postData .= "Content-Disposition: form-data; name=\"additional_data\"\r\n\r\n";
$postData .= json_encode([
    'phone' => '1234567890',
    'verificationCode' => '123456',
    'nickname' => 'Test Avatar User',
    'favorite_area' => 'Tokyo',
    'location' => 'Tokyo',
    'interests' => [],
    'age' => '25',
    'shiatsu' => 'medium'
]) . "\r\n";

// Add file
$postData .= "--{$boundary}\r\n";
$postData .= "Content-Disposition: form-data; name=\"profile_photo\"; filename=\"test_avatar.jpg\"\r\n";
$postData .= "Content-Type: image/jpeg\r\n\r\n";
$postData .= file_get_contents($testImagePath) . "\r\n";

$postData .= "--{$boundary}--\r\n";

curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/line/register');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-CSRF-TOKEN: ' . $csrfToken,
    'X-Requested-With: XMLHttpRequest',
    'Accept: application/json',
    'Content-Type: multipart/form-data; boundary=' . $boundary
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Response Code: {$httpCode}\n";
echo "Response: {$response}\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "✅ LINE registration with avatar PASSED\n\n";
    } else {
        echo "❌ LINE registration with avatar FAILED\n";
        echo "Error: " . ($data['message'] ?? 'Unknown error') . "\n\n";
    }
} else {
    echo "❌ LINE registration with avatar FAILED (HTTP {$httpCode})\n";
    echo "Response: {$response}\n\n";
}

// Clean up
unlink($testImagePath);
curl_close($ch);

// Clean up cookies
if (file_exists('line_test_cookies.txt')) {
    unlink('line_test_cookies.txt');
}

echo "=== Test completed ===\n";
echo "\nIf both tests passed, your LINE registration with avatar upload is working correctly!\n";
