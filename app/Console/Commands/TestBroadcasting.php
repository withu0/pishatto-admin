<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;
use App\Models\Chat;
use App\Models\Guest;
use App\Models\Cast;
use Illuminate\Support\Facades\Log;

class TestBroadcasting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'realtime:test-broadcasting {--chat-id=} {--message=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test broadcasting functionality by creating a test message';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $chatId = $this->option('chat-id');
        $messageText = $this->option('message') ?? 'Test broadcast message';

        if (!$chatId) {
            $this->error('Please provide a chat ID with --chat-id option');
            return 1;
        }

        $chat = Chat::find($chatId);
        if (!$chat) {
            $this->error("Chat with ID {$chatId} not found");
            return 1;
        }

        $this->info("Testing broadcasting for chat ID: {$chatId}");
        $this->info("Chat details: Guest ID: {$chat->guest_id}, Cast ID: {$chat->cast_id}");

        // Create a test message
        $message = Message::create([
            'chat_id' => $chatId,
            'sender_guest_id' => $chat->guest_id,
            'message' => $messageText,
            'created_at' => now(),
        ]);

        $this->info("Created test message with ID: {$message->id}");

        // Load relationships
        $message->load(['guest', 'cast', 'chat']);

        // Log the test
        Log::info('TestBroadcasting: Created test message', [
            'message_id' => $message->id,
            'chat_id' => $chatId,
            'sender_guest_id' => $message->sender_guest_id,
            'sender_cast_id' => $message->sender_cast_id,
            'message_text' => $messageText
        ]);

        // Broadcast the message
        $this->info("Broadcasting MessageSent event...");
        event(new \App\Events\MessageSent($message));

        $this->info("Test message broadcasted successfully!");
        $this->info("Check the logs for detailed broadcasting information.");
        $this->info("Check the frontend console for connection and message reception logs.");

        return 0;
    }
}
