<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Gift;

class GiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gifts = [
            [
                'name' => '花束',
                'category' => 'standard',
                'points' => 100,
                'icon' => '🌹',
            ],
            [
                'name' => 'ぬいぐるみ',
                'category' => 'standard',
                'points' => 200,
                'icon' => '🧸',
            ],
            [
                'name' => 'チョコレート',
                'category' => 'standard',
                'points' => 50,
                'icon' => '🍫',
            ],
            [
                'name' => '東京限定ギフト',
                'category' => 'regional',
                'points' => 500,
                'icon' => '🗼',
            ],
            [
                'name' => '大阪限定ギフト',
                'category' => 'regional',
                'points' => 500,
                'icon' => '🏯',
            ],
            [
                'name' => 'VIPギフト',
                'category' => 'grade',
                'points' => 1000,
                'icon' => '👑',
            ],
            [
                'name' => 'プレミアムギフト',
                'category' => 'grade',
                'points' => 2000,
                'icon' => '💎',
            ],
            [
                'name' => 'マイギフト1',
                'category' => 'mygift',
                'points' => 300,
                'icon' => '🎁',
            ],
            [
                'name' => 'マイギフト2',
                'category' => 'mygift',
                'points' => 400,
                'icon' => '💝',
            ],
        ];

        foreach ($gifts as $gift) {
            Gift::create($gift);
        }
    }
}
