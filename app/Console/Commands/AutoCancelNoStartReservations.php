<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Reservation;
use App\Models\ReservationApplication;
use App\Models\CastSession;
use Carbon\Carbon;

class AutoCancelNoStartReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:auto-cancel-no-start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto cancel reservations where cast did not start timer within 1 hour of scheduled time';

    public function handle(): int
    {
        // Use Asia/Tokyo timezone for consistency
        $tz = 'Asia/Tokyo';
        $now = now($tz);
        $gracePeriodEnd = $now->copy()->subHour(); // 1 hour ago

        // Find reservations that:
        // 1. Are still active
        // 2. Have scheduled_at set
        // 3. scheduled_at + 1 hour has passed
        // 4. Not already cancelled
        // 5. Not already ended
        $candidates = Reservation::where('active', true)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $gracePeriodEnd)
            ->whereNull('cancelled_at')
            ->whereNull('ended_at')
            ->get();

        $processed = 0;
        $cancelled = 0;

        foreach ($candidates as $reservation) {
            DB::beginTransaction();
            try {
                // Refresh to avoid stale data
                $reservation->refresh();
                
                // Double-check conditions after refresh
                if (!$reservation->active || $reservation->cancelled_at || $reservation->ended_at) {
                    DB::rollBack();
                    continue;
                }

                $shouldCancel = false;
                $reason = '';

                if ($reservation->type === 'Pishatto') {
                    // For Pishatto: check if started_at is null
                    if (!$reservation->started_at) {
                        $shouldCancel = true;
                        $reason = 'キャストがタイマーを開始しませんでした（Pishatto）';
                    }
                } elseif ($reservation->type === 'free') {
                    // For free: check if any approved cast hasn't started a session
                    $approvedCasts = ReservationApplication::where('reservation_id', $reservation->id)
                        ->where('status', 'approved')
                        ->pluck('cast_id')
                        ->toArray();

                    if (empty($approvedCasts)) {
                        // No approved casts, skip
                        DB::rollBack();
                        continue;
                    }

                    // Check if any approved cast hasn't started a session
                    $startedCasts = CastSession::where('reservation_id', $reservation->id)
                        ->whereIn('cast_id', $approvedCasts)
                        ->whereNotNull('started_at')
                        ->pluck('cast_id')
                        ->toArray();

                    $notStartedCasts = array_diff($approvedCasts, $startedCasts);

                    if (!empty($notStartedCasts)) {
                        $shouldCancel = true;
                        $castCount = count($notStartedCasts);
                        $reason = "承認済みキャスト{$castCount}名がタイマーを開始しませんでした（free）";
                    }
                }

                if ($shouldCancel) {
                    // Calculate next business day at 23:59 in Asia/Tokyo timezone
                    $nextBusinessDay = $this->calculateNextBusinessDay($now);
                    $scheduledRefundAt = $nextBusinessDay->copy()->setTime(23, 59, 0)->setTimezone($tz);

                    // Cancel the reservation
                    $reservation->active = false;
                    $reservation->cancelled_at = $now;
                    $reservation->cancellation_reason = $reason;
                    $reservation->scheduled_refund_at = $scheduledRefundAt;
                    $reservation->save();

                    DB::commit();
                    $cancelled++;

                    \Log::info('Auto-cancelled reservation due to no timer start', [
                        'reservation_id' => $reservation->id,
                        'type' => $reservation->type,
                        'scheduled_at' => $reservation->scheduled_at,
                        'cancelled_at' => $reservation->cancelled_at,
                        'scheduled_refund_at' => $scheduledRefundAt,
                        'reason' => $reason,
                    ]);

                    // Broadcast the reservation update event
                    event(new \App\Events\ReservationUpdated($reservation));
                } else {
                    DB::rollBack();
                }

                $processed++;
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('AutoCancelNoStartReservations failed for reservation', [
                    'reservation_id' => $reservation->id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Processed {$processed} reservations, cancelled {$cancelled}.");
        return Command::SUCCESS;
    }

    /**
     * Calculate the next business day (skipping weekends)
     * 
     * @param Carbon $date
     * @return Carbon
     */
    private function calculateNextBusinessDay(Carbon $date): Carbon
    {
        $nextDay = $date->copy()->addDay();
        
        // Skip weekends (Saturday = 6, Sunday = 0)
        while ($nextDay->isWeekend()) {
            $nextDay->addDay();
        }
        
        return $nextDay;
    }
}
