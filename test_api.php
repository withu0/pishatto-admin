<?php

// Simple test script to verify API endpoints
$baseUrl = 'http://localhost:8000/api';

$endpoints = [
    '/admin/dashboard',
    '/admin/matching',
    '/admin/sales',
    '/admin/messages',
    '/admin/ranking',
    '/admin/tweets',
    '/admin/receipts',
    '/admin/payments',
];

foreach ($endpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Testing: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode === 200) {
        echo "✓ Success (HTTP $httpCode)\n";
        $data = json_decode($response, true);
        if (is_array($data)) {
            echo "  Data keys: " . implode(', ', array_keys($data)) . "\n";
        }
    } else {
        echo "✗ Failed (HTTP $httpCode)\n";
        echo "  Response: $response\n";
    }
    
    curl_close($ch);
    echo "\n";
} 