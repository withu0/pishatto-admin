<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RealtimeLogService;
use App\Models\Message;
use App\Models\Chat;
use App\Models\Guest;
use App\Models\Cast;

class TestRealtimeLogging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'realtime:test-logging {--message-id=} {--chat-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test realtime logging functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Realtime Logging...');

        // Test configuration logging
        RealtimeLogService::logConfig([
            'test' => true,
            'command' => 'test-realtime-logging'
        ]);

        // Test connection logging
        RealtimeLogService::logConnection('Test Connection', [
            'test' => true,
            'ip' => '127.0.0.1',
            'user_agent' => 'Test Agent'
        ]);

        // Test broadcast logging
        RealtimeLogService::logBroadcast('Test Broadcast', [
            'test' => true,
            'channel' => 'test.channel',
            'event' => 'TestEvent'
        ]);

        // Test channel authorization logging
        $guest = Guest::first();
        if ($guest) {
            RealtimeLogService::logChannelAuth('guest.1', $guest, true, [
                'test' => true
            ]);
        }

        $cast = Cast::first();
        if ($cast) {
            RealtimeLogService::logChannelAuth('cast.1', $cast, true, [
                'test' => true
            ]);
        }

        // Test message logging if message ID provided
        $messageId = $this->option('message-id');
        if ($messageId) {
            $message = Message::find($messageId);
            if ($message) {
                RealtimeLogService::logMessage('Test Message Log', $message, [
                    'test' => true
                ]);
            } else {
                $this->error("Message with ID {$messageId} not found");
            }
        }

        // Test chat logging if chat ID provided
        $chatId = $this->option('chat-id');
        if ($chatId) {
            $chat = Chat::find($chatId);
            if ($chat) {
                RealtimeLogService::logChat('Test Chat Log', $chat, [
                    'test' => true
                ]);
            } else {
                $this->error("Chat with ID {$chatId} not found");
            }
        }

        // Test error logging
        RealtimeLogService::logError('Test Error', [
            'test' => true,
            'error_code' => 'TEST_ERROR'
        ]);

        $this->info('Realtime logging test completed. Check storage/logs/realtime.log for results.');
    }
}
