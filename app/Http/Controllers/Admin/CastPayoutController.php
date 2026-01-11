<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CastPayout;
use App\Services\CastPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CastPayoutController extends Controller
{
    public function __construct(private CastPayoutService $castPayoutService)
    {
    }

    /**
     * Display a listing of cast payouts.
     */
    public function index(Request $request): Response
    {
        $query = CastPayout::with(['cast', 'payment']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhereHas('cast', function($castQuery) use ($search) {
                      $castQuery->where('nickname', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('id', 'like', "%{$search}%");
                  })
                  ->orWhereHas('payment', function($paymentQuery) use ($search) {
                      $paymentQuery->where('stripe_payout_id', 'like', "%{$search}%")
                                   ->orWhereJsonContains('metadata->stripe_transfer_id', $search)
                                   ->orWhereJsonContains('metadata->stripe_payout_id', $search);
                  })
                  ->orWhereJsonContains('metadata->stripe_transfer_id', $search)
                  ->orWhereJsonContains('metadata->stripe_payout_id', $search);
            });
        }

        // Status filter
        if ($request->has('status') && $request->get('status') !== 'all') {
            $query->where('status', $request->get('status'));
        }

        // Type filter
        if ($request->has('type') && $request->get('type') !== 'all') {
            $query->where('type', $request->get('type'));
        }

        // Cast filter
        if ($request->has('cast_id') && $request->get('cast_id')) {
            $query->where('cast_id', $request->get('cast_id'));
        }

        // Date range filters
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->get('date_to') . ' 23:59:59');
        }

        // Scheduled date filter
        if ($request->filled('scheduled_date_from')) {
            $query->where('scheduled_payout_date', '>=', $request->get('scheduled_date_from'));
        }
        if ($request->filled('scheduled_date_to')) {
            $query->where('scheduled_payout_date', '<=', $request->get('scheduled_date_to'));
        }

        $perPage = (int) $request->input('per_page', 20);
        $payouts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform data for frontend
        $payouts->getCollection()->transform(function ($payout) {
            return [
                'id' => $payout->id,
                'cast_id' => $payout->cast_id,
                'cast_name' => $payout->cast ? ($payout->cast->nickname ?: $payout->cast->phone ?: "キャスト{$payout->cast_id}") : "不明",
                'type' => $payout->type,
                'closing_month' => $payout->closing_month,
                'period_start' => $payout->period_start?->format('Y-m-d'),
                'period_end' => $payout->period_end?->format('Y-m-d'),
                'total_points' => $payout->total_points,
                'conversion_rate' => $payout->conversion_rate,
                'redemption_rate' => $payout->conversion_rate, // conversion_rate now stores redemption rate
                'gross_amount_yen' => $payout->gross_amount_yen,
                'fee_rate' => $payout->fee_rate, // Kept for backward compatibility
                'fee_amount_yen' => $payout->fee_amount_yen,
                'net_amount_yen' => $payout->net_amount_yen,
                'transaction_count' => $payout->transaction_count,
                'status' => $payout->status,
                'scheduled_payout_date' => $payout->scheduled_payout_date?->format('Y-m-d'),
                'paid_at' => $payout->paid_at?->format('Y-m-d H:i:s'),
                'created_at' => $payout->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $payout->updated_at->format('Y-m-d H:i:s'),
                'payment_id' => $payout->payment_id,
                'stripe_transfer_id' => $payout->payment?->metadata['stripe_transfer_id'] ?? null,
                'stripe_payout_id' => $payout->payment?->stripe_payout_id,
                'metadata' => $payout->metadata,
            ];
        });

        return Inertia::render('admin/cast-payouts/index', [
            'payouts' => $payouts,
            'filters' => $request->only([
                'search',
                'status',
                'type',
                'cast_id',
                'date_from',
                'date_to',
                'scheduled_date_from',
                'scheduled_date_to',
                'per_page'
            ])
        ]);
    }

    /**
     * Display the specified payout.
     */
    public function show(CastPayout $castPayout): Response
    {
        $castPayout->load(['cast', 'payment', 'pointTransactions']);

        $payoutData = [
            'id' => $castPayout->id,
            'cast_id' => $castPayout->cast_id,
            'cast' => $castPayout->cast ? [
                'id' => $castPayout->cast->id,
                'nickname' => $castPayout->cast->nickname,
                'phone' => $castPayout->cast->phone,
                'line_id' => $castPayout->cast->line_id,
            ] : null,
            'type' => $castPayout->type,
            'closing_month' => $castPayout->closing_month,
            'period_start' => $castPayout->period_start?->format('Y-m-d'),
            'period_end' => $castPayout->period_end?->format('Y-m-d'),
            'total_points' => $castPayout->total_points,
            'conversion_rate' => $castPayout->conversion_rate,
            'redemption_rate' => $castPayout->conversion_rate, // conversion_rate now stores redemption rate
            'gross_amount_yen' => $castPayout->gross_amount_yen,
            'fee_rate' => $castPayout->fee_rate, // Kept for backward compatibility
            'fee_amount_yen' => $castPayout->fee_amount_yen,
            'net_amount_yen' => $castPayout->net_amount_yen,
            'transaction_count' => $castPayout->transaction_count,
            'status' => $castPayout->status,
            'scheduled_payout_date' => $castPayout->scheduled_payout_date?->format('Y-m-d'),
            'paid_at' => $castPayout->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $castPayout->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $castPayout->updated_at->format('Y-m-d H:i:s'),
            'payment' => $castPayout->payment ? [
                'id' => $castPayout->payment->id,
                'status' => $castPayout->payment->status,
                'stripe_transfer_id' => $castPayout->payment->metadata['stripe_transfer_id'] ?? null,
                'stripe_payout_id' => $castPayout->payment->stripe_payout_id,
                'stripe_connect_account_id' => $castPayout->payment->stripe_connect_account_id,
                'metadata' => $castPayout->payment->metadata,
                'created_at' => $castPayout->payment->created_at->format('Y-m-d H:i:s'),
            ] : null,
            'point_transactions' => $castPayout->pointTransactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                ];
            }),
            'metadata' => $castPayout->metadata,
        ];

        return Inertia::render('admin/cast-payouts/show', [
            'payout' => $payoutData
        ]);
    }

    /**
     * Retry a failed payout.
     */
    public function retry(CastPayout $castPayout)
    {
        if ($castPayout->status !== CastPayout::STATUS_FAILED) {
            return redirect()->back()
                ->with('error', '失敗した振込のみ再試行できます。');
        }

        try {
            $this->castPayoutService->retryPayout($castPayout);

            Log::info('Admin retried payout', [
                'payout_id' => $castPayout->id,
                'cast_id' => $castPayout->cast_id,
            ]);

            return redirect()->back()
                ->with('success', '振込の再試行を開始しました。');
        } catch (\Exception $e) {
            Log::error('Failed to retry payout', [
                'payout_id' => $castPayout->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', '再試行に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a pending or scheduled payout.
     */
    public function cancel(CastPayout $castPayout)
    {
        if (!in_array($castPayout->status, [CastPayout::STATUS_PENDING, CastPayout::STATUS_SCHEDULED])) {
            return redirect()->back()
                ->with('error', '保留中または予定済みの振込のみキャンセルできます。');
        }

        try {
            $this->castPayoutService->cancelPayout($castPayout);

            Log::info('Admin cancelled payout', [
                'payout_id' => $castPayout->id,
                'cast_id' => $castPayout->cast_id,
            ]);

            return redirect()->back()
                ->with('success', '振込をキャンセルしました。');
        } catch (\Exception $e) {
            Log::error('Failed to cancel payout', [
                'payout_id' => $castPayout->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'キャンセルに失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * Manually mark a payout as paid.
     */
    public function markPaid(Request $request, CastPayout $castPayout)
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        if ($castPayout->status === CastPayout::STATUS_PAID) {
            return redirect()->back()
                ->with('error', 'この振込は既に支払済みです。');
        }

        try {
            $this->castPayoutService->finalizePayout($castPayout, $castPayout->payment, $validated['note'] ?? null);

            Log::info('Admin manually marked payout as paid', [
                'payout_id' => $castPayout->id,
                'cast_id' => $castPayout->cast_id,
                'note' => $validated['note'] ?? null,
            ]);

            return redirect()->back()
                ->with('success', '振込を支払済みとしてマークしました。');
        } catch (\Exception $e) {
            Log::error('Failed to mark payout as paid', [
                'payout_id' => $castPayout->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'マークに失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * Approve a pending instant payout request.
     */
    public function approve(Request $request, CastPayout $castPayout)
    {
        if ($castPayout->status !== CastPayout::STATUS_PENDING_APPROVAL) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => '承認待ちの即時振込のみ承認できます。',
                ], 400);
            }
            return redirect()->back()
                ->with('error', '承認待ちの即時振込のみ承認できます。');
        }

        try {
            $this->castPayoutService->approveInstantPayout($castPayout);

            Log::info('Admin approved instant payout', [
                'payout_id' => $castPayout->id,
                'cast_id' => $castPayout->cast_id,
                'admin_id' => auth()->id(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => '即時振込を承認し、処理を開始しました。',
                ]);
            }

            return redirect()->back()
                ->with('success', '即時振込を承認し、処理を開始しました。');
        } catch (\Exception $e) {
            Log::error('Failed to approve instant payout', [
                'payout_id' => $castPayout->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => '承認に失敗しました: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', '承認に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * Reject a pending instant payout request.
     */
    public function reject(Request $request, CastPayout $castPayout)
    {
        if ($castPayout->status !== CastPayout::STATUS_PENDING_APPROVAL) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => '承認待ちの即時振込のみ却下できます。',
                ], 400);
            }
            return redirect()->back()
                ->with('error', '承認待ちの即時振込のみ却下できます。');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->castPayoutService->rejectInstantPayout($castPayout, $validated['reason'] ?? null);

            Log::info('Admin rejected instant payout', [
                'payout_id' => $castPayout->id,
                'cast_id' => $castPayout->cast_id,
                'admin_id' => auth()->id(),
                'reason' => $validated['reason'] ?? null,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => '即時振込を却下しました。',
                ]);
            }

            return redirect()->back()
                ->with('success', '即時振込を却下しました。');
        } catch (\Exception $e) {
            Log::error('Failed to reject instant payout', [
                'payout_id' => $castPayout->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => '却下に失敗しました: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', '却下に失敗しました: ' . $e->getMessage());
        }
    }
}


