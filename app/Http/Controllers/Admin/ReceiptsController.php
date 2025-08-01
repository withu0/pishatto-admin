<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Guest;
use App\Models\Cast;
use Illuminate\Http\Request;

class ReceiptsController extends Controller
{
    public function getReceiptsData()
    {
        $receipts = Payment::latest()
            ->get()
            ->map(function ($payment) {
                $user = null;
                if ($payment->user_type === 'guest' && $payment->guest) {
                    $user = $payment->guest->nickname ?? $payment->guest->phone;
                } elseif ($payment->user_type === 'cast' && $payment->cast) {
                    $user = $payment->cast->nickname ?? $payment->cast->phone;
                }

                return [
                    'id' => $payment->id,
                    'user' => $user ?? 'Unknown',
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'date' => $payment->created_at->format('Y-m-d H:i'),
                ];
            });

        return response()->json(['receipts' => $receipts]);
    }
} 