<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AdminNews;
use App\Models\User;

class AdminNewsSeeder extends Seeder
{
    public function run(): void
    {
        // Get the first user or create one if none exists
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        $newsData = [
            [
                'title' => '最大30,000Pの紹介クーポンがもらえる特別な期間！',
                'content' => '新規ユーザーをご紹介いただくと、最大30,000Pのクーポンがもらえる特別な期間を実施中です。この機会をお見逃しなく！',
                'target_type' => 'all',
                'status' => 'published',
                'published_at' => now()->subDays(5),
                'created_by' => $user->id,
            ],
            [
                'title' => '利用規約違反者への対処について',
                'content' => '利用規約に違反する行為が確認された場合、アカウントの停止や利用制限などの対処を行います。すべてのユーザーが安心して利用できる環境を維持するため、ご協力をお願いいたします。',
                'target_type' => 'all',
                'status' => 'published',
                'published_at' => now()->subDays(10),
                'created_by' => $user->id,
            ],
            [
                'title' => '【復旧済み】【障害】ギフトが送信できない不具合が発生しておりました',
                'content' => '先日発生していたギフト送信機能の不具合は復旧いたしました。ご不便をおかけし、申し訳ございませんでした。',
                'target_type' => 'guest',
                'status' => 'published',
                'published_at' => now()->subDays(15),
                'created_by' => $user->id,
            ],
            [
                'title' => '2024年10月~12月選出「紳士パットくん」をご存知ですか？🎩',
                'content' => '2024年10月から12月にかけて選出された「紳士パットくん」をご紹介します。素晴らしいサービスを提供してくださったキャストの皆様、ありがとうございました！',
                'target_type' => 'all',
                'status' => 'published',
                'published_at' => now()->subDays(20),
                'created_by' => $user->id,
            ],
            [
                'title' => '新機能リリースのお知らせ',
                'content' => 'より使いやすいサービスを目指して、新機能をリリースいたしました。詳細はアプリ内でご確認ください。',
                'target_type' => 'guest',
                'status' => 'published',
                'published_at' => now()->subDays(25),
                'created_by' => $user->id,
            ],
        ];

        foreach ($newsData as $news) {
            AdminNews::create($news);
        }
    }
} 