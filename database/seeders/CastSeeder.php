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
                'grade' => 'green',
                'grade_points' => 1000,
                'status' => 'active',
                'profile_text' => 'こんにちは！まこです ✨ 明るくて元気な性格で、お客様との素敵な時間をお過ごしできるように頑張ります 💕 お話し好きなので、どんなことでも気軽にお聞かせくださいね 🎵 一緒に楽しい時間を作りましょう！ 🌸',
            ],
            [
                'phone' => '09012345679',
                'nickname' => 'あいちゃん',
                'residence' => '大阪府',
                'location' => '大阪府',
                'birth_year' => 1993,
                'height' => 165,
                'grade' => 'blue',
                'grade_points' => 800,
                'status' => 'active',
                'profile_text' => 'はじめまして！あいと申します 🎀 関西出身で、明るくて笑顔が自慢です 😊 お客様一人一人との出会いを大切に、心から楽しめる時間をお届けします ✨ 趣味はカラオケとお料理なので、いろんなお話ができると思います 🎤 よろしくお願いします！ 💖',
            ],
            [
                'phone' => '09012345680',
                'nickname' => 'ゆきちゃん',
                'residence' => '愛知県',
                'location' => '愛知県',
                'birth_year' => 1997,
                'height' => 158,
                'grade' => 'green',
                'grade_points' => 1200,
                'status' => 'active',
                'profile_text' => 'ゆきです！❄️ 優しい性格で、お客様の心に寄り添えるような存在になれるよう心がけています 🌸 趣味は読書とカフェ巡り、そしてお花のアレンジメントです 📚💐 ゆっくりとお話ししながら、リラックスした時間をお過ごしください ✨ よろしくお願いします！ 💕',
            ],
            [
                'phone' => '09012345681',
                'nickname' => 'さくらちゃん',
                'residence' => '東京都',
                'location' => '東京都',
                'birth_year' => 1994,
                'height' => 162,
                'grade' => 'green',
                'grade_points' => 1100,
                'status' => 'active',
                'profile_text' => 'さくらです！🌸 春生まれで、桜のように華やかで優しい心を大切にしています ✨ お客様との素敵な出会いを、美しい桜の花びらのように大切にしたいと思います 🌸 趣味は写真撮影と旅行で、たくさんのお話しネタがあります 📸 一緒に素敵な時間を作りましょう！ 💖',
            ],
            [
                'phone' => '09012345682',
                'nickname' => 'はなちゃん',
                'residence' => '東京都',
                'location' => '東京都',
                'birth_year' => 1996,
                'height' => 159,
                'grade' => 'blue',
                'grade_points' => 900,
                'status' => 'active',
                'profile_text' => 'はなです！🌺 花のように美しく、そして優しい心を持ちたいと思っています ✨ お客様一人一人との出会いを大切に、心から癒しの時間をお届けします 🎀 趣味はガーデニングとアロマテラピーで、リラックスした空間作りが得意です 🌿 よろしくお願いします！ 💕',
            ],
            [
                'phone' => '09012345683',
                'nickname' => 'みゆちゃん',
                'residence' => '大阪府/心斎橋',
                'location' => '大阪府',
                'birth_year' => 1992,
                'height' => 163,
                'grade' => 'green',
                'grade_points' => 1300,
                'status' => 'active',
                'profile_text' => 'みゆです！💫 経験豊富で、お客様のことを第一に考えたサービスを心がけています ✨ 関西の明るさと、女性らしい優しさを兼ね備えています 🎭 趣味は旅行とグルメ巡りで、全国各地の素敵な場所やお店の情報もお持ちです 🗺️ 一緒に楽しい時間をお過ごししましょう！ 🌟',
            ],
            [
                'line_id' => 'U2c631603ae11e074d8d954a85e773b03',
                'nickname' => 'TARO',
                'residence' => '東京都/西麻布',
                'location' => '東京都',
                'birth_year' => 1995,
                'height' => 160,
                'grade' => 'green',
                'grade_points' => 1000,
                'status' => 'active',
                'profile_text' => 'こんにちは！まこです ✨ 明るくて元気な性格で、お客様との素敵な時間をお過ごしできるように頑張ります 💕 お話し好きなので、どんなことでも気軽にお聞かせくださいね 🎵 一緒に楽しい時間を作りましょう！ 🌸',
            ],
        ];

        foreach ($casts as $castData) {
            // Determine the unique identifier - use phone if available, otherwise use line_id
            $uniqueKey = isset($castData['phone']) ? 'phone' : 'line_id';
            $uniqueValue = $castData[$uniqueKey] ?? null;
            
            if ($uniqueValue) {
                Cast::updateOrCreate(
                    [$uniqueKey => $uniqueValue],
                    $castData
                );
            }
        }
    }
}


