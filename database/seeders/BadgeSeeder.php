<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Badge;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $badges = [
            [
                'name' => '優しい',
                'icon' => '😊',
                'description' => 'とても優しく接してくれた',
            ],
            [
                'name' => '楽しい',
                'icon' => '🎉',
                'description' => 'とても楽しい時間を過ごせた',
            ],
            [
                'name' => '親切',
                'icon' => '🤝',
                'description' => '親切で丁寧な対応',
            ],
            [
                'name' => '美しい',
                'icon' => '✨',
                'description' => '美しい外見と内面',
            ],
            [
                'name' => '面白い',
                'icon' => '😄',
                'description' => 'とても面白い会話',
            ],
            [
                'name' => '安心',
                'icon' => '🛡️',
                'description' => '安心できる雰囲気',
            ],
            [
                'name' => 'プロ',
                'icon' => '👑',
                'description' => 'プロフェッショナルな対応',
            ],
            [
                'name' => '癒し',
                'icon' => '🌸',
                'description' => '心が癒された',
            ],
            [
                'name' => 'スマート',
                'icon' => '🧠',
                'description' => '知的でスマート',
            ],
            [
                'name' => '元気',
                'icon' => '💪',
                'description' => '元気で明るい',
            ],
        ];

        foreach ($badges as $badge) {
            Badge::create($badge);
        }
    }
} 