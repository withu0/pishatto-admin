<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Reservation;
use App\Services\PointTransactionService;
use Illuminate\Support\Facades\Log;

class CheckExceededTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reservationId;

    /**
     * Create a new job instance.
     */
    public function __construct($reservationId)
    {
        $this->reservationId = $reservationId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reservation = Reservation::find($this->reservationId);
        
        if (!$reservation) {
            Log::warning('CheckExceededTime job: Reservation not found', [
                'reservation_id' => $this->reservationId
            ]);
            return;
        }

        // Check if reservation is still active (not ended)
        if ($reservation->ended_at) {
            Log::info('CheckExceededTime job: Reservation already ended', [
                'reservation_id' => $this->reservationId
            ]);
            return;
        }

        // Check if reservation is a pishatto call
        if ($reservation->type !== 'Pishatto') {
            Log::info('CheckExceededTime job: Not a pishatto call', [
                'reservation_id' => $this->reservationId,
                'type' => $reservation->type
            ]);
            return;
        }

        $pointService = app(PointTransactionService::class);
        
        // Calculate exceeded time amount
        $exceededAmount = $pointService->calculateExceededTimeAmount($reservation);
        
        if ($exceededAmount > 0) {
            // Process exceeded time and create pending transaction
            $success = $pointService->processExceededTime($reservation);
            
            if ($success) {
                Log::info('CheckExceededTime job: Exceeded time processed successfully', [
                    'reservation_id' => $this->reservationId,
                    'exceeded_amount' => $exceededAmount
                ]);
                
                // Schedule another check in 1 hour
                CheckExceededTime::dispatch($this->reservationId)
                    ->delay(now()->addHour());
            } else {
                Log::error('CheckExceededTime job: Failed to process exceeded time', [
                    'reservation_id' => $this->reservationId,
                    'exceeded_amount' => $exceededAmount
                ]);
            }
        } else {
            Log::info('CheckExceededTime job: No exceeded time found', [
                'reservation_id' => $this->reservationId
            ]);
            
            // Schedule another check in 1 hour if still active
            CheckExceededTime::dispatch($this->reservationId)
                ->delay(now()->addHour());
        }
    }
}

