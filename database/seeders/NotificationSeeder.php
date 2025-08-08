<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\Guest;
use App\Models\Cast;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guestIds = Guest::pluck('id')->toArray();
        $castIds = Cast::pluck('id')->toArray();

        if (empty($guestIds) && empty($castIds)) {
            return;
        }

        $notifications = [];

        // Guest notifications (no reservation dependency)
        foreach (range(1, 5) as $i) {
            if (empty($guestIds)) { break; }
            $notifications[] = [
                'user_id' => $guestIds[array_rand($guestIds)],
                'user_type' => 'guest',
                'type' => 'system_info',
                'message' => 'ようこそ！新機能のお知らせです。',
                'read' => false,
                'cast_id' => !empty($castIds) ? $castIds[array_rand($castIds)] : null,
                'created_at' => now()->subDays(rand(0, 7)),
                'updated_at' => now(),
            ];
        }

        // Cast notifications
        foreach (range(1, 5) as $i) {
            if (empty($castIds)) { break; }
            $notifications[] = [
                'user_id' => $castIds[array_rand($castIds)],
                'user_type' => 'cast',
                'type' => 'reminder',
                'message' => 'プロフィールを更新しましょう。',
                'read' => (bool)rand(0, 1),
                'cast_id' => null,
                'created_at' => now()->subDays(rand(0, 7)),
                'updated_at' => now(),
            ];
        }

        foreach ($notifications as $data) {
            Notification::create($data);
        }
    }
}


