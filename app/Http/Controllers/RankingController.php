<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Guest;
use App\Models\Cast;
use App\Models\Gift;
use App\Models\Reservation;
use App\Models\GuestGift;
use Carbon\Carbon;

class RankingController extends Controller
{
    /**
     * New: Get monthly earned ranking based on point_transactions (gift + transfer) for casts
     * Query params:
     * - limit (optional): number of top users to return
     * - castId (optional): include current cast rank summary
     * - month (optional): 'current' | 'last' (default: current)
     */
    public function getMonthlyEarnedRanking(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $castId = $request->query('castId');
        $month = $request->query('month', 'current');

        $now = Carbon::now();
        if ($month === 'last') {
            $start = $now->copy()->subMonth()->startOfMonth();
            $end = $now->copy()->subMonth()->endOfMonth();
        } else {
            $start = $now->copy()->startOfMonth();
            $end = $now->copy()->endOfMonth();
        }

        // Sum earned points for casts from point_transactions
        $baseQuery = DB::table('point_transactions as pt')
            ->select('pt.cast_id', DB::raw('COALESCE(SUM(pt.amount), 0) as points'))
            ->whereNotNull('pt.cast_id')
            ->whereIn('pt.type', ['gift', 'transfer'])
            ->whereBetween('pt.created_at', [$start, $end])
            ->groupBy('pt.cast_id');

        $ranked = DB::table(DB::raw("({$baseQuery->toSql()}) as totals"))
            ->mergeBindings($baseQuery)
            ->join('casts as c', 'c.id', '=', 'totals.cast_id')
            ->select(
                'c.id',
                DB::raw('c.nickname as name'),
                'c.avatar',
                'totals.points'
            )
            ->orderByDesc('totals.points')
            ->orderBy('c.id')
            ->limit($limit)
            ->get();

        // Compute rank for requested cast if provided
        $myRank = null;
        $myPoints = null;
        if ($castId) {
            $myTotal = (clone $baseQuery)
                ->where('pt.cast_id', $castId)
                ->first();
            $myPoints = $myTotal ? (int) $myTotal->points : 0;

            // Count how many have strictly greater points to derive base rank
            $aheadCount = DB::table(DB::raw("({$baseQuery->toSql()}) as totals"))
                ->mergeBindings($baseQuery)
                ->where('totals.points', '>', $myPoints)
                ->count();

            if ($myPoints > 0) {
                $myRank = $aheadCount + 1;
            } else {
                // For zero points, determine deterministic order among zero-point casts by name
                $zeroList = DB::table('casts as c')
                    ->leftJoin(DB::raw("({$baseQuery->toSql()}) as totals"), 'totals.cast_id', '=', 'c.id')
                    ->mergeBindings($baseQuery)
                    ->select('c.id', DB::raw('c.nickname as name'))
                    ->whereRaw('COALESCE(totals.points, 0) = 0')
                    ->orderBy('name')
                    ->get();

                $positionInZero = $zeroList->search(function ($row) use ($castId) {
                    return (int) $row->id === (int) $castId;
                });
                if ($positionInZero === false) {
                    $positionInZero = 0; // Fallback to first if not found for any reason
                }
                $myRank = $aheadCount + ((int) $positionInZero) + 1;
            }
        }

        return response()->json([
            'data' => $ranked->map(function ($row, $idx) {
                return [
                    'user_id' => $row->id,
                    'name' => $row->name,
                    'avatar' => $row->avatar,
                    'points' => (int) $row->points,
                    'rank' => $idx + 1,
                ];
            }),
            'summary' => [
                'month' => $month,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'cast_id' => $castId ? (int) $castId : null,
                'my_points' => $myPoints,
                'my_rank' => $myRank,
            ],
        ]);
    }

    public function getRanking(Request $request)
    {
        try {
            $userType = $request->query('userType', 'cast');
            $timePeriod = $request->query('timePeriod', 'current');
            $category = $request->query('category', 'gift');
            $area = $request->query('area', '全国');

            // Validate inputs
            if (!in_array($userType, ['cast', 'guest'])) {
                return response()->json(['error' => 'Invalid user type'], 400);
            }

            if (!in_array($category, ['gift', 'reservation'])) {
                return response()->json(['error' => 'Invalid category'], 400);
            }
            
            // Create cache key for this specific ranking
            $cacheKey = "ranking_{$userType}_{$timePeriod}_{$category}_{$area}";
            
            // Try to get from cache first (cache for 5 minutes)
            $ranking = Cache::remember($cacheKey, 300, function () use ($userType, $timePeriod, $category, $area) {
                return $this->calculateRanking($userType, $timePeriod, $category, $area);
            });

            return response()->json(['data' => $ranking]);
        } catch (\Exception $e) {
            Log::error('Ranking calculation error: ' . $e->getMessage(), [
                'userType' => $request->query('userType'),
                'timePeriod' => $request->query('timePeriod'),
                'category' => $request->query('category'),
                'area' => $request->query('area'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Failed to calculate ranking'], 500);
        }
    }

    private function calculateRanking($userType, $timePeriod, $category, $area)
    {
        $dateRange = $this->getDateRange($timePeriod);

        if ($category === 'gift') {
            return $this->getGiftRanking($userType, $dateRange, $area);
        } else {
            return $this->getReservationRanking($userType, $dateRange, $area);
        }
    }

    private function getDateRange($timePeriod)
    {
        $now = Carbon::now();
        
        switch ($timePeriod) {
            case 'current':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth()
                ];
                
            case 'yesterday':
                return [
                    'start' => $now->copy()->subDay()->startOfDay(),
                    'end' => $now->copy()->subDay()->endOfDay()
                ];
            case 'lastWeek':
                return [
                    'start' => $now->copy()->subWeek()->startOfWeek(),
                    'end' => $now->copy()->subWeek()->endOfWeek()
                ];
            case 'lastMonth':
                return [
                    'start' => $now->copy()->subMonth()->startOfMonth(),
                    'end' => $now->copy()->subMonth()->endOfMonth()
                ];
            case 'allTime':
                return [
                    'start' => Carbon::create(2020, 1, 1), // Arbitrary start date
                    'end' => $now
                ];
            default:
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth()
                ];
        }
    }

    /**
     * Calculate night time bonus points (4000 points for activities after 12 AM)
     */
    private function calculateNightTimeBonus($createdAt)
    {
        $hour = Carbon::parse($createdAt)->hour;
        return ($hour >= 0 && $hour < 6) ? 4000 : 0; // 12 AM to 6 AM
    }

    private function getGiftRanking($userType, $dateRange, $area)
    {
        if ($userType === 'cast') {
            // Rank casts by total gift points from point_transactions (type = 'gift')
            return DB::table('point_transactions as pt')
                ->join('casts as c', 'c.id', '=', 'pt.cast_id')
                ->select([
                    'c.id',
                    DB::raw('c.nickname as name'),
                    'c.avatar',
                    DB::raw('COALESCE(SUM(pt.amount), 0) as total_points'),
                    DB::raw('COUNT(pt.id) as gift_count'),
                ])
                ->where('pt.type', 'gift')
                ->whereNotNull('pt.cast_id')
                ->whereBetween('pt.created_at', [$dateRange['start'], $dateRange['end']])
                ->when($area !== '全国', function ($query) use ($area) {
                    return $query->where('c.residence', 'LIKE', "%{$area}%");
                })
                ->groupBy('c.id', 'c.nickname', 'c.avatar')
                ->having('total_points', '>', 0)
                ->orderByDesc('total_points')
                ->orderBy('gift_count', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'avatar' => $item->avatar,
                        'points' => (int) $item->total_points,
                        'gift_count' => (int) $item->gift_count,
                    ];
                });
        } else {
            // Rank guests by total gift points from point_transactions (type = 'gift')
            // Prefer reservation guest when available, otherwise fallback to pt.guest_id
            return DB::table('point_transactions as pt')
                ->leftJoin('reservations as r', 'r.id', '=', 'pt.reservation_id')
                ->leftJoin('guests as gr', 'gr.id', '=', 'r.guest_id')
                ->leftJoin('guests as gp', 'gp.id', '=', 'pt.guest_id')
                ->where('pt.type', 'gift')
                ->whereBetween('pt.created_at', [$dateRange['start'], $dateRange['end']])
                ->select([
                    DB::raw('COALESCE(gr.id, gp.id) as id'),
                    DB::raw('COALESCE(gr.nickname, gp.nickname) as name'),
                    DB::raw('COALESCE(gr.avatar, gp.avatar) as avatar'),
                    DB::raw('COALESCE(SUM(pt.amount), 0) as total_points'),
                    DB::raw('COUNT(pt.id) as gift_count'),
                ])
                ->when($area !== '全国', function ($query) use ($area) {
                    return $query->whereRaw('COALESCE(gr.residence, gp.residence) LIKE ?', ['%' . $area . '%']);
                })
                ->groupBy(
                    DB::raw('COALESCE(gr.id, gp.id)'),
                    DB::raw('COALESCE(gr.nickname, gp.nickname)'),
                    DB::raw('COALESCE(gr.avatar, gp.avatar)')
                )
                ->having('total_points', '>', 0)
                ->orderByDesc('total_points')
                ->orderBy('gift_count', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'avatar' => $item->avatar,
                        'points' => (int) $item->total_points,
                        'gift_count' => (int) $item->gift_count,
                    ];
                });
        }
    }

    private function getReservationRanking($userType, $dateRange, $area)
    {
        if ($userType === 'cast') {
            // Rank casts by total transfer points from point_transactions (type = 'transfer')
            return DB::table('point_transactions as pt')
                ->join('casts as c', 'c.id', '=', 'pt.cast_id')
                ->select([
                    'c.id',
                    DB::raw('c.nickname as name'),
                    'c.avatar',
                    DB::raw('COALESCE(SUM(pt.amount), 0) as total_points'),
                    DB::raw('COUNT(pt.id) as reservation_count'),
                ])
                ->where('pt.type', 'transfer')
                ->whereNotNull('pt.cast_id')
                ->whereBetween('pt.created_at', [$dateRange['start'], $dateRange['end']])
                ->when($area !== '全国', function ($query) use ($area) {
                    return $query->where('c.residence', 'LIKE', "%{$area}%");
                })
                ->groupBy('c.id', 'c.nickname', 'c.avatar')
                ->having('total_points', '>', 0)
                ->orderByDesc('total_points')
                ->orderBy('reservation_count', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'avatar' => $item->avatar,
                        'points' => (int) $item->total_points,
                        'reservation_count' => (int) $item->reservation_count,
                    ];
                });
        } else {
            // Rank guests by total transfer points from point_transactions (type = 'transfer')
            // Prefer reservation guest when available, otherwise fallback to pt.guest_id
            return DB::table('point_transactions as pt')
                ->leftJoin('reservations as r', 'r.id', '=', 'pt.reservation_id')
                ->leftJoin('guests as gr', 'gr.id', '=', 'r.guest_id')
                ->leftJoin('guests as gp', 'gp.id', '=', 'pt.guest_id')
                ->where('pt.type', 'transfer')
                ->whereBetween('pt.created_at', [$dateRange['start'], $dateRange['end']])
                ->select([
                    DB::raw('COALESCE(gr.id, gp.id) as id'),
                    DB::raw('COALESCE(gr.nickname, gp.nickname) as name'),
                    DB::raw('COALESCE(gr.avatar, gp.avatar) as avatar'),
                    DB::raw('COALESCE(SUM(pt.amount), 0) as total_points'),
                    DB::raw('COUNT(pt.id) as reservation_count'),
                ])
                ->when($area !== '全国', function ($query) use ($area) {
                    return $query->whereRaw('COALESCE(gr.residence, gp.residence) LIKE ?', ['%' . $area . '%']);
                })
                ->groupBy(
                    DB::raw('COALESCE(gr.id, gp.id)'),
                    DB::raw('COALESCE(gr.nickname, gp.nickname)'),
                    DB::raw('COALESCE(gr.avatar, gp.avatar)')
                )
                ->having('total_points', '>', 0)
                ->orderByDesc('total_points')
                ->orderBy('reservation_count', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'avatar' => $item->avatar,
                        'points' => (int) $item->total_points,
                        'reservation_count' => (int) $item->reservation_count,
                    ];
                });
        }
    }

    // Method to clear ranking cache (call this when data changes)
    public function clearRankingCache()
    {
        try {
            Cache::flush();
            return response()->json(['message' => 'Ranking cache cleared successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to clear ranking cache: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to clear cache'], 500);
        }
    }

    // Method to recalculate rankings for a specific period and region
    public function recalculateRankings(Request $request)
    {
        try {
            $period = $request->input('period', 'monthly');
            $region = $request->input('region', '全国');
            $category = $request->input('category', 'gift');

            // Map frontend period names to backend period names
            $periodMapping = [
                'current' => 'monthly',
                'yesterday' => 'daily',
                'lastWeek' => 'weekly',
                'lastMonth' => 'monthly',
                'allTime' => 'period'
            ];

            $backendPeriod = $periodMapping[$period] ?? $period;

            $rankingService = new \App\Services\RankingService();
            $rankingService->calculateRankings($backendPeriod, $region, $category);

            // Clear cache for this specific ranking
            $cacheKey = "ranking_cast_{$period}_{$category}_{$region}";
            Cache::forget($cacheKey);
            $cacheKey = "ranking_guest_{$period}_{$category}_{$region}";
            Cache::forget($cacheKey);

            return response()->json([
                'message' => "Rankings recalculated successfully for {$period} period in {$region}",
                'period' => $period,
                'region' => $region,
                'category' => $category
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to recalculate rankings: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to recalculate rankings'], 500);
        }
    }

    // Method to recalculate all rankings
    public function recalculateAllRankings()
    {
        try {
            $rankingService = new \App\Services\RankingService();
            $rankingService->recalculateAllRankings();

            // Clear all ranking cache
            Cache::flush();

            return response()->json([
                'message' => 'All rankings recalculated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to recalculate all rankings: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to recalculate all rankings'], 500);
        }
    }
} 