<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ranking;
use App\Models\Cast;
use App\Models\Guest;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RankingController extends Controller
{
    public function index(Request $request)
    {
        $userType = $request->query('userType', 'all');
        $period = $request->query('period', 'allTime');
        $category = $request->query('category', 'reservation');
        $region = $request->query('region', '全国');
        $search = $request->query('search', '');

        $rankings = $this->getRankingData($userType, $period, $category, $region, $search);

        return response()->json([
            'data' => $rankings,
            'filters' => [
                'userType' => $userType,
                'period' => $period,
                'category' => $category,
                'region' => $region,
                'search' => $search
            ]
        ]);
    }

    private function getRankingData($userType, $period, $category, $region, $search)
    {
        $dateRange = $this->getDateRange($period);
        
        if ($category === 'reservation') {
            return $this->getReservationRankings($userType, $dateRange, $region, $search);
        } else {
            return $this->getGiftRankings($userType, $dateRange, $region, $search);
        }
    }

    private function getReservationRankings($userType, $dateRange, $region, $search)
    {
        $query = DB::table('reservations as r')
            ->join('guests as g', 'r.guest_id', '=', 'g.id')
            ->join('casts as c', 'r.cast_id', '=', 'c.id')
            ->select([
                'r.id',
                'r.guest_id',
                'r.cast_id',
                'g.nickname as guest_name',
                'c.nickname as cast_name',
                'g.residence as guest_residence',
                'c.residence as cast_residence',
                'r.points_earned',
                'r.created_at',
                'r.scheduled_at'
            ])
            ->whereBetween('r.created_at', [$dateRange['start'], $dateRange['end']]);

        // Apply region filter
        if ($region !== '全国') {
            $query->where(function($q) use ($region) {
                $q->where('g.residence', 'LIKE', "%{$region}%")
                  ->orWhere('c.residence', 'LIKE', "%{$region}%");
            });
        }

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('g.nickname', 'LIKE', "%{$search}%")
                  ->orWhere('c.nickname', 'LIKE', "%{$search}%");
            });
        }

        $reservations = $query->get();

        // Group and calculate rankings
        $rankings = [];
        
        if ($userType === 'all' || $userType === 'guest') {
            $guestRankings = $this->calculateGuestRankings($reservations);
            $rankings = array_merge($rankings, $guestRankings);
        }
        
        if ($userType === 'all' || $userType === 'cast') {
            $castRankings = $this->calculateCastRankings($reservations);
            $rankings = array_merge($rankings, $castRankings);
        }

        // Sort by points descending
        usort($rankings, function($a, $b) {
            return $b['points'] <=> $a['points'];
        });

        // Add rank numbers
        foreach ($rankings as $index => $ranking) {
            $rankings[$index]['rank'] = $index + 1;
        }

        return $rankings;
    }

    private function calculateGuestRankings($reservations)
    {
        $guestPoints = [];
        
        foreach ($reservations as $reservation) {
            $guestId = $reservation->guest_id;
            $points = $reservation->points_earned ?? 0;
            
            if (!isset($guestPoints[$guestId])) {
                $guestPoints[$guestId] = [
                    'id' => $guestId,
                    'name' => $reservation->guest_name,
                    'type' => 'ゲスト',
                    'points' => 0,
                    'reservation_count' => 0,
                    'residence' => $reservation->guest_residence
                ];
            }
            
            $guestPoints[$guestId]['points'] += $points;
            $guestPoints[$guestId]['reservation_count']++;
        }

        return array_values($guestPoints);
    }

    private function calculateCastRankings($reservations)
    {
        $castPoints = [];
        
        foreach ($reservations as $reservation) {
            $castId = $reservation->cast_id;
            $points = $reservation->points_earned ?? 0;
            
            if (!isset($castPoints[$castId])) {
                $castPoints[$castId] = [
                    'id' => $castId,
                    'name' => $reservation->cast_name,
                    'type' => 'キャスト',
                    'points' => 0,
                    'reservation_count' => 0,
                    'residence' => $reservation->cast_residence
                ];
            }
            
            $castPoints[$castId]['points'] += $points;
            $castPoints[$castId]['reservation_count']++;
        }

        return array_values($castPoints);
    }

    private function getGiftRankings($userType, $dateRange, $region, $search)
    {
        $query = DB::table('guest_gifts as gg')
            ->join('guests as g', 'gg.sender_guest_id', '=', 'g.id')
            ->join('casts as c', 'gg.receiver_cast_id', '=', 'c.id')
            ->join('gifts as gift', 'gg.gift_id', '=', 'gift.id')
            ->select([
                'gg.id',
                'gg.sender_guest_id',
                'gg.receiver_cast_id',
                'g.nickname as guest_name',
                'c.nickname as cast_name',
                'g.residence as guest_residence',
                'c.residence as cast_residence',
                'gift.points',
                'gg.created_at'
            ])
            ->whereBetween('gg.created_at', [$dateRange['start'], $dateRange['end']]);

        // Apply region filter
        if ($region !== '全国') {
            $query->where(function($q) use ($region) {
                $q->where('g.residence', 'LIKE', "%{$region}%")
                  ->orWhere('c.residence', 'LIKE', "%{$region}%");
            });
        }

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('g.nickname', 'LIKE', "%{$search}%")
                  ->orWhere('c.nickname', 'LIKE', "%{$search}%");
            });
        }

        $gifts = $query->get();

        // Group and calculate rankings
        $rankings = [];
        
        if ($userType === 'all' || $userType === 'guest') {
            $guestRankings = $this->calculateGuestGiftRankings($gifts);
            $rankings = array_merge($rankings, $guestRankings);
        }
        
        if ($userType === 'all' || $userType === 'cast') {
            $castRankings = $this->calculateCastGiftRankings($gifts);
            $rankings = array_merge($rankings, $castRankings);
        }

        // Sort by points descending
        usort($rankings, function($a, $b) {
            return $b['points'] <=> $a['points'];
        });

        // Add rank numbers
        foreach ($rankings as $index => $ranking) {
            $rankings[$index]['rank'] = $index + 1;
        }

        return $rankings;
    }

    private function calculateGuestGiftRankings($gifts)
    {
        $guestPoints = [];
        
        foreach ($gifts as $gift) {
            $guestId = $gift->sender_guest_id;
            $points = $gift->points ?? 0;
            
            if (!isset($guestPoints[$guestId])) {
                $guestPoints[$guestId] = [
                    'id' => $guestId,
                    'name' => $gift->guest_name,
                    'type' => 'ゲスト',
                    'points' => 0,
                    'gift_count' => 0,
                    'residence' => $gift->guest_residence
                ];
            }
            
            $guestPoints[$guestId]['points'] += $points;
            $guestPoints[$guestId]['gift_count']++;
        }

        return array_values($guestPoints);
    }

    private function calculateCastGiftRankings($gifts)
    {
        $castPoints = [];
        
        foreach ($gifts as $gift) {
            $castId = $gift->receiver_cast_id;
            $points = $gift->points ?? 0;
            
            if (!isset($castPoints[$castId])) {
                $castPoints[$castId] = [
                    'id' => $castId,
                    'name' => $gift->cast_name,
                    'type' => 'キャスト',
                    'points' => 0,
                    'gift_count' => 0,
                    'residence' => $gift->cast_residence
                ];
            }
            
            $castPoints[$castId]['points'] += $points;
            $castPoints[$castId]['gift_count']++;
        }

        return array_values($castPoints);
    }

    private function getDateRange($period)
    {
        $now = Carbon::now();
        
        switch ($period) {
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
            case 'current':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth()
                ];
            case 'allTime':
            default:
                return [
                    'start' => Carbon::create(2020, 1, 1),
                    'end' => $now
                ];
        }
    }
} 