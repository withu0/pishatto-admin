<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Guest;

class GuestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guests = [
            [
                'phone' => '08012345678',
                'nickname' => '田中太郎',
                'residence' => '東京都',
                'birth_year' => 1990,
                'height' => 175,
                'age' => '30代',
                'shiatsu' => '普通',
                'location' => '東京都',
            ],
            [
                'phone' => '08012345679',
                'nickname' => '佐藤次郎',
                'residence' => '大阪府',
                'birth_year' => 1988,
                'height' => 170,
                'age' => '30代',
                'shiatsu' => '強め',
                'location' => '大阪府',
            ],
            [
                'phone' => '08012345680',
                'nickname' => '鈴木三郎',
                'residence' => '愛知県',
                'birth_year' => 1992,
                'height' => 180,
                'age' => '20代',
                'shiatsu' => '弱め',
                'location' => '愛知県',
            ],
        ];

        foreach ($guests as $guestData) {
            Guest::updateOrCreate(
                ['phone' => $guestData['phone']],
                $guestData
            );
        }
    }
}


