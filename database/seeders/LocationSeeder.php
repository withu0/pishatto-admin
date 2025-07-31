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
        $locations = [
            ['name' => '東京都', 'prefecture' => '東京都', 'is_active' => true, 'sort_order' => 1],
            ['name' => '大阪府', 'prefecture' => '大阪府', 'is_active' => true, 'sort_order' => 2],
            ['name' => '愛知県', 'prefecture' => '愛知県', 'is_active' => true, 'sort_order' => 3],
            ['name' => '福岡県', 'prefecture' => '福岡県', 'is_active' => true, 'sort_order' => 4],
            ['name' => '北海道', 'prefecture' => '北海道', 'is_active' => true, 'sort_order' => 5],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
} 