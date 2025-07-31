<?php

require_once 'vendor/autoload.php';

use App\Models\Guest;
use App\Models\Cast;
use App\Models\Chat;
use App\Models\ChatFavorite;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Chat Favorite Functionality\n";
echo "==================================\n\n";

try {
    // Create test data
    echo "Creating test data...\n";
    
    $guest = Guest::create([
        'phone' => '1234567890' . time(),
        'nickname' => 'Test Guest',
        'location' => 'Tokyo',
        'age' => '25',
        'shiatsu' => 'medium',
    ]);
    
    $cast = Cast::create([
        'phone' => '0987654321' . time(),
        'nickname' => 'Test Cast',
        'location' => 'Tokyo',
        'status' => 'active',
    ]);
    
    $chat = Chat::create([
        'guest_id' => $guest->id,
        'cast_id' => $cast->id,
    ]);
    
    echo "Guest ID: {$guest->id}\n";
    echo "Cast ID: {$cast->id}\n";
    echo "Chat ID: {$chat->id}\n\n";
    
    // Test 1: Check if chat is favorited (should be false)
    echo "Test 1: Checking if chat is favorited (should be false)...\n";
    $chatFavorite = ChatFavorite::where('guest_id', $guest->id)
        ->where('chat_id', $chat->id)
        ->first();
    
    $isFavorited = $chatFavorite ? true : false;
    echo "Result: " . ($isFavorited ? 'true' : 'false') . "\n\n";
    
    // Test 2: Create a favorite
    echo "Test 2: Creating a favorite...\n";
    ChatFavorite::create([
        'guest_id' => $guest->id,
        'chat_id' => $chat->id,
        'created_at' => now(),
    ]);
    echo "Favorite created successfully\n\n";
    
    // Test 3: Check if chat is favorited (should be true)
    echo "Test 3: Checking if chat is favorited (should be true)...\n";
    $chatFavorite = ChatFavorite::where('guest_id', $guest->id)
        ->where('chat_id', $chat->id)
        ->first();
    
    $isFavorited = $chatFavorite ? true : false;
    echo "Result: " . ($isFavorited ? 'true' : 'false') . "\n\n";
    
    // Test 4: Check with guest relationship
    echo "Test 4: Checking with guest relationship...\n";
    $guest->refresh();
    $favoritedChats = $guest->favoritedChats;
    echo "Number of favorited chats: " . $favoritedChats->count() . "\n";
    
    if ($favoritedChats->count() > 0) {
        echo "First favorited chat ID: " . $favoritedChats->first()->id . "\n";
    }
    
    echo "\nAll tests completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} 