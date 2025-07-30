<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $badges = [
            [
                'name' => '初回訪問',
                'icon' => '🎉',
                'description' => '初回訪問時に獲得できるバッジ',
            ],
            [
                'name' => '常連客',
                'icon' => '👑',
                'description' => '10回以上訪問した常連客に贈られるバッジ',
            ],
            [
                'name' => 'VIP',
                'icon' => '💎',
                'description' => '特別なVIP会員に贈られるバッジ',
            ],
            [
                'name' => 'ギフトマスター',
                'icon' => '🎁',
                'description' => '多くのギフトを贈ったユーザーに贈られるバッジ',
            ],
            [
                'name' => 'チャット名人',
                'icon' => '💬',
                'description' => '積極的にチャットを利用したユーザーに贈られるバッジ',
            ],
            [
                'name' => 'マッチング成功',
                'icon' => '💕',
                'description' => 'マッチングに成功したユーザーに贈られるバッジ',
            ],
            [
                'name' => 'レビュアー',
                'icon' => '⭐',
                'description' => 'レビューを投稿したユーザーに贈られるバッジ',
            ],
            [
                'name' => '早期登録',
                'icon' => '🚀',
                'description' => 'サービス開始初期に登録したユーザーに贈られるバッジ',
            ],
        ];

        foreach ($badges as $badge) {
            Badge::create($badge);
        }
    }
}
