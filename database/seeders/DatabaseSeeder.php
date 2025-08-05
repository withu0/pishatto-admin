<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cast;
use App\Models\Guest;
use App\Models\Gift;
use App\Models\Reservation;
use App\Models\Like;
use App\Models\Ranking;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call badge seeder
        $this->call(BadgeSeeder::class);
        
        // Create sample gifts
        $gifts = [
            ['name' => 'Rose', 'category' => 'standard', 'points' => 100, 'icon' => 'rose.png'],
            ['name' => 'Chocolate', 'category' => 'standard', 'points' => 50, 'icon' => 'chocolate.png'],
            ['name' => 'Diamond', 'category' => 'grade', 'points' => 500, 'icon' => 'diamond.png'],
            ['name' => 'Gold Coin', 'category' => 'grade', 'points' => 200, 'icon' => 'gold_coin.png'],
        ];

        foreach ($gifts as $giftData) {
            Gift::updateOrCreate(
                ['name' => $giftData['name']],
                $giftData
            );
        }

        // Create sample casts if they don't exist
        $casts = [
            [
                'phone' => '09012345678',
                'nickname' => 'まこちゃん',
                'avatar' => 'avatar-1.png',
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
                'avatar' => 'avatar-2.png',
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
                'avatar' => 'avatar-3.png',
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
                'avatar' => 'avatar-4.png',
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
                'avatar' => 'avatar-5.png',
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
                'avatar' => 'avatar-6.png',
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

        // Create sample guests if they don't exist
        $guests = [
            [
                'phone' => '08012345678',
                'nickname' => '田中太郎',
                'avatar' => 'avatar-4.png',
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
                'avatar' => 'avatar-5.png',
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
                'avatar' => 'avatar-6.png',
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

        // Create sample guest gifts
        $castIds = Cast::pluck('id')->toArray();
        $guestIds = Guest::pluck('id')->toArray();
        $giftIds = Gift::pluck('id')->toArray();

        for ($i = 0; $i < 20; $i++) {
            $existing = DB::table('guest_gifts')
                ->where('sender_guest_id', $guestIds[array_rand($guestIds)])
                ->where('receiver_cast_id', $castIds[array_rand($castIds)])
                ->where('gift_id', $giftIds[array_rand($giftIds)])
                ->where('created_at', now()->subDays(rand(1, 30)))
                ->first();

            if (!$existing) {
                DB::table('guest_gifts')->insert([
                    'sender_guest_id' => $guestIds[array_rand($guestIds)],
                    'receiver_cast_id' => $castIds[array_rand($castIds)],
                    'gift_id' => $giftIds[array_rand($giftIds)],
                    'message' => 'ありがとうございます！',
                    'created_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }

        // Create sample reservations
        for ($i = 0; $i < 15; $i++) {
            Reservation::updateOrCreate(
                [
                    'guest_id' => $guestIds[array_rand($guestIds)],
                    'scheduled_at' => now()->addDays(rand(1, 30)),
                    'created_at' => now()->subDays(rand(1, 30)),
                ],
                [
                    'active' => true,
                    'type' => rand(0, 1) ? 'free' : 'pishatto',
                    'time' => '19:00',
                    'location' => '東京都',
                    'duration' => 60,
                    'details' => 'よろしくお願いします',
                ]
            );
        }

        // Create sample likes
        for ($i = 0; $i < 25; $i++) {
            Like::updateOrCreate(
                [
                    'guest_id' => $guestIds[array_rand($guestIds)],
                    'cast_id' => $castIds[array_rand($castIds)],
                    'created_at' => now()->subDays(rand(1, 30)),
                ]
            );
        }

        $this->command->info('Sample data seeded successfully!');
    }
}
