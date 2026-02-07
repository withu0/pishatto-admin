<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Reservation;
use App\Models\Payment;
use App\Services\PointTransactionService;

class ProcessScheduledRefunds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:process-scheduled-refunds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled refunds for cancelled reservations at 23:59 on business days';

    public function handle(PointTransactionService $pointService): int
    {
        // Use Asia/Tokyo timezone for consistency
        $tz = 'Asia/Tokyo';
        $now = now($tz);

        // Find reservations that:
        // 1. Are cancelled (cancelled_at is not null)
        // 2. Have scheduled_refund_at set
        // 3. scheduled_refund_at <= now
        // 4. Not already refunded (scheduled_refund_at is still set, meaning refund not processed yet)
        $pendingRefunds = Reservation::whereNotNull('cancelled_at')
            ->whereNotNull('scheduled_refund_at')
            ->where('scheduled_refund_at', '<=', $now)
            ->get();

        $processed = 0;
        $successful = 0;
        $failed = 0;

        foreach ($pendingRefunds as $reservation) {
            DB::beginTransaction();
            try {
                // Refresh to avoid stale data
                $reservation->refresh();

                // Double-check conditions after refresh
                if (!$reservation->cancelled_at || !$reservation->scheduled_refund_at) {
                    DB::rollBack();
                    continue;
                }

                // Verify scheduled_refund_at is still in the past (should already be filtered by query, but double-check)
                if ($reservation->scheduled_refund_at > $now) {
                    DB::rollBack();
                    continue;
                }

                // If reservation has pending card payment, handle card-funded cancellation; else refund all unused points
                $hasPendingPayment = Payment::where('reservation_id', $reservation->id)
                    ->where('status', 'pending')
                    ->whereNotNull('stripe_payment_intent_id')
                    ->exists();

                if ($hasPendingPayment) {
                    $success = $pointService->handleCancellationForCardFundedReservation($reservation);
                } else {
                    $success = $pointService->refundUnusedPoints($reservation);
                }

                if ($success) {
                    // Clear scheduled_refund_at to mark as processed
                    $reservation->scheduled_refund_at = null;
                    $reservation->save();

                    DB::commit();
                    $successful++;

                    \Log::info('Processed scheduled refund for cancelled reservation', [
                        'reservation_id' => $reservation->id,
                        'cancelled_at' => $reservation->cancelled_at,
                        'refund_processed_at' => $now,
                    ]);
                } else {
                    // Check if failure is due to no points to refund (which is acceptable)
                    $reservedPoints = (int) \App\Models\PointTransaction::where('reservation_id', $reservation->id)
                        ->where('type', 'pending')
                        ->sum('amount');
                    
                    if ($reservedPoints <= 0) {
                        // No points to refund - mark as processed anyway
                        $reservation->scheduled_refund_at = null;
                        $reservation->save();
                        DB::commit();
                        $successful++;
                        
                        \Log::info('Scheduled refund processed - no points to refund', [
                            'reservation_id' => $reservation->id,
                            'cancelled_at' => $reservation->cancelled_at,
                        ]);
                    } else {
                        // Actual failure - log and keep scheduled for retry
                        DB::rollBack();
                        $failed++;

                        \Log::warning('Failed to process scheduled refund', [
                            'reservation_id' => $reservation->id,
                            'cancelled_at' => $reservation->cancelled_at,
                            'scheduled_refund_at' => $reservation->scheduled_refund_at,
                            'reserved_points' => $reservedPoints,
                        ]);
                    }
                }

                $processed++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $failed++;

                \Log::error('ProcessScheduledRefunds failed for reservation', [
                    'reservation_id' => $reservation->id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Processed {$processed} scheduled refunds: {$successful} successful, {$failed} failed.");
        return Command::SUCCESS;
    }
}
