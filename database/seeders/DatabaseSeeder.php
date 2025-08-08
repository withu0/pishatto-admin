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
        // Foundational seeders
        $this->call([
            BadgeSeeder::class,
            GiftSeeder::class,
            LocationSeeder::class,
            CastSeeder::class,
            GuestSeeder::class,
            UserSeeder::class,
            AdminNewsSeeder::class,
            NotificationSeeder::class,
            ConciergeMessageSeeder::class,
        ]);

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
