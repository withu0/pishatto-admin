<?php

namespace App\Services;

use App\Models\Ranking;
use App\Models\Cast;
use App\Models\Guest;
use App\Models\Like;
use App\Models\Gift;
use App\Models\Reservation;
use App\Models\VisitHistory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RankingService
{
    // Point weights for different activities
    const POINT_WEIGHTS = [
        'like_received' => 10,        // Points for receiving a like
        'like_given' => 5,            // Points for giving a like
        'gift_received' => 50,        // Base points for receiving a gift
        'gift_sent' => 20,            // Points for sending a gift
        'reservation_created' => 30,  // Points for creating a reservation
        'reservation_matched' => 40,  // Points for matching a reservation
        'profile_view' => 2,          // Points for profile view
        'gift_value_multiplier' => 0.1, // Additional points per gift value
    ];

    /**
     * Calculate and update rankings for a specific period
     */
    public function calculateRankings(string $period = 'daily', string $region = '全国', string $category = 'gift'): void
    {
        $dateRange = $this->getDateRangeForPeriod($period);
        $categories = ['gift', 'reservation'];
        if ($category !== 'all' && $category !== '総合') {
            $categories = [$category];
        }
        foreach ($categories as $cat) {
            $this->calculateCastRankings($dateRange, $period, $region, $cat);
            $this->calculateGuestRankings($dateRange, $period, $region, $cat);
        }
    }

    /**
     * Calculate rankings for casts
     */
    private function calculateCastRankings(array $dateRange, string $period, string $region, string $category): void
    {
        $casts = Cast::query();
        
        // Filter by region if not national
        if ($region !== '全国') {
            $casts->where('residence', 'like', "%{$region}%");
        }

        $casts = $casts->get();

        foreach ($casts as $cast) {
            $points = $this->calculateCastPoints($cast, $dateRange, $category);
            
            // Store or update ranking
            Ranking::updateOrCreate(
                [
                    'type' => 'cast',
                    'category' => $category,
                    'user_id' => $cast->id,
                    'period' => $period,
                    'region' => $region,
                    'created_at' => $dateRange['start']
                ],
                [
                    'points' => $points
                ]
            );
        }
    }

    /**
     * Calculate rankings for guests
     */
    private function calculateGuestRankings(array $dateRange, string $period, string $region, string $category): void
    {
        $guests = Guest::query();
        
        // Filter by region if not national
        if ($region !== '全国') {
            $guests->where('residence', 'like', "%{$region}%");
        }

        $guests = $guests->get();

        foreach ($guests as $guest) {
            $points = $this->calculateGuestPoints($guest, $dateRange, $category);
            
            // Store or update ranking
            Ranking::updateOrCreate(
                [
                    'type' => 'guest',
                    'category' => $category,
                    'user_id' => $guest->id,
                    'period' => $period,
                    'region' => $region,
                    'created_at' => $dateRange['start']
                ],
                [
                    'points' => $points
                ]
            );
        }
    }

    /**
     * Calculate points for a cast based on various activities
     */
    private function calculateCastPoints(Cast $cast, array $dateRange, string $category = 'gift'): int
    {
        $points = 0;

        if ($category === 'gift') {
            $giftsReceived = DB::table('guest_gifts')
                ->join('gifts', 'guest_gifts.gift_id', '=', 'gifts.id')
                ->where('guest_gifts.receiver_cast_id', $cast->id)
                ->whereBetween('guest_gifts.created_at', [$dateRange['start'], $dateRange['end']])
                ->get();

            foreach ($giftsReceived as $gift) {
                $points += self::POINT_WEIGHTS['gift_received'];
                $points += $gift->points * self::POINT_WEIGHTS['gift_value_multiplier'];
            }
        }

        if ($category === 'reservation') {
            // For casts, we'll count the likes they received as a proxy for reservation interest
            $likesReceived = Like::where('cast_id', $cast->id)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();
            $points += $likesReceived * self::POINT_WEIGHTS['like_received'];
        }

        return $points;
    }

    /**
     * Calculate points for a guest based on various activities
     */
    private function calculateGuestPoints(Guest $guest, array $dateRange, string $category = 'gift'): int
    {
        $points = 0;

        if ($category === 'gift') {
            $giftsSent = DB::table('guest_gifts')
                ->join('gifts', 'guest_gifts.gift_id', '=', 'gifts.id')
                ->where('guest_gifts.sender_guest_id', $guest->id)
                ->whereBetween('guest_gifts.created_at', [$dateRange['start'], $dateRange['end']])
                ->get();

            foreach ($giftsSent as $gift) {
                $points += self::POINT_WEIGHTS['gift_sent'];
                $points += $gift->points * self::POINT_WEIGHTS['gift_value_multiplier'];
            }
        }

        if ($category === 'reservation') {
            $reservationsCreated = Reservation::where('guest_id', $guest->id)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count(); 
            $points += $reservationsCreated * self::POINT_WEIGHTS['reservation_created'];
        }

        return $points;
    }

    /**
     * Get date range for a specific period
     */
    private function getDateRangeForPeriod(string $period): array
    {
        $now = Carbon::now();
        
        switch ($period) {
            case 'daily':
                return [
                    'start' => $now->copy()->subDay()->startOfDay(),
                    'end' => $now->copy()->subDay()->endOfDay()
                ];
            case 'weekly':
                return [
                    'start' => $now->copy()->subWeek()->startOfWeek(),
                    'end' => $now->copy()->subWeek()->endOfWeek()
                ];
            case 'monthly':
                return [
                    'start' => $now->copy()->subMonth()->startOfMonth(),
                    'end' => $now->copy()->subMonth()->endOfMonth()
                ];
            case 'period':
                return [
                    'start' => $now->copy()->subDays(30)->startOfDay(),
                    'end' => $now->copy()->endOfDay()
                ];
            default:
                return [
                    'start' => $now->copy()->subDay()->startOfDay(),
                    'end' => $now->copy()->subDay()->endOfDay()
                ];
        }
    }

    /**
     * Get rankings for display
     */
    public function getRankings(string $type, string $period, string $region = '全国', int $limit = 10, string $category = 'gift'): array
    {
        $query = Ranking::query()
            ->byType($type)
            ->byPeriod($period)
            ->byRegion($region)
            ->where('category', $category)
            ->orderByDesc('points')
            ->limit($limit);

        if ($type === 'cast') {
            $query->with('cast:id,nickname,avatar,residence');
        } else {
            $query->with('guest:id,nickname,avatar,residence');
        }

        return $query->get()->map(function ($ranking, $index) use ($type) {
            $user = $type === 'cast' ? $ranking->cast : $ranking->guest;
            return [
                'id' => $ranking->id,
                'user_id' => $ranking->user_id,
                'name' => $user->nickname ?? 'Unknown',
                'nickname' => $user->nickname ?? 'Unknown',
                'avatar' => $user->avatar ?? '',
                'residence' => $user->residence ?? '',
                'points' => $ranking->points,
                'type' => $ranking->type
            ];
        })->toArray();
    }

    /**
     * Recalculate all rankings (useful for maintenance)
     */
    public function recalculateAllRankings(): void
    {
        $periods = ['daily', 'weekly', 'monthly', 'period'];
        $regions = ['全国', '東京都', '大阪府', '愛知県', '福岡県', '北海道'];
        $categories = ['gift', 'reservation'];

        foreach ($periods as $period) {
            foreach ($regions as $region) {
                foreach ($categories as $category) {
                    $this->calculateRankings($period, $region, $category);
                }
            }
        }
    }

    /**
     * Update rankings in real time for a given region (for both categories, current period)
     */
    public function updateRealTimeRankings($region = '全国')
    {
        $period = 'monthly';
        $categories = ['gift', 'reservation'];
        foreach ($categories as $category) {
            $this->calculateRankings($period, $region, $category);
        }
    }
} 