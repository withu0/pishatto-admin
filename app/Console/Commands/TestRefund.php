<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Reservation;
use App\Services\PointTransactionService;

class TestRefund extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:test-refund {reservation_id : The ID of the reservation to test refund for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test refund processing for a specific cancelled reservation (bypasses scheduled_refund_at date check)';

    public function handle(PointTransactionService $pointService): int
    {
        $reservationId = $this->argument('reservation_id');
        
        $reservation = Reservation::find($reservationId);
        
        if (!$reservation) {
            $this->error("Reservation ID {$reservationId} not found.");
            return Command::FAILURE;
        }

        $this->info("Testing refund for Reservation ID: {$reservationId}");
        $this->line("Status: " . ($reservation->active ? 'Active' : 'Inactive'));
        $this->line("Cancelled at: " . ($reservation->cancelled_at ? $reservation->cancelled_at->format('Y-m-d H:i:s') : 'Not cancelled'));
        $this->line("Scheduled refund at: " . ($reservation->scheduled_refund_at ? $reservation->scheduled_refund_at->format('Y-m-d H:i:s') : 'Not scheduled'));
        
        if (!$reservation->cancelled_at) {
            $this->error("Reservation is not cancelled. Cannot process refund.");
            return Command::FAILURE;
        }

        // Check for pending points
        $reservedPoints = (int) \App\Models\PointTransaction::where('reservation_id', $reservation->id)
            ->where('type', 'pending')
            ->sum('amount');
        
        $this->line("Pending points to refund: {$reservedPoints}");
        
        if ($reservedPoints <= 0) {
            $this->warn("No pending points found to refund.");
            $this->line("This is acceptable - the reservation may have already been refunded or had no points reserved.");
            
            // Still mark as processed if scheduled_refund_at is set
            if ($reservation->scheduled_refund_at) {
                $reservation->scheduled_refund_at = null;
                $reservation->save();
                $this->info("Cleared scheduled_refund_at (no points to refund).");
            }
            return Command::SUCCESS;
        }

        // Get guest info before refund
        $guest = $reservation->guest;
        if (!$guest) {
            $this->error("Guest not found for this reservation.");
            return Command::FAILURE;
        }
        
        $pointsBefore = $guest->points;
        $this->line("Guest points before refund: {$pointsBefore}");

        // Process refund
        $this->info("Processing refund...");
        DB::beginTransaction();
        try {
            $success = $pointService->refundUnusedPoints($reservation);

            if ($success) {
                // Clear scheduled_refund_at to mark as processed
                $reservation->scheduled_refund_at = null;
                $reservation->save();

                DB::commit();
                
                // Refresh guest to get updated points
                $guest->refresh();
                $pointsAfter = $guest->points;
                $pointsRefunded = $pointsAfter - $pointsBefore;
                
                $this->info("✓ Refund processed successfully!");
                $this->line("Guest points after refund: {$pointsAfter}");
                $this->line("Points refunded: {$pointsRefunded}");
                $this->line("Scheduled refund date cleared.");

                \Log::info('Test refund processed for reservation', [
                    'reservation_id' => $reservation->id,
                    'points_refunded' => $pointsRefunded,
                    'guest_points_before' => $pointsBefore,
                    'guest_points_after' => $pointsAfter,
                ]);

                return Command::SUCCESS;
            } else {
                DB::rollBack();
                $this->error("Failed to process refund. Check logs for details.");
                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error processing refund: " . $e->getMessage());
            \Log::error('TestRefund failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
