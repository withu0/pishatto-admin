<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminNews;
use App\Models\User;

class AdminNewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure an admin user exists for FK
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'admin',
                'password' => bcrypt('administrator'),
            ]
        );
        $adminId = $admin->id;

        $newsItems = [
            [
                'title' => 'メンテナンスのお知らせ',
                'content' => '本日深夜2時から4時までシステムメンテナンスを実施します。',
                'target_type' => 'all',
                'status' => 'published',
                'published_at' => now()->subDay(),
                'created_by' => $adminId,
            ],
            [
                'title' => '新機能: ランキングタブ',
                'content' => 'ダッシュボードにランキングタブが追加されました。',
                'target_type' => 'guest',
                'status' => 'published',
                'published_at' => now()->subHours(12),
                'created_by' => $adminId,
            ],
            [
                'title' => 'キャスト向けお知らせ',
                'content' => 'プロフィール改善のヒントを公開しました。',
                'target_type' => 'cast',
                'status' => 'draft',
                'published_at' => null,
                'created_by' => $adminId,
            ],
        ];

        foreach ($newsItems as $item) {
            AdminNews::updateOrCreate(
                [
                    'title' => $item['title'],
                    'created_by' => $adminId,
                ],
                $item
            );
        }
    }
}


