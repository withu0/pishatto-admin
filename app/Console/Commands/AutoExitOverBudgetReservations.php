<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Reservation;
use App\Models\PointTransaction;
use App\Services\PointTransactionService;

class AutoExitOverBudgetReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:auto-exit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto end in-progress reservations when accrued cost exceeds pending + remaining guest points';

    public function handle(PointTransactionService $pointService): int
    {
        $now = now();

        // Find in-progress reservations: started_at set, ended_at null
        $inProgress = Reservation::whereNotNull('started_at')
            ->whereNull('ended_at')
            ->get();

        $processed = 0;
        foreach ($inProgress as $reservation) {
            DB::beginTransaction();
            try {
                // Refresh inside transaction to avoid stale data
                $reservation->refresh();
                if (!$reservation->started_at || $reservation->ended_at) {
                    DB::rollBack();
                    continue;
                }

                $guest = $reservation->guest;
                if (!$guest) {
                    DB::rollBack();
                    continue;
                }

                // Accrued cost so far
                $accrued = $pointService->calculateAccruedCostSoFar($reservation, $now);

                // Pending reserved points for this reservation
                $reservedPoints = (int) PointTransaction::where('reservation_id', $reservation->id)
                    ->where('type', 'pending')
                    ->sum('amount');

                $available = (int) $reservedPoints + (int) $guest->points;

                if ($accrued >= $available) {
                    // End now and settle
                    $reservation->ended_at = $now;
                    $reservation->save();

                    $success = $pointService->processReservationCompletion($reservation);
                    if (!$success) {
                        DB::rollBack();
                        continue;
                    }

                    DB::commit();
                    // Notify listeners/clients
                    event(new \App\Events\ReservationUpdated($reservation));
                    $processed++;
                } else {
                    DB::rollBack();
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                // Log silently; command should keep going
                \Log::error('AutoExitOverBudgetReservations failed for reservation', [
                    'reservation_id' => $reservation->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Processed {$processed} reservations.");
        return Command::SUCCESS;
    }
}


