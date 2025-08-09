<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Cast;
use App\Models\Reservation;
use App\Models\PointTransaction;
use App\Services\GradeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PointTransactionService
{
    const NIGHT_TIME_BONUS = 4000; // Bonus points for night time activities (12 AM - 5 AM)
    const EXTENSION_MULTIPLIER = 1.5; // Extension fee multiplier

    /**
     * Process point transaction when a reservation is completed
     * Calculates points based on cast's grade_points and adds to cast
     * Also refunds unused points to guest
     */
    public function processReservationCompletion(Reservation $reservation): bool
    {
        try {
            DB::beginTransaction();

            // Get the guest
            $guest = $reservation->guest;
            if (!$guest) {
                Log::error('Guest not found for reservation', [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $reservation->guest_id,
                ]);
                return false;
            }

            // Find all pending point transactions for this reservation
            $pendingTransactions = PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->get();

            if ($pendingTransactions->isEmpty()) {
                Log::error('No pending point transaction found for reservation', [
                    'reservation_id' => $reservation->id
                ]);
                return false;
            }

            // If multiple pending transactions exist, treat as multi-cast reservation
            if ($pendingTransactions->count() > 1) {
                // Calculate totals based on reserved amounts and time-based extras
                $reservedPointsTotal = (int) $pendingTransactions->sum('amount');

                $nightTimeBonus = $this->calculateNightTimeBonus($reservation->started_at);

                // Calculate extension fee for multi-cast by deriving base per-minute from reserved total
                $extensionFee = 0;
                if ($reservation->started_at && $reservation->ended_at && $reservation->duration) {
                    $startedAt = Carbon::parse($reservation->started_at);
                    $endedAt = Carbon::parse($reservation->ended_at);
                    $scheduledDuration = (int) ($reservation->duration * 60);
                    $actualDuration = $endedAt->diffInMinutes($startedAt);
                    if ($scheduledDuration > 0 && $actualDuration > $scheduledDuration) {
                        $exceededMinutes = $actualDuration - $scheduledDuration;
                        $basePointsPerMinute = $reservedPointsTotal / $scheduledDuration;
                        $extensionFee = (int) round($basePointsPerMinute * $exceededMinutes * self::EXTENSION_MULTIPLIER);
                    }
                }

                $extrasTotal = $nightTimeBonus + $extensionFee;

                // Compute per-cast allocations for extras proportionally by their reserved amount
                $perCastExtras = [];
                $allocatedExtras = 0;
                $pendingArray = $pendingTransactions->values();
                $numPending = $pendingArray->count();
                foreach ($pendingArray as $index => $pending) {
                    if ($extrasTotal > 0 && $reservedPointsTotal > 0) {
                        $share = ($pending->amount / $reservedPointsTotal) * $extrasTotal;
                        $extra = (int) floor($share);
                        // Assign remainder to last to ensure sums match
                        if ($index === $numPending - 1) {
                            $extra = $extrasTotal - $allocatedExtras;
                        }
                        $perCastExtras[$pending->id] = $extra;
                        $allocatedExtras += $extra;
                    } else {
                        $perCastExtras[$pending->id] = 0;
                    }
                }

                // Calculate total shortfall to deduct from guest
                $totalShortfall = array_sum($perCastExtras);
                if ($totalShortfall > 0) {
                    if ($guest->points < $totalShortfall) {
                        Log::error('Insufficient guest points to cover multi-cast shortfall', [
                            'reservation_id' => $reservation->id,
                            'guest_id' => $guest->id,
                            'required_shortfall' => $totalShortfall,
                            'available_points' => $guest->points,
                        ]);
                        DB::rollBack();
                        return false;
                    }

                    // Deduct once; we will still record per-cast transfer entries
                    $guest->points -= $totalShortfall;
                    $guest->save();
                }

                $pointsEarnedTotal = 0;

                // Convert each pending and credit each cast
                foreach ($pendingArray as $pending) {
                    $castId = $pending->cast_id;
                    $cast = Cast::find($castId);
                    if (!$cast) {
                        Log::warning('Cast not found during multi-cast completion, skipping one entry', [
                            'reservation_id' => $reservation->id,
                            'cast_id' => $castId,
                        ]);
                        continue;
                    }

                    $extra = $perCastExtras[$pending->id] ?? 0;
                    $perCastTotal = (int) $pending->amount + (int) $extra;

                    // Credit cast
                    $cast->points += $perCastTotal;
                    $cast->save();

                    // Convert pending to transfer for the reserved portion
                    $pending->type = 'transfer';
                    $pending->amount = (int) $pending->amount; // keep original reserved amount
                    $pending->description = "Reservation completion (multi-cast) - converted reserved points (Duration: " . ($reservation->duration * 60) . " minutes)" .
                        ($nightTimeBonus > 0 ? " (Night time bonus allocated)" : "") .
                        ($extensionFee > 0 ? " (Extension fee allocated)" : "");
                    $pending->save();

                    // Record extra (shortfall) as separate transfer if any
                    if ($extra > 0) {
                        PointTransaction::create([
                            'guest_id' => $guest->id,
                            'cast_id' => $cast->id,
                            'type' => 'transfer',
                            'amount' => $extra,
                            'reservation_id' => $reservation->id,
                            'description' => "Reservation completion (multi-cast) - extension/night bonus shortfall covered by guest (+{$extra} points)",
                        ]);
                    }

                    $pointsEarnedTotal += $perCastTotal;
                }

                // Update reservation with total points earned across all casts
                $reservation->points_earned = $pointsEarnedTotal;
                $reservation->save();

                // Recalculate guest grade points after applying transfers
                /** @var GradeService $gradeService */
                $gradeService = app(GradeService::class);
                $gradeService->calculateAndUpdateGrade($guest);

                DB::commit();

                Log::info('Multi-cast reservation completion processed successfully', [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $guest->id,
                    'points_reserved_total' => $reservedPointsTotal,
                    'night_time_bonus' => $nightTimeBonus,
                    'extension_fee' => $extensionFee,
                    'total_shortfall' => $totalShortfall,
                    'points_earned_total' => $pointsEarnedTotal,
                ]);

                return true;
            }

            // Single-cast path continues below
            $cast = $reservation->cast;
            if (!$cast) {
                Log::error('Cast not found for reservation', [
                    'reservation_id' => $reservation->id,
                    'cast_id' => $reservation->cast_id,
                ]);
                return false;
            }

            // Find the pending point transaction for this reservation and cast
            $pendingTransaction = $pendingTransactions->first();

            // Calculate points based on cast's grade_points and reservation duration
            $calculatedPoints = $this->calculateReservationPoints($reservation);
            $nightTimeBonus = $this->calculateNightTimeBonus($reservation->started_at);
            $extensionFee = $this->calculateExtensionFee($reservation);
            $totalPointsForCast = $calculatedPoints + $nightTimeBonus + $extensionFee;

            // Calculate unused points (refund) or shortfall (extra deduction)
            $reservedPoints = $pendingTransaction->amount;
            $unusedPoints = $reservedPoints - $totalPointsForCast;
            $shortfallPoints = $totalPointsForCast - $reservedPoints;

            // If there is a shortfall (e.g., due to extension), deduct from guest now
            if ($shortfallPoints > 0) {
                if ($guest->points < $shortfallPoints) {
                    Log::error('Insufficient guest points to cover extension shortfall', [
                        'reservation_id' => $reservation->id,
                        'guest_id' => $guest->id,
                        'required_shortfall' => $shortfallPoints,
                        'available_points' => $guest->points,
                    ]);
                    DB::rollBack();
                    return false;
                }

                // Deduct shortfall from guest's owned points
                $guest->points -= $shortfallPoints;
                $guest->save();

                // Record the extra deduction as a separate transfer entry for auditability
                PointTransaction::create([
                    'guest_id' => $guest->id,
                    'cast_id' => $cast->id,
                    'type' => 'transfer',
                    'amount' => $shortfallPoints,
                    'reservation_id' => $reservation->id,
                    'description' => "Reservation completion - extension shortfall covered by guest (+{$shortfallPoints} points)",
                ]);
            }

            // Add points to cast (including night time bonus and extension fee)
            $cast->points += $totalPointsForCast;
            $cast->save();

            // Refund unused points to guest if any
            if ($unusedPoints > 0) {
                $guest->points += $unusedPoints;
                $guest->save();

                // Create refund transaction record
                PointTransaction::create([
                    'guest_id' => $guest->id,
                    'cast_id' => $cast->id,
                    'type' => 'convert',
                    'amount' => $unusedPoints,
                    'reservation_id' => $reservation->id,
                    'description' => "Reservation completion - refunded unused points ({$unusedPoints} points)"
                ]);
            }

            // Update the pending transaction to completed
            $pendingTransaction->type = 'transfer';
            if ($shortfallPoints > 0) {
                // Keep the original reserved amount on the converted record; shortfall recorded separately
                $pendingTransaction->amount = $reservedPoints;
                $pendingTransaction->description = "Reservation completion - converted reserved points (Grade points: {$cast->grade_points}, Duration: " . ($reservation->duration * 60) . " minutes)" .
                    ($nightTimeBonus > 0 ? " (Night time bonus: +{$nightTimeBonus})" : "") .
                    ($extensionFee > 0 ? " (Extension fee: +{$extensionFee})" : "") .
                    " (Additional shortfall: +{$shortfallPoints})";
            } else {
                // No shortfall; transfer equals total
                $pendingTransaction->amount = $totalPointsForCast;
                $pendingTransaction->description = "Reservation completion - {$reservation->duration} hours (Grade points: {$cast->grade_points}, Duration: " . ($reservation->duration * 60) . " minutes)" . 
                    ($nightTimeBonus > 0 ? " (Night time bonus: +{$nightTimeBonus})" : "") .
                    ($extensionFee > 0 ? " (Extension fee: +{$extensionFee})" : "");
            }
            $pendingTransaction->save();

            // Update reservation with points earned (including night time bonus and extension fee)
            $reservation->points_earned = $totalPointsForCast;
            $reservation->save();

            // Recalculate guest grade points after applying transfer/refund
            /** @var GradeService $gradeService */
            $gradeService = app(GradeService::class);
            $gradeService->calculateAndUpdateGrade($guest);

            DB::commit();

            Log::info('Point transaction completed successfully', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'cast_id' => $cast->id,
                'grade_points' => $cast->grade_points,
                'duration_minutes' => $reservation->duration * 60,
                'calculated_points' => $calculatedPoints,
                'night_time_bonus' => $nightTimeBonus,
                'extension_fee' => $extensionFee,
                'total_points' => $totalPointsForCast,
                'unused_points' => $unusedPoints,
                'shortfall_points' => $shortfallPoints > 0 ? $shortfallPoints : 0,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process reservation completion', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Process free call creation - deduct points from guest and create pending transaction
     */
    public function processFreeCallCreation(Reservation $reservation, int $requiredPoints): bool
    {
        try {
            DB::beginTransaction();

            $guest = $reservation->guest;
            if (!$guest) {
                Log::error('Guest not found for free call', [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $reservation->guest_id
                ]);
                return false;
            }

            // Check if guest has enough points
            if ($guest->points < $requiredPoints) {
                Log::error('Insufficient points for free call', [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $guest->id,
                    'required_points' => $requiredPoints,
                    'available_points' => $guest->points
                ]);
                return false;
            }

            // Deduct points from guest
            $guest->points -= $requiredPoints;
            $guest->save();

            // Create pending transaction(s)
            $castIds = [];
            if (is_array($reservation->cast_ids) && !empty($reservation->cast_ids)) {
                $castIds = $reservation->cast_ids;
            } elseif (!empty($reservation->cast_id)) {
                $castIds = [$reservation->cast_id];
            }

            if (!empty($castIds)) {
                $numCasts = count($castIds);
                $baseShare = intdiv($requiredPoints, $numCasts);
                $remainder = $requiredPoints % $numCasts;

                foreach (array_values($castIds) as $index => $castId) {
                    $amount = $baseShare + ($index < $remainder ? 1 : 0);
                    PointTransaction::create([
                        'guest_id' => $guest->id,
                        'cast_id' => $castId,
                        'type' => 'pending',
                        'amount' => $amount,
                        'reservation_id' => $reservation->id,
                        'description' => "Free call - {$reservation->duration} hours (pending)"
                    ]);
                }
            } else {
                // If no cast(s) assigned yet, defer creating pending records until casts are selected
            }

            DB::commit();

            Log::info('Free call points deducted successfully', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'points_deducted' => $requiredPoints,
                'remaining_points' => $guest->points
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process free call creation', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Refund unused points for a reservation
     */
    public function refundUnusedPoints(Reservation $reservation): bool
    {
        try {
            DB::beginTransaction();

            $guest = $reservation->guest;
            if (!$guest) {
                Log::error('Guest not found for refund', [
                    'reservation_id' => $reservation->id
                ]);
                return false;
            }

            // Find all pending point transactions for this reservation (may be multiple casts)
            $pendingTransactions = PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->get();

            if ($pendingTransactions->isEmpty()) {
                Log::error('No pending point transaction found for refund', [
                    'reservation_id' => $reservation->id
                ]);
                return false;
            }

            $totalReserved = 0;
            foreach ($pendingTransactions as $pending) {
                $totalReserved += $pending->amount;
                $pending->type = 'convert';
                // keep original amount and cast_id per pending record
                $pending->description = "Reservation cancelled - refunded all points ({$pending->amount} points)";
                $pending->save();
            }

            // Refund all reserved points (sum) to guest
            $guest->points += $totalReserved;
            $guest->save();

            // Recalculate guest grade points after full refund
            /** @var GradeService $gradeService */
            $gradeService = app(GradeService::class);
            $gradeService->calculateAndUpdateGrade($guest);

            DB::commit();

            Log::info('Points refunded successfully', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'refunded_points' => $totalReserved,
                'new_guest_points' => $guest->points
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to refund unused points', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Calculate night time bonus points (4000 points for activities between 12 AM and 5 AM)
     */
    public function calculateNightTimeBonus($startedAt): int
    {
        if (!$startedAt) {
            return 0;
        }
        
        $hour = Carbon::parse($startedAt)->hour;
        return ($hour >= 0 && $hour < 5) ? self::NIGHT_TIME_BONUS : 0; // 12 AM to 5 AM
    }

    /**
     * Calculate extension fee for exceeded time (1.5 times the original amount)
     */
    public function calculateExtensionFee(Reservation $reservation): int
    {
        if (!$reservation->started_at || !$reservation->ended_at) {
            return 0;
        }

        $startedAt = Carbon::parse($reservation->started_at);
        $endedAt = Carbon::parse($reservation->ended_at);
        $scheduledDuration = $reservation->duration * 60; // Convert hours to minutes
        $actualDuration = $endedAt->diffInMinutes($startedAt);
        
        // If actual duration exceeds scheduled duration, calculate extension fee
        if ($actualDuration > $scheduledDuration) {
            $exceededMinutes = $actualDuration - $scheduledDuration;
            $cast = $reservation->cast;
            
            if (!$cast) {
                return 0;
            }

            // Calculate base points per minute
            $basePointsPerMinute = $cast->grade_points / 30; // Based on 30-minute intervals
            
            // Calculate extension fee: 1.5 times the original amount for exceeded time
            $extensionFee = $basePointsPerMinute * $exceededMinutes * self::EXTENSION_MULTIPLIER;
            
            Log::info('Extension fee calculation', [
                'reservation_id' => $reservation->id,
                'scheduled_duration_minutes' => $scheduledDuration,
                'actual_duration_minutes' => $actualDuration,
                'exceeded_minutes' => $exceededMinutes,
                'base_points_per_minute' => $basePointsPerMinute,
                'extension_fee' => $extensionFee
            ]);
            
            return (int)$extensionFee;
        }
        
        return 0;
    }

    /**
     * Calculate points for a reservation based on cast's grade_points and duration
     * Formula: grade_points * (duration_in_minutes / 30)
     */
    public function calculateReservationPoints(Reservation $reservation): int
    {
        $cast = $reservation->cast;
        if (!$cast) {
            Log::warning('Cast not found for reservation point calculation', [
                'reservation_id' => $reservation->id,
                'cast_id' => $reservation->cast_id
            ]);
            return 0;
        }

        // Calculate points based on cast's grade_points and reservation duration
        // Formula: grade_points * (duration_in_minutes / 30)
        $durationInMinutes = ($reservation->duration ?? 1) * 60; // Convert hours to minutes
        $gradePoints = $cast->grade_points ?? 0;
        $calculatedPoints = $gradePoints * ($durationInMinutes / 30);
        
        Log::info('Point calculation for reservation', [
            'reservation_id' => $reservation->id,
            'cast_id' => $cast->id,
            'cast_grade_points' => $gradePoints,
            'duration_hours' => $reservation->duration,
            'duration_minutes' => $durationInMinutes,
            'calculated_points' => $calculatedPoints
        ]);
        
        return $calculatedPoints;
    }

    /**
     * Calculate points for a reservation based on duration and other factors (legacy method)
     */
    public function calculateReservationPointsLegacy(Reservation $reservation): int
    {
        // Base calculation based on reservation details
        $basePoints = 0;
        
        // Parse details to extract VIP counts
        $details = $reservation->details ?? '';
        
        \Log::info('Calculating points for reservation', [
            'reservation_id' => $reservation->id,
            'details' => $details,
            'duration' => $reservation->duration
        ]);
        
        // Extract VIP counts from details
        preg_match('/VIP:(\d+)人/', $details, $vipMatches);
        preg_match('/ロイヤルVIP:(\d+)人/', $details, $royalVipMatches);
        preg_match('/プレミアム:(\d+)人/', $details, $premiumMatches);
        
        $vipCount = isset($vipMatches[1]) ? (int)$vipMatches[1] : 0;
        $royalVipCount = isset($royalVipMatches[1]) ? (int)$royalVipMatches[1] : 0;
        $premiumCount = isset($premiumMatches[1]) ? (int)$premiumMatches[1] : 0;
        
        \Log::info('VIP counts extracted', [
            'vip_count' => $vipCount,
            'royal_vip_count' => $royalVipCount,
            'premium_count' => $premiumCount
        ]);
        
        // Calculate points based on VIP types and duration
        $duration = $reservation->duration ?? 1; // Default to 1 hour
        
        // Point rates per hour
        $royalVipRate = 30000; // ロイヤルVIP
        $vipRate = 24000;      // VIP
        $premiumRate = 18000;   // プレミアム
        
        $basePoints = ($royalVipCount * $royalVipRate + $vipCount * $vipRate + $premiumCount * $premiumRate) * $duration;
        
        // If no VIP details found, use a default calculation
        if ($basePoints === 0) {
            // Default: 9000 points per hour per person (assuming 1 person)
            $basePoints = 9000 * $duration;
        }
        
        \Log::info('Point calculation result', [
            'reservation_id' => $reservation->id,
            'base_points' => $basePoints
        ]);
        
        return $basePoints;
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
} 