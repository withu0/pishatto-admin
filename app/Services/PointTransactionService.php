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

            // Recalculate guest grade if this is a guest transaction
            if ($transaction->guest_id) {
                /** @var GradeService $gradeService */
                $gradeService = app(GradeService::class);
                $gradeService->calculateAndUpdateGrade($transaction->guest);
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
                // Gift transactions: points already deducted from guest and added to cast
                // No additional point updates needed here
                break;

            case 'buy':
                // Buy transactions: points already added to user
                // No additional point updates needed here
                break;
        }
    }

    /**
     * Calculate base points earned for a reservation based on cast grade points and duration
     * Base per-minute points = cast.grade_points / 30
     * Total base points = per-minute × actual duration in minutes
     */
    public function calculateReservationPoints(Reservation $reservation): int
    {
        if (!$reservation->cast_id || !$reservation->started_at || !$reservation->ended_at) {
            return 0;
        }

        $cast = Cast::find($reservation->cast_id);
        if (!$cast) {
            return 0;
        }

        $perMinute = (int) floor(($cast->grade_points ?? 0) / 30);

        $startedAt = \Carbon\Carbon::parse($reservation->started_at);
        $endedAt = \Carbon\Carbon::parse($reservation->ended_at);
        $actualMinutes = max(0, $endedAt->diffInMinutes($startedAt));

        return (int) ($perMinute * $actualMinutes);
    }

    /**
     * Night time bonus: 4000 points if started between 00:00 and 05:59 inclusive
     */
    public function calculateNightTimeBonus($startedAt): int
    {
        if (empty($startedAt)) {
            return 0;
        }
        $start = $startedAt instanceof \Carbon\Carbon ? $startedAt : \Carbon\Carbon::parse($startedAt);
        $hour = (int) $start->format('G'); // 0..23 without leading zeros
        return ($hour >= 0 && $hour < 6) ? 4000 : 0;
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

        $perMinute = (int) floor(($cast->grade_points ?? 0) / 30);

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
     * Process reservation completion:
     * - Compute points (base + night bonus + extension)
     * - Transfer points to cast from pending; refund unused to guest
     * - If pending is insufficient, use guest.grade_points for shortfall
     */
    public function processReservationCompletion(Reservation $reservation): bool
    {
        try {
            DB::beginTransaction();

            $reservation->refresh();

            if (!$reservation->cast_id) {
                DB::rollBack();
                return false;
            }

            // Ensure ended_at exists; caller should set it before invoking
            if (!$reservation->ended_at) {
                $reservation->ended_at = now();
            }

            $basePoints = $this->calculateReservationPoints($reservation);
            $nightBonus = $this->calculateNightTimeBonus($reservation->started_at);
            $extensionFee = $this->calculateExtensionFee($reservation);
            $totalPoints = (int) ($basePoints + $nightBonus + $extensionFee);

            $reservation->points_earned = $totalPoints;
            $reservation->save();

            $guest = Guest::find($reservation->guest_id);
            $cast = Cast::find($reservation->cast_id);
            if (!$guest || !$cast) {
                DB::rollBack();
                return false;
            }

            // Sum all pending amounts for this reservation
            $reservedPoints = (int) PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->sum('amount');

            // Decide how much to transfer from pending vs grade points
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
                $this->createTransferTransaction([
                    'guest_id' => $guest->id,
                    'cast_id' => $cast->id,
                    'reservation_id' => $reservation->id,
                    'amount' => $transferFromGrade,
                    'description' => "Shortfall covered from grade points for reservation - {$reservation->id}",
                ]);

                // Deduct shortfall from grade_points
                $guest->grade_points = max(0, (int) $guest->grade_points - $transferFromGrade);
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
