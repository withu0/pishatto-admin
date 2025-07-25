<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cast;
use App\Models\Guest;
use App\Models\Like;
use App\Models\Gift;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function getRanking(Request $request)
    {
        $userType = $request->query('userType', 'cast');
        $timePeriod = $request->query('timePeriod', 'current');
        $category = $request->query('category', '総合');
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
                $query->where('area', $area);
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
                $query->where('area', $area);
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