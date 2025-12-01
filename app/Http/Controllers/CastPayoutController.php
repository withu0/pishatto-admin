<?php

namespace App\Http\Controllers;

use App\Models\Cast;
use App\Services\CastPayoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CastPayoutController extends Controller
{
    public function __construct(private CastPayoutService $castPayoutService)
    {
    }

    public function summary(int $castId): Response
    {
        $cast = Cast::findOrFail($castId);
        $summary = $this->castPayoutService->buildCastSummary($cast);

        return response([
            'success' => true,
            'summary' => [
                'conversion_rate' => $summary['conversion_rate'],
                'scheduled_fee_rate' => $summary['scheduled_fee_rate'],
                'instant_fee_rate' => $summary['instant_fee_rate'],
                'unsettled_points' => $summary['unsettled_points'],
                'unsettled_amount_yen' => $summary['unsettled_amount_yen'],
                'instant_available_points' => $summary['instant_available_points'],
                'instant_available_amount_yen' => $summary['instant_available_amount_yen'],
                'upcoming_payout' => $summary['upcoming_payout'],
                'recent_history' => $summary['recent_history'],
            ],
        ]);
    }

    public function requestInstant(Request $request, int $castId): Response
    {
        $data = $request->validate([
            'amount' => 'required|integer|min:1000',
            'memo' => 'nullable|string|max:200',
        ]);

        try {
            $cast = Cast::findOrFail($castId);
            $payout = $this->castPayoutService->createInstantPayout($cast, (int) $data['amount'], $data['memo'] ?? null);

            return response([
                'success' => true,
                'payout' => $payout->load('payment'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Instant payout request failed', [
                'cast_id' => $castId,
                'error' => $e->getMessage(),
            ]);

            return response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}


