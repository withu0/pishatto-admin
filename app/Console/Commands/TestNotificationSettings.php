<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotificationSetting;
use App\Services\NotificationService;

class TestNotificationSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:notification-settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test notification settings functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Notification Settings...');

        // Test 1: Create a notification setting
        $setting = NotificationSetting::updateOrCreate(
            [
                'user_id' => 1,
                'user_type' => 'guest',
                'setting_key' => 'likes',
            ],
            [
                'enabled' => false,
            ]
        );

        $this->info("Created notification setting: " . ($setting->enabled ? 'enabled' : 'disabled'));

        // Test 2: Check if notification is enabled
        $isEnabled = NotificationService::isNotificationEnabled(1, 'guest', 'likes');
        $this->info("Notification enabled: " . ($isEnabled ? 'true' : 'false'));

        // Test 3: Try to send a notification (should not send since disabled)
        $notification = NotificationService::sendLikeNotification(1, 1, 'Test Cast');
        $this->info("Notification sent: " . ($notification ? 'yes' : 'no'));

        // Test 4: Enable the setting and try again
        NotificationSetting::updateOrCreate(
            [
                'user_id' => 1,
                'user_type' => 'guest',
                'setting_key' => 'likes',
            ],
            [
                'enabled' => true,
            ]
        );

        $notification = NotificationService::sendLikeNotification(1, 1, 'Test Cast');
        $this->info("Notification sent after enabling: " . ($notification ? 'yes' : 'no'));

        $this->info('Test completed successfully!');
    }
} 