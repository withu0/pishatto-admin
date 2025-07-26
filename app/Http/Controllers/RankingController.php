<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RankingService;
use App\Models\Cast;
use App\Models\Guest;
use App\Models\Like;
use App\Models\Gift;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    protected $rankingService;

    public function __construct(RankingService $rankingService)
    {
        $this->rankingService = $rankingService;
    }

    public function getRanking(Request $request)
    {
        $userType = $request->query('userType', 'cast');
        $timePeriod = $request->query('timePeriod', 'current');
        $category = $request->query('category', 'gift');
        $area = $request->query('area', '全国');

        // Map frontend time periods to backend periods
        $periodMap = [
            'current' => 'monthly',
            'yesterday' => 'daily',
            'lastWeek' => 'weekly',
            'lastMonth' => 'monthly',
            'allTime' => 'period'
        ];

        $period = $periodMap[$timePeriod] ?? 'monthly';

        // Get rankings from the service
        $rankings = $this->rankingService->getRankings($userType, $period, $area, 50, $category);

        return response()->json([
            'type' => $userType,
            'data' => $rankings,
            'period' => $period,
            'area' => $area,
            'category' => $category
        ]);
    }

    /**
     * Calculate and update rankings (admin endpoint)
     */
    public function calculateRankings(Request $request)
    {
        $period = $request->input('period', 'daily');
        $region = $request->input('region', '全国');
        $category = $request->input('category', 'gift');

        try {
            $this->rankingService->calculateRankings($period, $region, $category);
            
            return response()->json([
                'success' => true,
                'message' => "Rankings calculated successfully for {$period} period in {$region} for {$category}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating rankings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate all rankings (admin endpoint)
     */
    public function recalculateAllRankings()
    {
        try {
            $this->rankingService->recalculateAllRankings();
            
            return response()->json([
                'success' => true,
                'message' => 'All rankings recalculated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recalculating rankings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ranking statistics (admin endpoint)
     */
    public function getRankingStats()
    {
        $stats = [
            'total_rankings' => DB::table('rankings')->count(),
            'cast_rankings' => DB::table('rankings')->where('type', 'cast')->count(),
            'guest_rankings' => DB::table('rankings')->where('type', 'guest')->count(),
            'periods' => [
                'daily' => DB::table('rankings')->where('period', 'daily')->count(),
                'weekly' => DB::table('rankings')->where('period', 'weekly')->count(),
                'monthly' => DB::table('rankings')->where('period', 'monthly')->count(),
                'period' => DB::table('rankings')->where('period', 'period')->count(),
            ],
            'categories' => [
                'gift' => DB::table('rankings')->where('category', 'gift')->count(),
                'reservation' => DB::table('rankings')->where('category', 'reservation')->count(),
            ],
            'regions' => DB::table('rankings')
                ->select('region', DB::raw('count(*) as count'))
                ->groupBy('region')
                ->get()
        ];

        return response()->json($stats);
    }

    /**
     * Legacy method for backward compatibility
     */
    public function getLegacyRanking(Request $request)
    {
        $userType = $request->query('userType', 'cast');
        $timePeriod = $request->query('timePeriod', 'current');
        $category = $request->query('category', 'gift');
        $area = $request->query('area', '全国');

        // Example: filter by time period (implement actual logic as needed)
        $dateRange = null;
        switch ($timePeriod) {
            case 'current':
                $dateRange = [now()->startOfMonth(), now()->endOfMonth()];
                break;
            case 'yesterday':
                $dateRange = [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()];
                break;
            case 'lastWeek':
                $dateRange = [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()];
                break;
            case 'lastMonth':
                $dateRange = [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()];
                break;
            case 'allTime':
            default:
                $dateRange = null;
        }

        if ($userType === 'cast') {
            $query = Cast::query();
            if ($area !== '全国') {
                $query->where('residence', 'like', "%{$area}%");
            }
            // Example: sort by likes count in the period
            $query->withCount(['likes' => function ($q) use ($dateRange) {
                if ($dateRange) {
                    $q->whereBetween('created_at', $dateRange);
                }
            }]);
            $query->orderByDesc('likes_count');
            $casts = $query->get();
            return response()->json(['type' => 'cast', 'data' => $casts]);
        } else {
            $query = Guest::query();
            if ($area !== '全国') {
                $query->where('residence', 'like', "%{$area}%");
            }
            // Example: sort by gifts count in the period
            $query->withCount(['gifts' => function ($q) use ($dateRange) {
                if ($dateRange) {
                    $q->whereBetween('created_at', $dateRange);
                }
            }]);
            $query->orderByDesc('gifts_count');
            $guests = $query->get();
            return response()->json(['type' => 'guest', 'data' => $guests]);
        }
    }
} 