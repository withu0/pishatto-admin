<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Cast;
use App\Models\Reservation;
use App\Models\PointTransaction;
use App\Services\GradeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PointTransactionService
{
    /**
     * Multiplier applied to per-minute base points for extension minutes
     */
    public const EXTENSION_MULTIPLIER = 1.5;

    /**
     * Create a point transaction record without any calculations
     * All calculations are now done on the frontend
     */
    public function createTransaction(array $data): PointTransaction
    {
        try {
            DB::beginTransaction();

            // Validate required fields
            if (!isset($data['type']) || !isset($data['amount'])) {
                throw new \InvalidArgumentException('Type and amount are required');
            }

            // Create the transaction record
            $transaction = PointTransaction::create([
                'guest_id' => $data['guest_id'] ?? null,
                'cast_id' => $data['cast_id'] ?? null,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'reservation_id' => $data['reservation_id'] ?? null,
                'description' => $data['description'] ?? null,
                'gift_type' => $data['gift_type'] ?? null,
            ]);

            // Update user points based on transaction type
            $this->updateUserPoints($transaction, $data);

            // Recalculate guest or cast grade as needed
            /** @var GradeService $gradeService */
            $gradeService = app(GradeService::class);
            if ($transaction->guest_id) {
                $gradeService->calculateAndUpdateGrade($transaction->guest);
            }
            if ($transaction->cast_id) {
                $gradeService->calculateAndUpdateCastGrade($transaction->cast);
            }

            DB::commit();

            Log::info('Point transaction created successfully', [
                'transaction_id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'guest_id' => $transaction->guest_id,
                'cast_id' => $transaction->cast_id,
                'reservation_id' => $transaction->reservation_id,
            ]);

            return $transaction;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create point transaction', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Create a pending point transaction for reservation
     */
    public function createPendingTransaction(array $data): PointTransaction
    {
        $data['type'] = 'pending';
        return $this->createTransaction($data);
    }

    /**
     * Create a transfer point transaction for completed reservation
     */
    public function createTransferTransaction(array $data): PointTransaction
    {
        $data['type'] = 'transfer';
        return $this->createTransaction($data);
    }

    /**
     * Create a refund point transaction
     */
    public function createRefundTransaction(array $data): PointTransaction
    {
        $data['type'] = 'convert';
        return $this->createTransaction($data);
    }

    /**
     * Create a gift point transaction
     */
    public function createGiftTransaction(array $data): PointTransaction
    {
        $data['type'] = 'gift';
        return $this->createTransaction($data);
    }

    /**
     * Process free call creation - deduct points and create pending transaction
     */
    public function processFreeCallCreation(Reservation $reservation, int $requiredPoints): bool
    {
        try {
            DB::beginTransaction();

            // Get the guest from the reservation
            $guest = Guest::find($reservation->guest_id);
            if (!$guest) {
                DB::rollBack();
                return false;
            }

            // Check if guest has sufficient points before proceeding
            if ($guest->points < $requiredPoints) {
                DB::rollBack();
                return false;
            }

            // Create pending transaction for the free call
            // The updateUserPoints method will handle the point deduction
            $this->createPendingTransaction([
                'guest_id' => $guest->id,
                'reservation_id' => $reservation->id,
                'amount' => $requiredPoints,
                'description' => "フリーコール予約 - {$reservation->id}",
            ]);

            // Update grade points after successful transaction
            $guest->grade_points += $requiredPoints;
            $guest->save();
            try {
                $gradeService = app(GradeService::class);
                $gradeService->calculateAndUpdateGrade($guest);
            } catch (\Throwable $e) {
                Log::warning('Failed to update guest grade after creating pending transaction', [
                    'guest_id' => $guest->id,
                    'error' => $e->getMessage(),
                ]);
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process free call creation', [
                'reservation_id' => $reservation->id,
                'required_points' => $requiredPoints,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Update user points based on transaction type
     */
    private function updateUserPoints(PointTransaction $transaction, array $data): void
    {
        switch ($transaction->type) {
            case 'pending':
                // Deduct points from guest when creating pending transaction
                if ($transaction->guest_id) {
                    $guest = Guest::find($transaction->guest_id);
                    if ($guest && $guest->points >= $transaction->amount) {
                        $guest->points -= $transaction->amount;
                        $guest->save();
                    } else {
                        throw new \InvalidArgumentException('Insufficient guest points');
                    }
                }
                break;

            case 'transfer':
                // Add points to cast when creating transfer transaction
                if ($transaction->cast_id) {
                    $cast = Cast::find($transaction->cast_id);
                    if ($cast) {
                        $cast->points += $transaction->amount;
                        $cast->save();
                        // Update cast grade based on new points
                        $gradeService = app(GradeService::class);
                        $gradeService->calculateAndUpdateCastGrade($cast);
                    }
                }
                break;

            case 'convert':
                // Refund points to guest when creating refund transaction
                if ($transaction->guest_id) {
                    $guest = Guest::find($transaction->guest_id);
                    if ($guest) {
                        $guest->points += $transaction->amount;
                        $guest->save();
                    }
                }
                break;

            case 'gift':
                // Gift transactions: ensure guest grade_points are updated and grades recalculated
                if ($transaction->guest_id) {
                    $guest = Guest::find($transaction->guest_id);
                    if ($guest) {
                        // Guest grade_points should already be updated in ChatController
                        // Just recalculate grade to ensure consistency
                        $gradeService = app(GradeService::class);
                        $gradeService->calculateAndUpdateGrade($guest);
                    }
                }
                if ($transaction->cast_id) {
                    $cast = Cast::find($transaction->cast_id);
                    if ($cast) {
                        // Cast points should already be updated in ChatController
                        // Just recalculate grade to ensure consistency
                        $gradeService = app(GradeService::class);
                        $gradeService->calculateAndUpdateCastGrade($cast);
                    }
                }
                break;

            case 'buy':
                // Buy transactions: points already added to user
                // No additional point updates needed here
                break;
        }
    }

    /**
     * Calculate base points earned for a reservation based on reservation type and scheduled duration
     * Free call: category_points per 30 min
     * Pishatto: cast.grade_points per 30 min
     * Base uses SCHEDULED duration (hours), not actual.
     */
    public function calculateReservationPoints(Reservation $reservation): int
    {
        if (!$reservation->cast_id || !$reservation->duration) {
            return 0;
        }

        $cast = Cast::find($reservation->cast_id);
        if (!$cast) {
            return 0;
        }

        if ($reservation->type === 'free') {
            $perMinute = (int) floor(($cast->category_points ?? 0) / 30);
        } else { // pishatto or default
            $perMinute = (int) floor(($cast->grade_points ?? 0) / 30);
        }

        $scheduledMinutes = (int) ($reservation->duration * 60);
        return (int) ($perMinute * $scheduledMinutes);
    }



    /**
     * Extension fee for minutes beyond scheduled duration
     * extension = per-minute base × exceeded minutes × EXTENSION_MULTIPLIER
     */
    public function calculateExtensionFee(Reservation $reservation): int
    {
        if (!$reservation->cast_id || !$reservation->started_at || !$reservation->ended_at || !$reservation->duration) {
            return 0;
        }

        $cast = Cast::find($reservation->cast_id);
        if (!$cast) {
            return 0;
        }

        // Per-minute base depends on reservation type
        if ($reservation->type === 'free') {
            $perMinute = (int) floor(($cast->category_points ?? 0) / 30);
        } else {
            $perMinute = (int) floor(($cast->grade_points ?? 0) / 30);
        }

        $startedAt = \Carbon\Carbon::parse($reservation->started_at);
        $endedAt = \Carbon\Carbon::parse($reservation->ended_at);
        $scheduledDurationMinutes = (int) ($reservation->duration * 60);
        $actualMinutes = max(0, $endedAt->diffInMinutes($startedAt));

        if ($actualMinutes <= $scheduledDurationMinutes) {
            return 0;
        }

        $exceededMinutes = $actualMinutes - $scheduledDurationMinutes;
        return (int) floor($perMinute * $exceededMinutes * self::EXTENSION_MULTIPLIER);
    }

    /**
     * Night time bonus: 4000 points per hour overlapped between 00:00 and 05:59 inclusive
     * Calculates overlap based on start and end time
     */
    public function calculateNightTimeBonus($startedAt, $endedAt = null): int
    {
        if (empty($startedAt)) {
            return 0;
        }

        $start = $startedAt instanceof \Carbon\Carbon ? $startedAt->copy() : \Carbon\Carbon::parse($startedAt);
        $end = $endedAt ? ($endedAt instanceof \Carbon\Carbon ? $endedAt->copy() : \Carbon\Carbon::parse($endedAt)) : now();

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $nightMinutes = 0;
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($endDay)) {
            $nightStart = $cursor->copy(); // 00:00
            $nightEnd = $cursor->copy()->addHours(6); // 06:00

            // Overlap of [start,end] with [nightStart, nightEnd]
            $overlapStart = $start->greaterThan($nightStart) ? $start : $nightStart;
            $overlapEnd = $end->lessThan($nightEnd) ? $end : $nightEnd;

            if ($overlapEnd->greaterThan($overlapStart)) {
                $nightMinutes += $overlapEnd->diffInMinutes($overlapStart);
            }

            $cursor->addDay();
        }

        $nightHours = (int) floor($nightMinutes / 60);
        return $nightHours * 4000;
    }

    /**
     * Calculate the accrued cost so far for an in-progress reservation.
     * Uses per-minute base rate for elapsed minutes up to scheduled duration,
     * applies extension multiplier beyond scheduled minutes, and includes
     * night-time bonus based on overlap between started_at and now.
     */
    public function calculateAccruedCostSoFar(Reservation $reservation, ?\Carbon\Carbon $now = null): int
    {
        if (!$reservation->cast_id || !$reservation->started_at || !$reservation->duration) {
            return 0;
        }

        $cast = Cast::find($reservation->cast_id);
        if (!$cast) {
            return 0;
        }

        $now = $now ?: now();
        $startedAt = $reservation->started_at instanceof \Carbon\Carbon
            ? $reservation->started_at
            : \Carbon\Carbon::parse($reservation->started_at);

        if ($now->lessThanOrEqualTo($startedAt)) {
            return 0;
        }

        // Per-minute base depends on reservation type
        if ($reservation->type === 'free') {
            $perMinute = (int) floor(($cast->category_points ?? 0) / 30);
        } else {
            $perMinute = (int) floor(($cast->grade_points ?? 0) / 30);
        }

        $elapsedMinutes = (int) $now->diffInMinutes($startedAt);
        $scheduledMinutes = (int) ($reservation->duration * 60);

        $baseForElapsed = (int) ($perMinute * min($elapsedMinutes, $scheduledMinutes));
        $extensionMinutes = max(0, $elapsedMinutes - $scheduledMinutes);
        $extensionFeeSoFar = (int) floor($perMinute * $extensionMinutes * self::EXTENSION_MULTIPLIER);

        $nightBonusSoFar = $this->calculateNightTimeBonus($startedAt, $now);

        return (int) ($baseForElapsed + $extensionFeeSoFar + $nightBonusSoFar);
    }

    /**
     * Process reservation completion:
     * - Compute points (base + night bonus + extension)
     * - Transfer points to cast from pending; refund unused to guest
     * - If pending is insufficient, deduct shortfall from guest.points and ADD the same to guest.grade_points
     */
    public function processReservationCompletion(Reservation $reservation): bool
    {
        try {
            DB::beginTransaction();

            $reservation->refresh();

            // Ensure ended_at exists; caller should set it before invoking
            if (!$reservation->ended_at) {
                $reservation->ended_at = now();
            }

            $guest = Guest::find($reservation->guest_id);
            if (!$guest) {
                DB::rollBack();
                return false;
            }

            // For reservations without cast_id (e.g., free calls with no accepted casts),
            // we can still process refunds but not transfers
            if (!$reservation->cast_id) {
                // Just refund all pending points since no cast to transfer to
                $reservedPoints = (int) PointTransaction::where('reservation_id', $reservation->id)
                    ->where('type', 'pending')
                    ->sum('amount');

                if ($reservedPoints > 0) {
                    $this->createRefundTransaction([
                        'guest_id' => $guest->id,
                        'reservation_id' => $reservation->id,
                        'amount' => $reservedPoints,
                        'description' => "Free call completed without cast - refunding all points - {$reservation->id}",
                    ]);

                    // Reduce grade_points by refunded amount
                    $guest->grade_points = max(0, (int) $guest->grade_points - $reservedPoints);
                    $guest->save();

                    /** @var GradeService $gradeService */
                    $gradeService = app(GradeService::class);
                    $gradeService->calculateAndUpdateGrade($guest);
                }

                DB::commit();
                return true;
            }

            // For reservations with cast_id, calculate and transfer points
            $basePoints = $this->calculateReservationPoints($reservation);
            $nightBonus = $this->calculateNightTimeBonus($reservation->started_at, $reservation->ended_at);
            $extensionFee = $this->calculateExtensionFee($reservation);
            $totalPoints = (int) ($basePoints + $nightBonus + $extensionFee);

            $reservation->points_earned = $totalPoints;
            $reservation->save();

            $cast = Cast::find($reservation->cast_id);
            if (!$cast) {
                DB::rollBack();
                return false;
            }

            // Sum all pending amounts for this reservation
            $reservedPoints = (int) PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->sum('amount');

            // Decide how much to transfer from pending vs shortfall from guest points
            $transferFromPending = min($reservedPoints, $totalPoints);
            $transferFromGrade = max(0, $totalPoints - $transferFromPending);

            if ($transferFromPending > 0) {
                $this->createTransferTransaction([
                    'guest_id' => $guest->id,
                    'cast_id' => $cast->id,
                    'reservation_id' => $reservation->id,
                    'amount' => $transferFromPending,
                    'description' => "予約待ちから予約へ移行 - {$reservation->id}",
                ]);
            }

            if ($transferFromGrade > 0) {
                // Ensure guest has enough points to cover shortfall
                if ($guest->points < $transferFromGrade) {
                    DB::rollBack();
                    return false;
                }

                // Create transfer for the shortfall amount as well
                $this->createTransferTransaction([
                    'guest_id' => $guest->id,
                    'cast_id' => $cast->id,
                    'reservation_id' => $reservation->id,
                    'amount' => $transferFromGrade,
                    'description' => "Shortfall covered from guest points for reservation - {$reservation->id}",
                ]);

                // Deduct from guest points and add to guest grade_points
                $guest->points = max(0, (int) $guest->points - $transferFromGrade);
                $guest->grade_points = (int) $guest->grade_points + $transferFromGrade;
                $guest->save();

                /** @var GradeService $gradeService */
                $gradeService = app(GradeService::class);
                $gradeService->calculateAndUpdateGrade($guest);
            }

            // Handle refund when reserved exceeds total
            if ($reservedPoints > $totalPoints) {
                $refund = $reservedPoints - $totalPoints;
                if ($refund > 0) {
                    $this->createRefundTransaction([
                        'guest_id' => $guest->id,
                        'reservation_id' => $reservation->id,
                        'amount' => $refund,
                        'description' => "予約で未使用のポイントを返金しました - {$reservation->id}",
                    ]);

                    // Reduce grade_points by refunded amount
                    $guest->grade_points = max(0, (int) $guest->grade_points - $refund);
                    $guest->save();

                    // Recalculate grade after adjustment
                    /** @var GradeService $gradeService */
                    $gradeService = app(GradeService::class);
                    $gradeService->calculateAndUpdateGrade($guest);
                }
            }

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('processReservationCompletion failed', [
                'reservation_id' => $reservation->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Refund all unused pending points for a reservation (e.g., on cancel)
     */
    public function refundUnusedPoints(Reservation $reservation): bool
    {
        try {
            DB::beginTransaction();

            $guest = Guest::find($reservation->guest_id);
            if (!$guest) {
                DB::rollBack();
                return false;
            }

            $reservedPoints = (int) PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->sum('amount');

            if ($reservedPoints <= 0) {
                DB::rollBack();
                return false;
            }

            $this->createRefundTransaction([
                'guest_id' => $guest->id,
                'reservation_id' => $reservation->id,
                'amount' => $reservedPoints,
                'description' => "refunded all points for cancelled reservation - {$reservation->id}",
            ]);

            // Reduce grade_points by refunded amount
            $guest->grade_points = max(0, (int) $guest->grade_points - $reservedPoints);
            $guest->save();

            // Update grade after adjustment
            /** @var GradeService $gradeService */
            $gradeService = app(GradeService::class);
            $gradeService->calculateAndUpdateGrade($guest);

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('refundUnusedPoints failed', [
                'reservation_id' => $reservation->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get point transaction history for a user
     */
    public function getTransactionHistory($userId, $userType, $limit = 50)
    {
        $query = PointTransaction::with(['guest', 'cast', 'reservation']);

        if ($userType === 'guest') {
            $query->where('guest_id', $userId);
        } elseif ($userType === 'cast') {
            $query->where('cast_id', $userId);
        }

        return $query->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    /**
     * Get point transaction by ID
     */
    public function getTransactionById($id): ?PointTransaction
    {
        return PointTransaction::with(['guest', 'cast', 'reservation'])->find($id);
    }

    /**
     * Get transactions by reservation ID
     */
    public function getTransactionsByReservation($reservationId)
    {
        return PointTransaction::with(['guest', 'cast'])
            ->where('reservation_id', $reservationId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get pending transactions by reservation ID
     */
    public function getPendingTransactionsByReservation($reservationId)
    {
        return PointTransaction::where('reservation_id', $reservationId)
            ->where('type', 'pending')
            ->get();
    }

    /**
     * Update transaction description
     */
    public function updateTransactionDescription($transactionId, $description): bool
    {
        try {
            $transaction = PointTransaction::find($transactionId);
            if (!$transaction) {
                return false;
            }

            $transaction->description = $description;
            $transaction->save();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update transaction description', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete a point transaction (for admin use only)
     */
    public function deleteTransaction($transactionId): bool
    {
        try {
            DB::beginTransaction();

            $transaction = PointTransaction::find($transactionId);
            if (!$transaction) {
                return false;
            }

            // Reverse the point changes if this was a completed transaction
            $this->reversePointChanges($transaction);

            $transaction->delete();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete point transaction', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Reverse point changes when deleting a transaction
     */
    private function reversePointChanges(PointTransaction $transaction): void
    {
        switch ($transaction->type) {
            case 'transfer':
                // Remove points from cast
                if ($transaction->cast_id) {
                    $cast = Cast::find($transaction->cast_id);
                    if ($cast) {
                        $cast->points = max(0, $cast->points - $transaction->amount);
                        $cast->save();
                    }
                }
                break;

            case 'convert':
                // Remove refunded points from guest
                if ($transaction->guest_id) {
                    $guest = Guest::find($transaction->guest_id);
                    if ($guest) {
                        $guest->points = max(0, $guest->points - $transaction->amount);
                        $guest->save();
                    }
                }
                break;

            case 'gift':
                // Reverse gift transaction
                if ($transaction->guest_id && $transaction->cast_id) {
                    $guest = Guest::find($transaction->guest_id);
                    $cast = Cast::find($transaction->cast_id);
                    
                    if ($guest) {
                        $guest->points += $transaction->amount;
                        $guest->save();
                    }
                    
                    if ($cast) {
                        $cast->points = max(0, $cast->points - $transaction->amount);
                        $cast->save();
                    }
                }
                break;
        }
    }
} 
