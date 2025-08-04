<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConciergeMessage;

class ConciergeMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some sample concierge messages for testing with metadata
        $sampleMessages = [
            [
                'user_id' => 1,
                'user_type' => 'guest',
                'message' => 'こんにちは！patoコンシェルジュです。何かお手伝いできることはありますか？',
                'is_concierge' => true,
                'is_read' => false,
                'message_type' => 'general',
                'category' => 'normal',
                'status' => 'resolved',
                'admin_notes' => 'Welcome message sent',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
                'ip_address' => '192.168.1.100',
                'metadata' => [
                    'source' => 'auto_response',
                    'response_type' => 'welcome',
                    'keywords' => ['welcome', 'greeting'],
                    'sentiment' => 'positive',
                ],
                'created_at' => now()->subMinutes(5),
                'updated_at' => now()->subMinutes(5),
            ],
            [
                'user_id' => 1,
                'user_type' => 'guest',
                'message' => '予約について質問があります',
                'is_concierge' => false,
                'is_read' => true,
                'message_type' => 'reservation',
                'category' => 'normal',
                'status' => 'pending',
                'admin_notes' => 'User asking about reservations',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
                'ip_address' => '192.168.1.100',
                'metadata' => [
                    'source' => 'user_message',
                    'keywords' => ['reservation'],
                    'sentiment' => 'neutral',
                ],
                'created_at' => now()->subMinutes(4),
                'updated_at' => now()->subMinutes(4),
            ],
            [
                'user_id' => 1,
                'user_type' => 'guest',
                'message' => '予約についてのご質問ですね。予約の変更・キャンセルは24時間前まで可能です。詳細はお気軽にお聞かせください。',
                'is_concierge' => true,
                'is_read' => false,
                'message_type' => 'reservation',
                'category' => 'normal',
                'status' => 'in_progress',
                'admin_notes' => 'Auto response about reservations',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
                'ip_address' => '192.168.1.100',
                'metadata' => [
                    'source' => 'auto_response',
                    'response_type' => 'automatic',
                    'keywords' => ['reservation', 'booking'],
                    'sentiment' => 'positive',
                ],
                'created_at' => now()->subMinutes(3),
                'updated_at' => now()->subMinutes(3),
            ],
            [
                'user_id' => 1,
                'user_type' => 'guest',
                'message' => '支払い方法は？',
                'is_concierge' => false,
                'is_read' => true,
                'message_type' => 'payment',
                'category' => 'normal',
                'status' => 'pending',
                'admin_notes' => 'User asking about payment methods',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
                'ip_address' => '192.168.1.100',
                'metadata' => [
                    'source' => 'user_message',
                    'keywords' => ['payment'],
                    'sentiment' => 'neutral',
                ],
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ],
            [
                'user_id' => 1,
                'user_type' => 'guest',
                'message' => '支払いについてのご質問ですね。クレジットカード、銀行振込、コンビニ決済に対応しています。',
                'is_concierge' => true,
                'is_read' => false,
                'message_type' => 'payment',
                'category' => 'normal',
                'status' => 'resolved',
                'admin_notes' => 'Auto response about payment methods',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
                'ip_address' => '192.168.1.100',
                'metadata' => [
                    'source' => 'auto_response',
                    'response_type' => 'automatic',
                    'keywords' => ['payment', 'credit_card', 'bank_transfer'],
                    'sentiment' => 'positive',
                ],
                'created_at' => now()->subMinutes(1),
                'updated_at' => now()->subMinutes(1),
            ],
            [
                'user_id' => 2,
                'user_type' => 'guest',
                'message' => '緊急です！アプリが動きません',
                'is_concierge' => false,
                'is_read' => true,
                'message_type' => 'technical',
                'category' => 'urgent',
                'status' => 'pending',
                'admin_notes' => 'Urgent technical issue reported',
                'user_agent' => 'Mozilla/5.0 (Android; Mobile; rv:68.0)',
                'ip_address' => '192.168.1.101',
                'metadata' => [
                    'source' => 'user_message',
                    'keywords' => ['technical', 'urgent'],
                    'sentiment' => 'negative',
                ],
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(30),
            ],
            [
                'user_id' => 2,
                'user_type' => 'guest',
                'message' => 'トラブルが発生してしまい、申し訳ございません。詳しい状況をお聞かせください。すぐに対応いたします。',
                'is_concierge' => true,
                'is_read' => false,
                'message_type' => 'technical',
                'category' => 'urgent',
                'status' => 'in_progress',
                'admin_notes' => 'Auto response for urgent technical issue',
                'user_agent' => 'Mozilla/5.0 (Android; Mobile; rv:68.0)',
                'ip_address' => '192.168.1.101',
                'metadata' => [
                    'source' => 'auto_response',
                    'response_type' => 'automatic',
                    'keywords' => ['technical', 'urgent', 'support'],
                    'sentiment' => 'positive',
                ],
                'created_at' => now()->subMinutes(29),
                'updated_at' => now()->subMinutes(29),
            ],
        ];

        foreach ($sampleMessages as $message) {
            ConciergeMessage::create($message);
        }

        // Create sample messages for cast user
        $castMessages = [
            [
                'user_id' => 1,
                'user_type' => 'cast',
                'message' => 'こんにちは！patoコンシェルジュです。何かお手伝いできることはありますか？',
                'is_concierge' => true,
                'is_read' => false,
                'message_type' => 'general',
                'category' => 'normal',
                'status' => 'resolved',
                'admin_notes' => 'Welcome message for cast',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
                'ip_address' => '192.168.1.102',
                'metadata' => [
                    'source' => 'auto_response',
                    'response_type' => 'welcome',
                    'keywords' => ['welcome', 'greeting'],
                    'sentiment' => 'positive',
                ],
                'created_at' => now()->subMinutes(10),
                'updated_at' => now()->subMinutes(10),
            ],
            [
                'user_id' => 1,
                'user_type' => 'cast',
                'message' => 'サービスについて教えて',
                'is_concierge' => false,
                'is_read' => true,
                'message_type' => 'inquiry',
                'category' => 'low',
                'status' => 'pending',
                'admin_notes' => 'Cast asking about services',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
                'ip_address' => '192.168.1.102',
                'metadata' => [
                    'source' => 'user_message',
                    'keywords' => ['inquiry', 'service'],
                    'sentiment' => 'neutral',
                ],
                'created_at' => now()->subMinutes(9),
                'updated_at' => now()->subMinutes(9),
            ],
            [
                'user_id' => 1,
                'user_type' => 'cast',
                'message' => 'サービスについてのご質問ですね。当店では様々なサービスをご提供しております。詳しくはお気軽にお聞かせください。',
                'is_concierge' => true,
                'is_read' => false,
                'message_type' => 'inquiry',
                'category' => 'low',
                'status' => 'resolved',
                'admin_notes' => 'Auto response about services',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
                'ip_address' => '192.168.1.102',
                'metadata' => [
                    'source' => 'auto_response',
                    'response_type' => 'automatic',
                    'keywords' => ['inquiry', 'service'],
                    'sentiment' => 'positive',
                ],
                'created_at' => now()->subMinutes(8),
                'updated_at' => now()->subMinutes(8),
            ],
        ];

        foreach ($castMessages as $message) {
            ConciergeMessage::create($message);
        }
    }
} 