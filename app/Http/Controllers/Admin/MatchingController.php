<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Guest;
use App\Models\Cast;
use Illuminate\Http\Request;

class MatchingController extends Controller
{
    public function getMatchingData()
    {
        $matchings = Reservation::whereNotNull('cast_id')
            ->latest()
            ->get()
            ->map(function ($reservation) {
                $guest = null;
                $cast = null;
                
                if ($reservation->guest_id && $reservation->guest) {
                    $guest = $reservation->guest->nickname ?? $reservation->guest->phone;
                }
                if ($reservation->cast_id && $reservation->cast) {
                    $cast = $reservation->cast->nickname ?? $reservation->cast->phone;
                }

                return [
                    'id' => $reservation->id,
                    'guest' => $guest ?? 'Unknown',
                    'call' => 'コール' . $reservation->id, // You might want to add a call field to reservations
                    'cast' => $cast ?? 'Unknown',
                    'status' => $this->getStatusText($reservation->status),
                    'date' => $reservation->created_at ? $reservation->created_at->format('Y-m-d') : 'Unknown',
                ];
            });

        return response()->json(['matchings' => $matchings]);
    }

    private function getStatusText($status)
    {
        $statusMap = [
            'pending' => '未選定',
            'matched' => '選定済',
            'completed' => '完了',
            'cancelled' => 'キャンセル',
        ];

        return $statusMap[$status] ?? $status;
    }
} 