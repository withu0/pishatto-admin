<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConciergeMessage;
use App\Models\Guest;
use App\Models\Cast;
use App\Models\User;

class ConciergeMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guestIds = Guest::pluck('id')->toArray();
        $castIds = Cast::pluck('id')->toArray();
        $adminId = User::value('id');

        $messages = [
            [
                'user_type' => 'guest',
                'message' => '予約について質問があります。',
                'is_concierge' => false,
                'is_read' => false,
                'message_type' => 'reservation',
                'category' => 'normal',
                'status' => 'pending',
                'admin_notes' => null,
                'assigned_admin_id' => $adminId,
                'resolved_at' => null,
                'user_agent' => 'Mozilla/5.0',
                'ip_address' => '127.0.0.1',
                'metadata' => ['source' => 'web'],
            ],
            [
                'user_type' => 'cast',
                'message' => '支払いに関する不明点があります。',
                'is_concierge' => false,
                'is_read' => true,
                'message_type' => 'payment',
                'category' => 'urgent',
                'status' => 'in_progress',
                'admin_notes' => '至急対応',
                'assigned_admin_id' => $adminId,
                'resolved_at' => null,
                'user_agent' => 'Mozilla/5.0',
                'ip_address' => '127.0.0.1',
                'metadata' => ['source' => 'web'],
            ],
            [
                'user_type' => 'guest',
                'message' => '技術的な問題が発生しました。',
                'is_concierge' => true,
                'is_read' => false,
                'message_type' => 'technical',
                'category' => 'low',
                'status' => 'resolved',
                'admin_notes' => '解決済み',
                'assigned_admin_id' => $adminId,
                'resolved_at' => now()->subDay(),
                'user_agent' => 'Mozilla/5.0',
                'ip_address' => '127.0.0.1',
                'metadata' => ['source' => 'web'],
            ],
        ];

        foreach ($messages as $data) {
            if ($data['user_type'] === 'guest' && !empty($guestIds)) {
                $data['user_id'] = $guestIds[array_rand($guestIds)];
            } elseif ($data['user_type'] === 'cast' && !empty($castIds)) {
                $data['user_id'] = $castIds[array_rand($castIds)];
            } else {
                // Skip if corresponding users do not exist
                continue;
            }
            ConciergeMessage::create($data);
        }
    }
}


