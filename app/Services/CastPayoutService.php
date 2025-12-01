<?php

namespace App\Services;

use App\Models\Cast;
use App\Models\CastPayout;
use App\Models\Payment;
use App\Models\PointTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CastPayoutService
{
    public function __construct(private StripeService $stripeService)
    {
    }

    /**
     * Close the specified month (default: previous month) and create scheduled payouts.
     */
    public function closeMonthlyPeriod(?Carbon $periodEnd = null): int
    {
        $tz = config('cast_payouts.timezone', 'Asia/Tokyo');
        $periodEnd = $periodEnd
            ? $periodEnd->copy()->timezone($tz)->endOfDay()
            : now($tz)->subMonth()->endOfMonth();

        $periodStart = $periodEnd->copy()->startOfMonth();
        $closingMonth = $periodEnd->format('Y-m');

        $eligibleCastIds = PointTransaction::query()
            ->select('cast_id')
            ->whereNotNull('cast_id')
            ->whereNull('cast_payout_id')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereIn('type', $this->earnableTypes())
            ->distinct()
            ->pluck('cast_id');

        $createdCount = 0;

        foreach ($eligibleCastIds as $castId) {
            $created = DB::transaction(function () use ($castId, $periodStart, $periodEnd, $closingMonth) {
                $cast = Cast::find($castId);
                if (!$cast) {
                    return 0;
                }

                // Lock to avoid double-closing the same month
                $alreadyClosed = CastPayout::where('cast_id', $castId)
                    ->where('closing_month', $closingMonth)
                    ->where('type', CastPayout::TYPE_SCHEDULED)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyClosed) {
                    return 0;
                }

                $transactions = PointTransaction::where('cast_id', $castId)
                    ->whereNull('cast_payout_id')
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->whereIn('type', $this->earnableTypes())
                    ->lockForUpdate()
                    ->get(['id', 'amount']);

                if ($transactions->isEmpty()) {
                    return 0;
                }

                $totalPoints = (int) $transactions->sum('amount');
                if ($totalPoints <= 0) {
                    return 0;
                }

                $conversionRate = $this->conversionRate();
                $grossYen = (int) floor($totalPoints * $conversionRate);
                $feeRate = $this->scheduledFeeRate($cast->grade);
                $feeAmount = (int) floor($grossYen * $feeRate);
                $netAmount = max(0, $grossYen - $feeAmount);
                $scheduledDate = $this->calculateScheduledPayoutDate($periodEnd);

                $payout = CastPayout::create([
                    'cast_id' => $castId,
                    'type' => CastPayout::TYPE_SCHEDULED,
                    'closing_month' => $closingMonth,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'total_points' => $totalPoints,
                    'conversion_rate' => $conversionRate,
                    'gross_amount_yen' => $grossYen,
                    'fee_rate' => $feeRate,
                    'fee_amount_yen' => $feeAmount,
                    'net_amount_yen' => $netAmount,
                    'transaction_count' => $transactions->count(),
                    'scheduled_payout_date' => $scheduledDate->toDateString(),
                    'status' => CastPayout::STATUS_SCHEDULED,
                    'metadata' => [
                        'source' => 'auto-close',
                    ],
                ]);

                PointTransaction::whereIn('id', $transactions->pluck('id'))
                    ->update(['cast_payout_id' => $payout->id]);

                Log::info('Cast payout scheduled', [
                    'cast_id' => $castId,
                    'closing_month' => $closingMonth,
                    'payout_id' => $payout->id,
                    'total_points' => $totalPoints,
                    'net_amount_yen' => $netAmount,
                ]);

                return 1;
            });

            $createdCount += $created;
        }

        return $createdCount;
    }

    /**
     * Process scheduled payouts whose due date has arrived.
     */
    public function processDuePayouts(?Carbon $runDate = null): int
    {
        $tz = config('cast_payouts.timezone', 'Asia/Tokyo');
        $date = ($runDate ?? now($tz))->toDateString();

        $duePayouts = CastPayout::with('cast')
            ->whereIn('status', [CastPayout::STATUS_SCHEDULED, CastPayout::STATUS_PENDING])
            ->whereDate('scheduled_payout_date', '<=', $date)
            ->orderBy('scheduled_payout_date')
            ->get();

        $processed = 0;

        foreach ($duePayouts as $payout) {
            $cast = $payout->cast;
            
            // Skip if Stripe Connect is not set up - leave as scheduled/pending
            if (!$cast || !$cast->stripe_connect_account_id || !$cast->payouts_enabled) {
                Log::info('Skipping payout - Stripe Connect not configured', [
                    'payout_id' => $payout->id,
                    'cast_id' => $payout->cast_id,
                ]);
                continue;
            }

            if ($this->dispatchScheduledPayout($payout)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Request an instant payout for a cast.
     */
    public function createInstantPayout(Cast $cast, int $amountYen, ?string $memo = null): CastPayout
    {
        $this->assertInstantEligibility($cast, $amountYen);

        // Require Stripe Connect account to be set up
        if (!$cast->stripe_connect_account_id || !$cast->payouts_enabled) {
            throw new \RuntimeException('Stripe Connectアカウントが設定されていません。振込設定ページでStripe Connectを設定してください。');
        }

        return DB::transaction(function () use ($cast, $amountYen, $memo) {
            $conversionRate = $this->conversionRate();
            $requiredPoints = (int) ceil($amountYen / max(0.0001, $conversionRate));

            $availableTransactions = PointTransaction::where('cast_id', $cast->id)
                ->whereNull('cast_payout_id')
                ->whereIn('type', $this->earnableTypes())
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get(['id', 'amount', 'created_at']);

            $consumed = $this->consumeTransactionsForInstantPayout($availableTransactions, $requiredPoints);

            if ($consumed['total_points'] < $requiredPoints) {
                throw new \RuntimeException('即時振込に必要なポイントが不足しています。');
            }

            $feeRate = $this->instantFeeRate($cast->grade);
            $feeAmount = (int) ceil($amountYen * $feeRate);
            $netAmount = max(0, $amountYen - $feeAmount);

            $payout = CastPayout::create([
                'cast_id' => $cast->id,
                'type' => CastPayout::TYPE_INSTANT,
                'closing_month' => now()->format('Y-m'),
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'total_points' => $consumed['total_points'],
                'conversion_rate' => $conversionRate,
                'gross_amount_yen' => $amountYen,
                'fee_rate' => $feeRate,
                'fee_amount_yen' => $feeAmount,
                'net_amount_yen' => $netAmount,
                'transaction_count' => $consumed['transaction_count'],
                'scheduled_payout_date' => now()->toDateString(),
                'status' => CastPayout::STATUS_PROCESSING,
                'metadata' => [
                    'instant_request' => true,
                    'memo' => $memo,
                ],
            ]);

            PointTransaction::whereIn('id', $consumed['transaction_ids'])
                ->update(['cast_payout_id' => $payout->id]);

            $payment = $this->createStripeOrManualPayout($cast, $payout, true);

            if (!$payment) {
                throw new \RuntimeException('Stripe Connectアカウントが設定されていません。振込設定ページでStripe Connectを設定してください。');
            }

            return $payout->fresh(['payment']);
        });
    }

    /**
     * Build payout summary for cast dashboard.
     */
    public function buildCastSummary(Cast $cast): array
    {
        $conversionRate = $this->conversionRate();
        $scheduledFeeRate = $this->scheduledFeeRate($cast->grade);
        $instantFeeRate = $this->instantFeeRate($cast->grade);
        $unsettledPoints = $this->getUnsettledPoints($cast->id);
        $availableInstantPoints = $this->getInstantAvailablePoints($cast->id);

        $upcoming = CastPayout::where('cast_id', $cast->id)
            ->where('type', CastPayout::TYPE_SCHEDULED)
            ->whereIn('status', [CastPayout::STATUS_SCHEDULED, CastPayout::STATUS_PROCESSING, CastPayout::STATUS_PENDING])
            ->orderBy('scheduled_payout_date')
            ->first();

        $history = CastPayout::where('cast_id', $cast->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'conversion_rate' => $conversionRate,
            'scheduled_fee_rate' => $scheduledFeeRate,
            'instant_fee_rate' => $instantFeeRate,
            'unsettled_points' => $unsettledPoints,
            'unsettled_amount_yen' => (int) floor($unsettledPoints * $conversionRate),
            'instant_available_points' => $availableInstantPoints,
            'instant_available_amount_yen' => (int) floor($availableInstantPoints * $conversionRate),
            'upcoming_payout' => $upcoming,
            'recent_history' => $history,
        ];
    }

    /**
     * Finalize payout when payment is marked paid.
     */
    public function finalizePayout(CastPayout $payout, ?Payment $payment = null, ?string $note = null): void
    {
        DB::transaction(function () use ($payout, $payment, $note) {
            $cast = Cast::find($payout->cast_id);
            if ($cast) {
                $cast->points = max(0, (int) $cast->points - (int) $payout->total_points);
                $cast->save();
            }

            $payout->markPaid($note);

            if ($payment && $payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }
        });
    }

    /**
     * Retry a failed payout.
     */
    public function retryPayout(CastPayout $payout): void
    {
        if ($payout->status !== CastPayout::STATUS_FAILED) {
            throw new \RuntimeException('失敗した振込のみ再試行できます。');
        }

        DB::transaction(function () use ($payout) {
            $cast = $payout->cast;
            if (!$cast) {
                throw new \RuntimeException('キャストが見つかりません。');
            }

            // Reset payout status to processing
            $payout->update([
                'status' => CastPayout::STATUS_PROCESSING,
                'metadata' => array_merge($payout->metadata ?? [], [
                    'retry_attempted_at' => now()->toISOString(),
                    'retry_count' => ($payout->metadata['retry_count'] ?? 0) + 1,
                ]),
            ]);

            // Attempt to create Stripe payout again
            $payment = $this->createStripeOrManualPayout($cast, $payout, $payout->type === CastPayout::TYPE_INSTANT);

            if (!$payment) {
                $payout->update([
                    'status' => CastPayout::STATUS_FAILED,
                    'metadata' => array_merge($payout->metadata ?? [], [
                        'retry_failed_at' => now()->toISOString(),
                    ]),
                ]);
                throw new \RuntimeException('再試行に失敗しました。Stripe Connectアカウントの設定を確認してください。');
            }
        });
    }

    /**
     * Cancel a pending or scheduled payout.
     */
    public function cancelPayout(CastPayout $payout): void
    {
        if (!in_array($payout->status, [CastPayout::STATUS_PENDING, CastPayout::STATUS_SCHEDULED])) {
            throw new \RuntimeException('保留中または予定済みの振込のみキャンセルできます。');
        }

        DB::transaction(function () use ($payout) {
            // Release point transactions back to unsettled
            PointTransaction::where('cast_payout_id', $payout->id)
                ->update(['cast_payout_id' => null]);

            // Update payout status
            $payout->update([
                'status' => CastPayout::STATUS_CANCELLED,
                'metadata' => array_merge($payout->metadata ?? [], [
                    'cancelled_at' => now()->toIso8601String(),
                ]),
            ]);

            // Cancel related payment if exists and is pending
            if ($payout->payment && $payout->payment->status === 'pending') {
                $payout->payment->update(['status' => 'canceled']);
            }
        });
    }

    private function dispatchScheduledPayout(CastPayout $payout): bool
    {
        try {
            return DB::transaction(function () use ($payout) {
                $cast = $payout->cast()->lockForUpdate()->first();
                if (!$cast) {
                    return false;
                }

                $payment = $this->createStripeOrManualPayout($cast, $payout, false);

                return (bool) $payment;
            });
        } catch (Throwable $e) {
            Log::error('Failed to dispatch scheduled payout', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function createStripeOrManualPayout(Cast $cast, CastPayout $payout, bool $instant): ?Payment
    {
        // Require Stripe Connect account - no manual fallback
        if (!$cast->stripe_connect_account_id || !$cast->payouts_enabled) {
            Log::warning('Payout skipped - Stripe Connect not configured', [
                'cast_id' => $cast->id,
                'payout_id' => $payout->id,
                'has_account_id' => (bool) $cast->stripe_connect_account_id,
                'payouts_enabled' => $cast->payouts_enabled,
            ]);

            // Update payout status to indicate it's waiting for Stripe Connect setup
            $payout->update([
                'status' => CastPayout::STATUS_PENDING,
                'metadata' => array_merge($payout->metadata ?? [], [
                    'waiting_for_stripe_connect' => true,
                    'reason' => 'Stripe Connectアカウントが設定されていません',
                ]),
            ]);

            return null;
        }

        $metadata = [
            'cast_payout_id' => $payout->id,
            'type' => $payout->type,
            'closing_month' => $payout->closing_month,
            'instant' => $instant,
        ];

        try {
            // Check platform balance before attempting transfer
            try {
                $platformBalance = $this->stripeService->getPlatformBalance();
                $availableYen = 0;
                foreach ($platformBalance['available'] ?? [] as $item) {
                    if (strtolower($item['currency'] ?? '') === 'jpy') {
                        $availableYen = (int) ($item['amount'] ?? 0);
                        break;
                    }
                }

                if ($availableYen < $payout->net_amount_yen) {
                    $shortfall = $payout->net_amount_yen - $availableYen;
                    Log::warning('Insufficient platform balance for transfer', [
                        'cast_id' => $cast->id,
                        'payout_id' => $payout->id,
                        'required' => $payout->net_amount_yen,
                        'available' => $availableYen,
                        'shortfall' => $shortfall,
                    ]);
                }
            } catch (Throwable $balanceCheckError) {
                // Log but don't fail - proceed with transfer attempt
                Log::warning('Failed to check platform balance before transfer', [
                    'error' => $balanceCheckError->getMessage(),
                ]);
            }

            // Step 1: Transfer money FROM platform account TO connected account
            $transfer = $this->stripeService->createTransfer(
                $cast->stripe_connect_account_id,
                $payout->net_amount_yen,
                'jpy',
                array_merge($metadata, [
                    'transfer_purpose' => $instant ? 'instant_payout' : 'scheduled_payout',
                    'cast_payout_id' => $payout->id,
                ])
            );

            Log::info('Transfer to connected account completed, creating payout', [
                'cast_id' => $cast->id,
                'payout_id' => $payout->id,
                'transfer_id' => $transfer['id'] ?? null,
                'amount' => $payout->net_amount_yen,
            ]);

            // Step 2: Create payout FROM connected account TO cast's bank
            $stripePayout = $this->stripeService->createPayout(
                $cast->stripe_connect_account_id,
                $payout->net_amount_yen,
                'jpy',
                array_merge($metadata, [
                    'requested_via' => $instant ? 'instant' : 'scheduled',
                    'transfer_id' => $transfer['id'] ?? null,
                ])
            );

            $payment = Payment::create([
                'user_id' => $cast->id,
                'user_type' => 'cast',
                'cast_payout_id' => $payout->id,
                'amount' => $payout->net_amount_yen,
                'payment_method' => 'payout',
                'status' => 'pending',
                'stripe_payout_id' => $stripePayout['id'] ?? null,
                'stripe_connect_account_id' => $cast->stripe_connect_account_id,
                'description' => $instant ? '即時振込' : '末締め振込',
                'metadata' => array_merge($metadata, [
                    'stripe_payout_status' => $stripePayout['status'] ?? null,
                    'stripe_transfer_id' => $transfer['id'] ?? null,
                ]),
            ]);

            $payout->update([
                'status' => CastPayout::STATUS_PROCESSING,
                'metadata' => array_merge($payout->metadata ?? [], [
                    'payment_id' => $payment->id,
                    'stripe_transfer_id' => $transfer['id'] ?? null,
                ]),
            ]);

            return $payment;
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            $isInsufficientFunds = stripos($errorMessage, 'insufficient') !== false || 
                                   stripos($errorMessage, 'available funds') !== false ||
                                   stripos($errorMessage, 'balance') !== false;

            Log::error('Stripe payout initiation failed', [
                'cast_id' => $cast->id,
                'payout_id' => $payout->id,
                'error' => $errorMessage,
                'is_insufficient_funds' => $isInsufficientFunds,
            ]);

            // Update payout status to failed
            $payout->update([
                'status' => CastPayout::STATUS_FAILED,
                'metadata' => array_merge($payout->metadata ?? [], [
                    'stripe_error' => $errorMessage,
                    'failed_at' => now()->toIso8601String(),
                ]),
            ]);

            // Provide more helpful error message for insufficient funds
            if ($isInsufficientFunds) {
                $userMessage = 'プラットフォームのStripeアカウントに十分な残高がありません。';
                if (config('app.env') === 'local' || config('app.env') === 'testing') {
                    $userMessage .= ' テストモードでは、テストカード（4000000000000077）を使用してプラットフォームアカウントに残高を追加してください。';
                } else {
                    $userMessage .= ' 管理者に連絡してプラットフォームアカウントに残高を追加してください。';
                }
                throw new \RuntimeException($userMessage);
            }

            // Re-throw the exception so the caller knows it failed
            throw new \RuntimeException('Stripe振込処理に失敗しました: ' . $errorMessage);
        }
    }

    private function consumeTransactionsForInstantPayout(Collection $transactions, int $requiredPoints): array
    {
        $consumedIds = [];
        $sum = 0;

        foreach ($transactions as $transaction) {
            if ($sum >= $requiredPoints) {
                break;
            }

            $consumedIds[] = $transaction->id;
            $sum += (int) $transaction->amount;
        }

        return [
            'transaction_ids' => $consumedIds,
            'total_points' => $sum,
            'transaction_count' => count($consumedIds),
        ];
    }

    private function assertInstantEligibility(Cast $cast, int $amountYen): void
    {
        $minAmount = config('cast_payouts.instant_min_amount_yen', 5000);
        if ($amountYen < $minAmount) {
            throw new \InvalidArgumentException("即時振込は最低{$minAmount}円から申請できます。");
        }

        $conversionRate = $this->conversionRate();
        $requiredPoints = (int) ceil($amountYen / max(0.0001, $conversionRate));

        $availablePoints = $this->getInstantAvailablePoints($cast->id);
        $minPoints = (int) config('cast_payouts.instant_min_points', 1000);

        if ($availablePoints < $minPoints) {
            throw new \InvalidArgumentException('即時振込に必要なポイントが貯まっていません。');
        }

        if ($requiredPoints > $availablePoints) {
            throw new \InvalidArgumentException('申請ポイントが利用可能上限を超えています。');
        }
    }

    private function getUnsettledPoints(int $castId): int
    {
        return (int) PointTransaction::where('cast_id', $castId)
            ->whereIn('type', $this->earnableTypes())
            ->whereNull('cast_payout_id')
            ->sum('amount');
    }

    private function getInstantAvailablePoints(int $castId): int
    {
        $total = $this->getUnsettledPoints($castId);
        $ratio = (float) config('cast_payouts.instant_max_ratio', 0.5);

        return (int) floor($total * $ratio);
    }

    private function earnableTypes(): array
    {
        return ['transfer', 'gift'];
    }

    private function conversionRate(): float
    {
        $configured = (float) config('cast_payouts.yen_per_point', 1.2);
        $fallback = (float) config('points.yen_per_point', 1.2);

        return $configured > 0 ? $configured : $fallback;
    }

    private function scheduledFeeRate(?string $grade): float
    {
        return $this->feeRateForGrade('scheduled_fee_rates', $grade);
    }

    private function instantFeeRate(?string $grade): float
    {
        return $this->feeRateForGrade('instant_fee_rates', $grade);
    }

    private function feeRateForGrade(string $configKey, ?string $grade): float
    {
        $gradeKey = $grade ? strtolower($grade) : 'default';
        $rates = config("cast_payouts.{$configKey}", []);

        return (float) ($rates[$gradeKey] ?? $rates['default'] ?? 0.0);
    }

    private function calculateScheduledPayoutDate(Carbon $periodEnd): Carbon
    {
        $offset = (int) config('cast_payouts.scheduled_payout_offset_months', 1);
        $date = $periodEnd->copy()->addMonthsNoOverflow($offset)->endOfMonth();

        if (config('cast_payouts.business_day_adjustment', true)) {
            while ($date->isWeekend()) {
                $date->subDay();
            }
        }

        return $date;
    }
}


