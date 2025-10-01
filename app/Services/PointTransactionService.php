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

        Log::info('Point transaction created successfully', [
            'transaction_id' => $transaction->id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'guest_id' => $transaction->guest_id,
            'cast_id' => $transaction->cast_id,
            'reservation_id' => $transaction->reservation_id,
        ]);

        return $transaction;
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
     * Create an exceeded pending point transaction for exceeded time
     */
    public function createExceededPendingTransaction(array $data): PointTransaction
    {
        $data['type'] = 'exceeded_pending';
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
            // Grade upgrades are handled via quarterly evaluation & admin approval

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
                        // Grade upgrades are handled via quarterly evaluation & admin approval
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
                // Gift transactions: points are updated but grades are handled via quarterly evaluation
                // No automatic grade upgrades - only point balance updates
                break;

            case 'buy':
                // Buy transactions: points already added to user
                // No additional point updates needed here
                break;

            case 'exceeded_pending':
                // Exceeded pending transactions: deduct points from guest for exceeded time
                // This method is called when processing existing exceeded_pending transactions
                if ($transaction->guest_id) {
                    $guest = Guest::find($transaction->guest_id);
                    if ($guest) {
                        $requiredAmount = (int) $transaction->amount;
                        $availablePoints = (int) $guest->points;

                        if ($availablePoints >= $requiredAmount) {
                            // Sufficient points - deduct normally
                            $guest->points -= $requiredAmount;
                            $guest->save();

                            Log::info('Exceeded pending: Sufficient points available', [
                                'guest_id' => $guest->id,
                                'required_amount' => $requiredAmount,
                                'available_points' => $availablePoints,
                                'remaining_points' => $guest->points
                            ]);
                        } else {
                            // Insufficient points - deduct available points only
                            // Note: Automatic payment should have been handled during transaction creation
                            $guest->points = 0;
                            $guest->save();

                            Log::warning('Exceeded pending: Insufficient points, deducting available points only', [
                                'guest_id' => $guest->id,
                                'available_points' => $availablePoints,
                                'required_amount' => $requiredAmount,
                                'shortfall' => $requiredAmount - $availablePoints,
                                'reservation_id' => $transaction->reservation_id
                            ]);
                        }
                    }
                }
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
        } else { // Pishatto or default
            $perMinute = (int) floor(($cast->grade_points ?? 0) / 30);
        }

        $scheduledMinutes = $this->getScheduledDurationMinutes($reservation);
        return (int) ($perMinute * $scheduledMinutes);
    }

    /**
     * Calculate total points based on actual elapsed time between started_at and ended_at
     * This method calculates points for the actual time the session ran, not the scheduled duration
     */
    public function calculateTotalPointsBasedOnElapsedTime(Reservation $reservation): int
    {
        if (!$reservation->cast_id || !$reservation->started_at || !$reservation->ended_at) {
            return 0;
        }

        $cast = Cast::find($reservation->cast_id);
        if (!$cast) {
            return 0;
        }

        // Per-minute base depends on reservation type
        if ($reservation->type === 'free') {
            $perMinute = (int) floor(($cast->category_points ?? 0) / 30);
        } else { // Pishatto or default
            $perMinute = (int) floor(($cast->grade_points ?? 0) / 30);
        }

        $startedAt = \Carbon\Carbon::parse($reservation->started_at)->setTimezone('UTC');
        $endedAt = \Carbon\Carbon::parse($reservation->ended_at)->setTimezone('UTC');

        // Ensure we have valid dates and ended_at is after started_at
        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            Log::warning('calculateTotalPointsBasedOnElapsedTime: Invalid time range', [
                'reservation_id' => $reservation->id,
                'started_at' => $startedAt->toDateTimeString(),
                'ended_at' => $endedAt->toDateTimeString()
            ]);
            return 0;
        }

        // Calculate elapsed minutes manually to avoid diffInMinutes() issues
        $timeDifferenceSeconds = $endedAt->timestamp - $startedAt->timestamp;
        $elapsedMinutes = max(0, $timeDifferenceSeconds / 60); // Use decimal minutes for accurate calculation

        // Calculate base points for elapsed time
        $basePoints = (int) ($perMinute * $elapsedMinutes);

        // Add night time bonus
        $nightBonus = $this->calculateNightTimeBonus($reservation->started_at, $reservation->ended_at);

        Log::info('calculateTotalPointsBasedOnElapsedTime: Calculation', [
            'reservation_id' => $reservation->id,
            'started_at' => $startedAt->toDateTimeString(),
            'ended_at' => $endedAt->toDateTimeString(),
            'started_at_timestamp' => $startedAt->timestamp,
            'ended_at_timestamp' => $endedAt->timestamp,
            'time_difference_seconds' => $endedAt->timestamp - $startedAt->timestamp,
            'elapsed_minutes' => $elapsedMinutes,
            'per_minute' => $perMinute,
            'base_points' => $basePoints,
            'night_bonus' => $nightBonus,
            'total_points' => $basePoints + $nightBonus
        ]);

        return (int) ($basePoints + $nightBonus);
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
        $scheduledDurationMinutes = $this->getScheduledDurationMinutes($reservation);
        $actualMinutes = max(0, $endedAt->diffInMinutes($startedAt));

        if ($actualMinutes <= $scheduledDurationMinutes) {
            return 0;
        }

        $exceededMinutes = $actualMinutes - $scheduledDurationMinutes;
        return (int) floor($perMinute * $exceededMinutes * self::EXTENSION_MULTIPLIER);
    }

    /**
     * Calculate exceeded time amount for pishatto calls and create pending transaction
     * This is called when a pishatto call exceeds the scheduled duration
     * NOTE: This method is now deprecated - exceeded time is handled in processReservationCompletion
     */
    public function processExceededTime(Reservation $reservation): bool
    {
        // This method is now deprecated as exceeded time is handled in processReservationCompletion
        // Return true to maintain backward compatibility
        Log::info('processExceededTime: Method deprecated, exceeded time now handled in processReservationCompletion', [
            'reservation_id' => $reservation->id
        ]);
        return true;
    }

    /**
     * Calculate exceeded time amount for pishatto calls
     */
    public function calculateExceededTimeAmount(Reservation $reservation): int
    {
        if (!$reservation->cast_id || !$reservation->started_at || !$reservation->duration) {
            Log::info('calculateExceededTimeAmount: Missing required fields', [
                'reservation_id' => $reservation->id,
                'cast_id' => $reservation->cast_id,
                'started_at' => $reservation->started_at,
                'duration' => $reservation->duration
            ]);
            return 0;
        }

        $cast = Cast::find($reservation->cast_id);
        if (!$cast) {
            return 0;
        }

        // For pishatto calls, use grade_points per minute
        $perMinute = (int) floor(($cast->grade_points ?? 0) / 30);

        $startedAt = \Carbon\Carbon::parse($reservation->started_at);
        $endedAt = $reservation->ended_at ? \Carbon\Carbon::parse($reservation->ended_at) : now();
        $scheduledDurationMinutes = $this->getScheduledDurationMinutes($reservation);
        $elapsedMinutes = max(0, $endedAt->diffInMinutes($startedAt));

        Log::info('calculateExceededTimeAmount: Time calculation', [
            'reservation_id' => $reservation->id,
            'started_at' => $startedAt->toDateTimeString(),
            'ended_at' => $endedAt->toDateTimeString(),
            'duration_hours_or_minutes' => $reservation->duration,
            'scheduled_duration_minutes' => $scheduledDurationMinutes,
            'elapsed_minutes' => $elapsedMinutes,
            'per_minute' => $perMinute,
            'extension_multiplier' => self::EXTENSION_MULTIPLIER
        ]);

        if ($elapsedMinutes <= $scheduledDurationMinutes) {
            Log::info('calculateExceededTimeAmount: No exceeded time', [
                'reservation_id' => $reservation->id,
                'elapsed_minutes' => $elapsedMinutes,
                'scheduled_duration_minutes' => $scheduledDurationMinutes
            ]);
            return 0;
        }

        $exceededMinutes = $elapsedMinutes - $scheduledDurationMinutes;
        $exceededAmount = (int) floor($perMinute * $exceededMinutes * self::EXTENSION_MULTIPLIER);

        Log::info('calculateExceededTimeAmount: Exceeded time calculated', [
            'reservation_id' => $reservation->id,
            'exceeded_minutes' => $exceededMinutes,
            'exceeded_amount' => $exceededAmount
        ]);

        return $exceededAmount;
    }

    /**
     * Derive scheduled duration in minutes from reservation.duration which may be provided
     * either in hours (decimal) or minutes (small integers from UI, e.g., 5 for 5 minutes).
     * Heuristics:
     * - If duration < 1: treat as hours decimal → minutes = duration * 60
     * - If duration is an integer and <= 60: treat as minutes
     * - If duration is >= 24: treat as minutes (unlikely to be hours)
     * - Otherwise: treat as hours → minutes = duration * 60
     */
    private function getScheduledDurationMinutes(Reservation $reservation): int
    {
        $raw = $reservation->duration;
        $numeric = is_numeric($raw) ? (float) $raw : 0.0;
        if ($numeric <= 0) {
            return 0;
        }

        // Detect if value has a fractional part
        $hasFraction = fmod($numeric, 1.0) !== 0.0;

        if ($numeric < 1.0 || $hasFraction) {
            // Decimal hours
            return (int) floor($numeric * 60.0);
        }

        // Integer values: likely minutes for small values (<=60) or very large values (>=24)
        if ($numeric <= 60.0 || $numeric >= 24 * 60.0) {
            return (int) $numeric;
        }

        // Default to hours
        return (int) floor($numeric * 60.0);
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
                // Calculate overlap minutes manually to avoid diffInMinutes() issues
                $overlapSeconds = $overlapEnd->timestamp - $overlapStart->timestamp;
                $overlapMinutes = max(0, (int) floor($overlapSeconds / 60));
                // Ensure we don't get negative values
                if ($overlapMinutes > 0) {
                    $nightMinutes += $overlapMinutes;
                }
            }

            $cursor->addDay();
        }

        $nightHours = (int) floor($nightMinutes / 60);
        $bonus = $nightHours * 4000;

        Log::info('calculateNightTimeBonus: Calculation', [
            'started_at' => $start->toDateTimeString(),
            'ended_at' => $end->toDateTimeString(),
            'start_hour' => $start->hour,
            'end_hour' => $end->hour,
            'night_minutes' => $nightMinutes,
            'night_hours' => $nightHours,
            'bonus' => $bonus,
            'is_night_time' => ($start->hour >= 0 && $start->hour < 6) || ($end->hour >= 0 && $end->hour < 6)
        ]);

        return $bonus;
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
        $duration = is_numeric($reservation->duration) ? (float)$reservation->duration : 0;
        $scheduledMinutes = (int) ($duration * 60);

        $baseForElapsed = (int) ($perMinute * min($elapsedMinutes, $scheduledMinutes));
        $extensionMinutes = max(0, $elapsedMinutes - $scheduledMinutes);
        $extensionFeeSoFar = (int) floor($perMinute * $extensionMinutes * self::EXTENSION_MULTIPLIER);

        $nightBonusSoFar = $this->calculateNightTimeBonus($startedAt, $now);

        return (int) ($baseForElapsed + $extensionFeeSoFar + $nightBonusSoFar);
    }

    /**
     * Process reservation completion:
     * - Compute total points based on actual elapsed time (started_at to ended_at)
     * - Calculate exceeded_pending as total_points - pending_points
     * - Transfer points to cast from pending; refund unused to guest
     * - If pending is insufficient, deduct shortfall from guest.points and ADD the same to guest.grade_points
     */
    public function processReservationCompletion(Reservation $reservation): bool
    {
        try {
            Log::info('Starting processReservationCompletion', [
                'reservation_id' => $reservation->id,
                'guest_id' => $reservation->guest_id,
                'cast_id' => $reservation->cast_id,
                'type' => $reservation->type
            ]);

            DB::beginTransaction();

            $reservation->refresh();

            // Check if reservation has already been processed
            if ($reservation->points_earned !== null) {
                Log::info('Reservation already processed, skipping', [
                    'reservation_id' => $reservation->id,
                    'points_earned' => $reservation->points_earned
                ]);
                return true;
            }

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
                    // Grade updates are handled via quarterly evaluation & admin approval
                }

                DB::commit();
                return true;
            }

            // Calculate total points based on actual elapsed time (started_at to ended_at)
            $totalPoints = $this->calculateTotalPointsBasedOnElapsedTime($reservation);
            $nightBonus = $this->calculateNightTimeBonus($reservation->started_at, $reservation->ended_at);

            // Sum all pending amounts for this reservation (reserved for scheduled duration)
            $reservedPoints = (int) PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->sum('amount');

            // Calculate exceeded_pending as total_points - pending_points
            $exceededPendingPoints = max(0, $totalPoints - $reservedPoints);

            // Calculate actual shortfall based on guest's total available points
            // Guest's current points + reserved points (pending transaction)
            $guestAvailablePoints = (int) $guest->points;
            $reservedPoints = (int) $reservedPoints; // Ensure it's an integer
            $guestTotalAvailablePoints = $guestAvailablePoints + $reservedPoints;
            $totalShortfall = max(0, $totalPoints - $guestTotalAvailablePoints);

            Log::info('Shortfall calculation debug', [
                'reservation_id' => $reservation->id,
                'total_points' => $totalPoints,
                'guest_available_points' => $guestAvailablePoints,
                'reserved_points' => $reservedPoints,
                'guest_total_available_points' => $guestTotalAvailablePoints,
                'calculated_shortfall' => $totalShortfall,
                'guest_id' => $guest->id,
                'guest_points_before_pending' => $guestAvailablePoints + $reservedPoints
            ]);

            $reservation->points_earned = $totalPoints;
            $reservation->save();

            $cast = Cast::find($reservation->cast_id);
            if (!$cast) {
                DB::rollBack();
                return false;
            }

            Log::info('Reservation completion calculation', [
                'reservation_id' => $reservation->id,
                'reserved_points' => $reservedPoints,
                'exceeded_pending_points' => $exceededPendingPoints,
                'total_points' => $totalPoints,
                'night_bonus' => $nightBonus,
                'started_at' => $reservation->started_at,
                'ended_at' => $reservation->ended_at
            ]);

            Log::info('Guest points before shortfall calculation', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'guest_points' => $guest->points,
                'guest_points_before_pending' => $guest->points + $reservedPoints
            ]);

            // 1. Handle automatic payment for shortfall BEFORE any point deductions
            if ($totalShortfall > 0) {
                // Check if automatic payment transaction already exists for this reservation
                $existingPayment = PointTransaction::where('reservation_id', $reservation->id)
                    ->where('type', 'buy')
                    ->where('description', 'like', '%Automatic payment for exceeded time%')
                    ->first();

                if (!$existingPayment) {
                    try {
                        // Process automatic payment for the shortfall
                        $automaticPaymentService = app(\App\Services\AutomaticPaymentService::class);
                        $paymentResult = $automaticPaymentService->processAutomaticPaymentForInsufficientPoints(
                            $guest->id,
                            $totalShortfall,
                            $reservation->id,
                            "Automatic payment for exceeded time - reservation {$reservation->id}"
                        );
                    } catch (\Exception $e) {
                        Log::error('Automatic payment failed', [
                            'reservation_id' => $reservation->id,
                            'guest_id' => $guest->id,
                            'shortfall' => $totalShortfall,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);

                        // Set payment result to failed
                        $paymentResult = [
                            'success' => false,
                            'error' => $e->getMessage()
                        ];
                    }

                    if ($paymentResult['success']) {
                        // Add the payment points to guest's account
                        $guest->points += $paymentResult['points_added'];
                        $guest->save();

                        // Deduct the total points used from guest's account
                        // The guest should end up with: original_points + payment_points - total_points
                        $guest->points = max(0, $guest->points - $totalPoints);
                        $guest->save();

                        // Create exceeded_pending transaction for the total points used
                        $this->createExceededPendingTransaction([
                            'guest_id' => $guest->id,
                            'cast_id' => $cast->id,
                            'reservation_id' => $reservation->id,
                            'amount' => $totalPoints,
                            'description' => "Pishatto call exceeded time - {$reservation->id} (paid via automatic payment)",
                        ]);
                    } else {
                        // Payment failed - deduct available points only
                        $guest->points = 0;
                        $guest->save();

                        // Create exceeded_pending transaction for available points only
                        $this->createExceededPendingTransaction([
                            'guest_id' => $guest->id,
                            'cast_id' => $cast->id,
                            'reservation_id' => $reservation->id,
                            'amount' => $guestAvailablePoints,
                            'description' => "Pishatto call exceeded time - {$reservation->id} (insufficient points, no payment method)",
                        ]);
                    }
                }
            }

            // 2. Transfer ALL pending points to cast (for scheduled duration)
            if ($reservedPoints > 0) {
                // Check if transfer transaction already exists to avoid duplicates
                $existingTransfer = PointTransaction::where('reservation_id', $reservation->id)
                    ->where('type', 'transfer')
                    ->where('description', 'like', '%予約待ちから予約へ移行%')
                    ->first();

                if (!$existingTransfer) {
                    $this->createTransferTransaction([
                        'guest_id' => $guest->id,
                        'reservation_id' => $reservation->id,
                        'amount' => $reservedPoints,
                        'description' => "予約待ちから予約へ移行 - {$reservation->id}",
                    ]);
                }
            }

            // 3. Exceeded_pending transactions are now handled in the automatic payment section above
            // No additional logic needed here

            // 3. Handle refund when reserved exceeds what was actually used
            // Only refund if we used less than what was reserved
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

            // Send concierge message to guest with session completion details
            $this->sendSessionCompletionMessage($reservation, $guest, $cast, $totalPoints, $reservedPoints, $totalShortfall);

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
            // Grade updates are handled via quarterly evaluation & admin approval

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
     * Process 2-day auto-transfer for exceeded pending amounts
     * This should be called by a scheduled command
     */
    public function processAutoTransferExceededPending(): int
    {
        $processedCount = 0;

        try {
            DB::beginTransaction();

            // Find exceeded_pending transactions older than 2 days that haven't been processed yet
            $exceededPendingTransactions = PointTransaction::where('type', 'exceeded_pending')
                ->where('created_at', '<=', now()->subDays(2))
                ->where('description', 'not like', '%(Auto-transferred after 2 days)%')
                ->get();

            foreach ($exceededPendingTransactions as $transaction) {
                if ($this->transferExceededPendingToCast($transaction)) {
                    $processedCount++;
                }
            }

            DB::commit();
            Log::info('Auto-transfer exceeded pending completed', [
                'processed_count' => $processedCount,
                'total_found' => $exceededPendingTransactions->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process auto-transfer exceeded pending', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $processedCount;
    }

    /**
     * Transfer exceeded pending amount to cast and remove from guest
     */
    private function transferExceededPendingToCast(PointTransaction $transaction): bool
    {
        try {
            if (!$transaction->cast_id || !$transaction->guest_id) {
                return false;
            }

            $cast = Cast::find($transaction->cast_id);
            $guest = Guest::find($transaction->guest_id);

            if (!$cast || !$guest) {
                return false;
            }

            // Add points to cast
            $cast->points += $transaction->amount;
            $cast->save();

            // Create transfer transaction record (separate from exceeded_pending)
            $this->createTransferTransaction([
                'guest_id' => $guest->id,
                'cast_id' => $cast->id,
                'reservation_id' => $transaction->reservation_id,
                'amount' => $transaction->amount,
                'description' => "Auto-transfer exceeded pending amount after 2 days - {$transaction->id}",
            ]);

            // Mark the exceeded_pending transaction as processed (but keep type as exceeded_pending)
            $transaction->update([
                'description' => $transaction->description . ' (Auto-transferred after 2 days)',
                // Keep type as 'exceeded_pending' to maintain audit trail
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to transfer exceeded pending to cast', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all exceeded pending transactions for admin view
     */
    public function getExceededPendingTransactions()
    {
        return PointTransaction::with(['guest', 'cast', 'reservation'])
            ->where('type', 'exceeded_pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get exceeded pending transactions count for admin dashboard
     */
    public function getExceededPendingCount(): int
    {
        return PointTransaction::where('type', 'exceeded_pending')->count();
    }

    /**
     * Get all point transactions except pending type for admin view
     */
    public function getAllPointTransactionsExceptPending()
    {
        return PointTransaction::with(['guest', 'cast', 'reservation', 'payment'])
            ->where('type', '!=', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get point transactions grouped by reservation for admin view
     */
    public function getPointTransactionsGroupedByReservation()
    {
        $transactions = PointTransaction::with(['guest', 'cast', 'reservation.cast', 'payment'])
            ->where('type', '!=', 'pending')
            ->whereNotNull('reservation_id')
            ->orderBy('created_at', 'desc')
            ->get();

        // Group transactions by reservation_id
        $groupedTransactions = $transactions->groupBy('reservation_id');

        $reservationSummaries = [];

        foreach ($groupedTransactions as $reservationId => $reservationTransactions) {
            $reservation = $reservationTransactions->first()->reservation;
            $guest = $reservationTransactions->first()->guest;

            // Get cast from reservation relationship or from transactions
            $cast = null;
            if ($reservation && $reservation->cast) {
                $cast = $reservation->cast;
            } else {
                // Try to get cast from any transaction that has cast_id
                $castTransaction = $reservationTransactions->where('cast_id', '!=', null)->first();
                if ($castTransaction) {
                    $cast = $castTransaction->cast;
                } else {
                    $cast = $reservationTransactions->first()->cast;
                }
            }


            // Calculate various point amounts
            $reservedPoints = $reservationTransactions->where('type', 'transfer')->sum('amount');
            $actualUsedPoints = $reservationTransactions->where('type', 'exceeded_pending')->where('amount', '>', 0)->sum('amount');
            $boughtPoints = $reservationTransactions->where('type', 'buy')->sum('amount');
            $exceededPoints = max(0, $actualUsedPoints - $reservedPoints);

            // Get payment information
            $paymentTransaction = $reservationTransactions->where('type', 'buy')->first();
            $payment = $paymentTransaction ? $paymentTransaction->payment : null;
            $paymentAmountYen = $payment ? $payment->amount : 0;

            $reservationSummaries[] = [
                'reservation_id' => $reservationId,
                'reservation' => $reservation,
                'guest' => $guest,
                'cast' => $cast,
                'reserved_points' => $reservedPoints,
                'actual_used_points' => $actualUsedPoints,
                'exceeded_points' => $exceededPoints,
                'bought_points' => $boughtPoints,
                'payment_amount_yen' => $paymentAmountYen,
                'payment_status' => $payment ? $payment->status : null,
                'payment_id' => $payment ? $payment->id : null,
                'stripe_payment_intent_id' => $payment ? $payment->stripe_payment_intent_id : null,
                'transactions' => $reservationTransactions->toArray(),
                'created_at' => $reservationTransactions->first()->created_at,
                'updated_at' => $reservationTransactions->max('updated_at')
            ];
        }

        // Sort by most recent first
        usort($reservationSummaries, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $reservationSummaries;
    }

    /**
     * Get all point transactions except pending type count for admin dashboard
     */
    public function getAllPointTransactionsExceptPendingCount(): int
    {
        return PointTransaction::where('type', '!=', 'pending')->count();
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

    /**
     * Send session completion message to guest via concierge system
     */
    private function sendSessionCompletionMessage(Reservation $reservation, Guest $guest, Cast $cast, int $totalPoints, int $reservedPoints, int $totalShortfall): void
    {
        try {
            // Calculate session duration
            $startedAt = \Carbon\Carbon::parse($reservation->started_at);
            $endedAt = \Carbon\Carbon::parse($reservation->ended_at);
            $durationSeconds = max(0, $startedAt->diffInSeconds($endedAt));
            $sessionMinutes = floor($durationSeconds / 60);
            $sessionSeconds = $durationSeconds % 60;
            $sessionTimeText = $sessionMinutes > 0 ? "{$sessionMinutes}分{$sessionSeconds}秒" : "{$sessionSeconds}秒";

            // Calculate reservation duration in minutes
            $reservationDurationMinutes = floor($reservation->duration * 60);

            // Calculate exceeded points and shortfall
            $exceededPoints = max(0, $totalPoints - $reservedPoints);
            $shortfallPoints = $totalShortfall;

            Log::info('Message generation debug', [
                'reservation_id' => $reservation->id,
                'total_points' => $totalPoints,
                'reserved_points' => $reservedPoints,
                'exceeded_points' => $exceededPoints,
                'total_shortfall' => $totalShortfall,
                'shortfall_points' => $shortfallPoints,
                'duration_seconds' => $durationSeconds,
                'session_time_text' => $sessionTimeText
            ]);

            // Build the concierge message
            $conciergeMessage = "【セッション終了レポート】\n\n" .
                "⏰ セッション時間:\n" .
                "• 予約時間: {$reservationDurationMinutes}分\n" .
                "• 実際の使用時間: {$sessionTimeText}\n\n" .
                "📊 ポイント使用状況:\n" .
                "• 予約時支払いポイント: " . number_format($reservedPoints) . "P\n" .
                "• 実際の使用ポイント: " . number_format($totalPoints) . "P\n" .
                "• キャスト獲得ポイント: " . number_format($totalPoints) . "P\n" .
                "• ゲスト返金ポイント: " . number_format(max(0, $reservedPoints - $totalPoints)) . "P\n\n";

            // Add payment information if there was a shortfall
            if ($shortfallPoints > 0) {
                // Get the actual payment amount from the database
                $paymentRecord = \App\Models\Payment::where('reservation_id', $reservation->id)
                    ->where('status', 'pending')
                    ->where('is_automatic', true)
                    ->first();

                $calculatedAmount = ceil($shortfallPoints * 1.2);
                $actualPaymentAmount = $paymentRecord ? $paymentRecord->amount : $calculatedAmount;

                Log::info('Payment amount calculation', [
                    'reservation_id' => $reservation->id,
                    'shortfall_points' => $shortfallPoints,
                    'calculated_amount' => $calculatedAmount,
                    'payment_record_found' => $paymentRecord ? true : false,
                    'actual_payment_amount' => $actualPaymentAmount,
                    'payment_record_amount' => $paymentRecord ? $paymentRecord->amount : null
                ]);

                $conciergeMessage .= "💳 決済情報:\n" .
                    "• 残りポイント不足のため、クレジットカードから自動決済を実行しました\n" .
                    "• 決済金額: " . number_format($actualPaymentAmount) . "円 (" . number_format($shortfallPoints) . "P × 1.2円)\n" .
                    "• 決済は承認済みですが、実際の引き落としは2日後に自動実行されます\n" .
                    "• 決済詳細はマイページの「決済履歴」でご確認いただけます\n\n" .
                    "📝 ポイント使用の詳細:\n" .
                    "• 予約時にお支払いいただいたポイント: " . number_format($reservedPoints) . "P\n" .
                    "• セッション中に実際に使用されたポイント: " . number_format($totalPoints) . "P\n" .
                    "• 超過分: " . number_format($exceededPoints) . "P\n" .
                    "• クレジットカードからの支払い: " . number_format($shortfallPoints) . "P\n\n";
            } else if ($exceededPoints > 0) {
                $conciergeMessage .= "📝 ポイント使用の詳細:\n" .
                    "• 予約時にお支払いいただいたポイント: " . number_format($reservedPoints) . "P\n" .
                    "• セッション中に実際に使用されたポイント: " . number_format($totalPoints) . "P\n" .
                    "• 超過分: " . number_format($exceededPoints) . "P\n" .
                    "• お持ちのポイントから支払い: " . number_format($exceededPoints) . "P\n\n";
            } else {
                $conciergeMessage .= "📝 ポイント使用の詳細:\n" .
                    "• 予約時にお支払いいただいたポイント: " . number_format($reservedPoints) . "P\n" .
                    "• セッション中に実際に使用されたポイント: " . number_format($totalPoints) . "P\n" .
                    "• 未使用分の返金: " . number_format(max(0, $reservedPoints - $totalPoints)) . "P\n\n";
            }

            $conciergeMessage .= "ご利用いただき、ありがとうございました！";

            // Send the message via the concierge system
            $conciergeController = app(\App\Http\Controllers\ConciergeController::class);
            $conciergeController->sendSystemMessage(new \Illuminate\Http\Request([
                'user_id' => $guest->id,
                'user_type' => 'guest',
                'message' => $conciergeMessage,
                'message_type' => 'system',
                'category' => 'session_report'
            ]));

            Log::info('Session completion message sent', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'total_points' => $totalPoints,
                'shortfall_points' => $shortfallPoints,
                'message_length' => strlen($conciergeMessage)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send session completion message', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
