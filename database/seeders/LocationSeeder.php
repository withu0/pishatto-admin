<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear all existing location data first
        Location::truncate();
        
        $locations = [
            // 東京都
            ['name' => '東京都', 'prefecture' => '六本木', 'is_active' => true, 'sort_order' => 1],
            ['name' => '東京都', 'prefecture' => '西麻布', 'is_active' => true, 'sort_order' => 2],
            ['name' => '東京都', 'prefecture' => '麻布十番', 'is_active' => true, 'sort_order' => 3],
            ['name' => '東京都', 'prefecture' => '渋谷', 'is_active' => true, 'sort_order' => 4],
            ['name' => '東京都', 'prefecture' => '赤坂', 'is_active' => true, 'sort_order' => 5],
            ['name' => '東京都', 'prefecture' => '池袋', 'is_active' => true, 'sort_order' => 6],
            ['name' => '東京都', 'prefecture' => '銀座', 'is_active' => true, 'sort_order' => 7],
            ['name' => '東京都', 'prefecture' => '日本橋', 'is_active' => true, 'sort_order' => 8],
            ['name' => '東京都', 'prefecture' => '中目黒', 'is_active' => true, 'sort_order' => 9],
            ['name' => '東京都', 'prefecture' => '新宿', 'is_active' => true, 'sort_order' => 10],
            
            // 大阪府
            ['name' => '大阪府', 'prefecture' => '梅田', 'is_active' => true, 'sort_order' => 11],
            ['name' => '大阪府', 'prefecture' => '北新地', 'is_active' => true, 'sort_order' => 12],
            ['name' => '大阪府', 'prefecture' => '心斎橋', 'is_active' => true, 'sort_order' => 13],
            ['name' => '大阪府', 'prefecture' => 'なんば', 'is_active' => true, 'sort_order' => 14],
            ['name' => '大阪府', 'prefecture' => '京橋', 'is_active' => true, 'sort_order' => 15],
            ['name' => '大阪府', 'prefecture' => '十三', 'is_active' => true, 'sort_order' => 16],
            ['name' => '大阪府', 'prefecture' => '天満', 'is_active' => true, 'sort_order' => 17],
            ['name' => '大阪府', 'prefecture' => '福島', 'is_active' => true, 'sort_order' => 18],
            ['name' => '大阪府', 'prefecture' => '天王寺', 'is_active' => true, 'sort_order' => 19],
            ['name' => '大阪府', 'prefecture' => '本町', 'is_active' => true, 'sort_order' => 20],
            
            // 愛知県
            ['name' => '愛知県', 'prefecture' => '栄', 'is_active' => true, 'sort_order' => 21],
            ['name' => '愛知県', 'prefecture' => '名駅', 'is_active' => true, 'sort_order' => 22],
            ['name' => '愛知県', 'prefecture' => '伏見', 'is_active' => true, 'sort_order' => 23],
            ['name' => '愛知県', 'prefecture' => '錦三', 'is_active' => true, 'sort_order' => 24],
            ['name' => '愛知県', 'prefecture' => '泉', 'is_active' => true, 'sort_order' => 25],
            ['name' => '愛知県', 'prefecture' => '今池', 'is_active' => true, 'sort_order' => 26],
            ['name' => '愛知県', 'prefecture' => '金山', 'is_active' => true, 'sort_order' => 27],
            
            // 福岡県
            ['name' => '福岡県', 'prefecture' => '天神', 'is_active' => true, 'sort_order' => 28],
            ['name' => '福岡県', 'prefecture' => '大名', 'is_active' => true, 'sort_order' => 29],
            ['name' => '福岡県', 'prefecture' => '中州', 'is_active' => true, 'sort_order' => 30],
            ['name' => '福岡県', 'prefecture' => '今泉', 'is_active' => true, 'sort_order' => 31],
            ['name' => '福岡県', 'prefecture' => '博多', 'is_active' => true, 'sort_order' => 32],
            ['name' => '福岡県', 'prefecture' => '春吉', 'is_active' => true, 'sort_order' => 33],
            ['name' => '福岡県', 'prefecture' => '北九州', 'is_active' => true, 'sort_order' => 34],
            
            // 北海道
            ['name' => '北海道', 'prefecture' => 'すすきの', 'is_active' => true, 'sort_order' => 35],
            ['name' => '北海道', 'prefecture' => '札幌駅', 'is_active' => true, 'sort_order' => 36],
            ['name' => '北海道', 'prefecture' => '函館', 'is_active' => true, 'sort_order' => 37],
            ['name' => '北海道', 'prefecture' => '帯広', 'is_active' => true, 'sort_order' => 38],
            
            // 神奈川県
            ['name' => '神奈川県', 'prefecture' => '横浜', 'is_active' => true, 'sort_order' => 39],
            ['name' => '神奈川県', 'prefecture' => '新横浜', 'is_active' => true, 'sort_order' => 40],
            ['name' => '神奈川県', 'prefecture' => '川崎', 'is_active' => true, 'sort_order' => 41],
            ['name' => '神奈川県', 'prefecture' => 'みなとみらい', 'is_active' => true, 'sort_order' => 42],
            ['name' => '神奈川県', 'prefecture' => '関内', 'is_active' => true, 'sort_order' => 43],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
} 