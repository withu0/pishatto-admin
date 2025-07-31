<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Guest;
use App\Models\Cast;
use App\Models\Chat;
use App\Models\ChatFavorite;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChatFavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_chat_favorited_returns_correct_status()
    {
        // Create test data
        $guest = Guest::factory()->create();
        $cast = Cast::factory()->create();
        $chat = Chat::factory()->create([
            'guest_id' => $guest->id,
            'cast_id' => $cast->id,
        ]);

        // Test when chat is not favorited
        $response = $this->getJson("/api/chats/{$chat->id}/favorited/{$guest->id}");
        
        $response->assertStatus(200)
                ->assertJson(['favorited' => false]);

        // Create a favorite
        ChatFavorite::create([
            'guest_id' => $guest->id,
            'chat_id' => $chat->id,
            'created_at' => now(),
        ]);

        // Test when chat is favorited
        $response = $this->getJson("/api/chats/{$chat->id}/favorited/{$guest->id}");
        
        $response->assertStatus(200)
                ->assertJson(['favorited' => true]);
    }

    public function test_is_chat_favorited_returns_404_for_nonexistent_guest()
    {
        $chat = Chat::factory()->create();
        $nonexistentGuestId = 99999;

        $response = $this->getJson("/api/chats/{$chat->id}/favorited/{$nonexistentGuestId}");
        
        $response->assertStatus(404)
                ->assertJson(['error' => 'Guest not found']);
    }

    public function test_is_chat_favorited_returns_404_for_nonexistent_chat()
    {
        $guest = Guest::factory()->create();
        $nonexistentChatId = 99999;

        $response = $this->getJson("/api/chats/{$nonexistentChatId}/favorited/{$guest->id}");
        
        $response->assertStatus(404)
                ->assertJson(['error' => 'Chat not found']);
    }
} 