<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cast;

class CastSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $casts = [
            [
                'phone' => '09012345678',
                'nickname' => 'まこちゃん',
                'residence' => '東京都',
                'location' => '東京都',
                'birth_year' => 1995,
                'height' => 160,
                'grade' => 'A',
                'grade_points' => 1000,
                'status' => 'active',
            ],
            [
                'phone' => '09012345679',
                'nickname' => 'あいちゃん',
                'residence' => '大阪府',
                'location' => '大阪府',
                'birth_year' => 1993,
                'height' => 165,
                'grade' => 'B',
                'grade_points' => 800,
                'status' => 'active',
            ],
            [
                'phone' => '09012345680',
                'nickname' => 'ゆきちゃん',
                'residence' => '愛知県',
                'location' => '愛知県',
                'birth_year' => 1997,
                'height' => 158,
                'grade' => 'A',
                'grade_points' => 1200,
                'status' => 'active',
            ],
            [
                'phone' => '09012345681',
                'nickname' => 'さくらちゃん',
                'residence' => '東京都',
                'location' => '東京都',
                'birth_year' => 1994,
                'height' => 162,
                'grade' => 'A',
                'grade_points' => 1100,
                'status' => 'active',
            ],
            [
                'phone' => '09012345682',
                'nickname' => 'はなちゃん',
                'residence' => '東京都',
                'location' => '東京都',
                'birth_year' => 1996,
                'height' => 159,
                'grade' => 'B',
                'grade_points' => 900,
                'status' => 'active',
            ],
            [
                'phone' => '09012345683',
                'nickname' => 'みゆちゃん',
                'residence' => '大阪府',
                'location' => '大阪府',
                'birth_year' => 1992,
                'height' => 163,
                'grade' => 'A',
                'grade_points' => 1300,
                'status' => 'active',
            ],
        ];

        foreach ($casts as $castData) {
            Cast::updateOrCreate(
                ['phone' => $castData['phone']],
                $castData
            );
        }
    }
}


