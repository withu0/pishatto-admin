<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use App\Models\Guest;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function getSalesData()
    {
        $sales = PointTransaction::where('type', 'purchase')
            ->latest()
            ->get()
            ->map(function ($transaction) {
                $guest = null;
                if ($transaction->guest_id && $transaction->guest) {
                    $guest = $transaction->guest->nickname ?? $transaction->guest->phone;
                }

                return [
                    'id' => $transaction->id,
                    'guest' => $guest ?? 'Unknown',
                    'amount' => $transaction->amount,
                    'date' => $transaction->created_at ? $transaction->created_at->format('Y-m-d') : 'Unknown',
                ];
            });

        return response()->json(['sales' => $sales]);
    }
} 