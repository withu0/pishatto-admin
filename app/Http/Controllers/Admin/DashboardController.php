<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Cast;
use App\Models\PointTransaction;
use App\Models\Message;
use App\Models\GuestGift;
use App\Models\Ranking;
use App\Models\Reservation;
use App\Models\Notification;
use App\Models\Gift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getDashboardData()
    {
        // Get counts
        $guestCount = Guest::count();
        $castCount = Cast::count();
        $messageCount = Message::count();
        $giftCount = GuestGift::count();
        $rankingCount = Ranking::count();
        $matchingCount = Reservation::where('status', 'matched')->count();
        $notificationCount = Notification::count();
        
        // Identity verification counts
        $pendingVerifications = Guest::where('identity_verification_completed', 'pending')
                                   ->whereNotNull('identity_verification')->count();
        $approvedVerifications = Guest::where('identity_verification_completed', 'success')->count();
        $rejectedVerifications = Guest::where('identity_verification_completed', 'failed')->count();

        // Calculate total sales from point transactions
        $totalSales = PointTransaction::where('type', 'purchase')
            ->sum('amount');

        // Get gift distribution for pie chart
        $giftDistribution = Gift::select('gifts.name', DB::raw('COUNT(guest_gifts.id) as value'))
            ->leftJoin('guest_gifts', 'gifts.id', '=', 'guest_gifts.gift_id')
            ->groupBy('gifts.id', 'gifts.name')
            ->having('value', '>', 0)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'value' => $item->value,
                    'color' => $this->getRandomColor()
                ];
            });

        // Get recent updates
        $recentUpdates = $this->getRecentUpdates();

        return response()->json([
            'summary' => [
                'guests' => $guestCount,
                'casts' => $castCount,
                'sales' => $totalSales,
                'messages' => $messageCount,
                'gifts' => $giftCount,
                'rankings' => $rankingCount,
                'matching' => $matchingCount,
                'notifications' => $notificationCount,
                'pendingVerifications' => $pendingVerifications,
                'approvedVerifications' => $approvedVerifications,
                'rejectedVerifications' => $rejectedVerifications,
            ],
            'giftDistribution' => $giftDistribution,
            'recentUpdates' => $recentUpdates,
        ]);
    }

    private function getRecentUpdates()
    {
        $updates = [];

        // Get recent guest registrations
        $recentGuests = Guest::latest()->take(3)->get();
        foreach ($recentGuests as $guest) {
            $updates[] = [
                'user' => $guest->nickname ?? $guest->phone,
                'type' => 'ゲスト',
                'action' => '新規登録',
                'time' => $guest->created_at->diffForHumans(),
            ];
        }

        // Get recent cast profile updates
        $recentCasts = Cast::latest()->take(3)->get();
        foreach ($recentCasts as $cast) {
            $updates[] = [
                'user' => $cast->nickname ?? $cast->phone,
                'type' => 'キャスト',
                'action' => 'プロフィール更新',
                'time' => $cast->updated_at->diffForHumans(),
            ];
        }

        // Get recent sales
        $recentSales = PointTransaction::where('type', 'purchase')
            ->latest()
            ->take(3)
            ->get();
        
        foreach ($recentSales as $sale) {
            if ($sale->guest_id && $sale->guest) {
                $updates[] = [
                    'user' => $sale->guest->nickname ?? $sale->guest->phone,
                    'type' => 'ゲスト',
                    'action' => '売上追加',
                    'time' => $sale->created_at->diffForHumans(),
                ];
            }
        }

        // Sort by time and take the most recent 10
        usort($updates, function ($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return array_slice($updates, 0, 10);
    }

    private function getRandomColor()
    {
        $colors = [
            '#a78bfa', '#f472b6', '#facc15', '#34d399', '#60a5fa',
            '#f87171', '#fb923c', '#a3e635', '#fbbf24', '#f97316'
        ];
        return $colors[array_rand($colors)];
    }
} 