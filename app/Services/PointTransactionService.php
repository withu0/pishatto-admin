<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Cast;
use App\Models\Reservation;
use App\Models\PointTransaction;
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

            // Get the guest and cast
            $guest = $reservation->guest;
            $cast = $reservation->cast;

            if (!$guest || !$cast) {
                Log::error('Guest or Cast not found for reservation', [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $reservation->guest_id,
                    'cast_id' => $reservation->cast_id
                ]);
                return false;
            }

            // Find the pending point transaction for this reservation
            $pendingTransaction = PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->first();

            if (!$pendingTransaction) {
                Log::error('No pending point transaction found for reservation', [
                    'reservation_id' => $reservation->id
                ]);
                return false;
            }

            // Calculate points based on cast's grade_points and reservation duration
            $calculatedPoints = $this->calculateReservationPoints($reservation);
            $nightTimeBonus = $this->calculateNightTimeBonus($reservation->started_at);
            $extensionFee = $this->calculateExtensionFee($reservation);
            $totalPointsForCast = $calculatedPoints + $nightTimeBonus + $extensionFee;

            // Calculate unused points to refund
            $reservedPoints = $pendingTransaction->amount;
            $unusedPoints = $reservedPoints - $totalPointsForCast;

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
                    'cast_id' => null,
                    'type' => 'convert',
                    'amount' => $unusedPoints,
                    'reservation_id' => $reservation->id,
                    'description' => "Reservation completion - refunded unused points ({$unusedPoints} points)"
                ]);
            }

            // Update the pending transaction to completed
            $pendingTransaction->type = 'transfer';
            $pendingTransaction->amount = $totalPointsForCast;
            $pendingTransaction->description = "Reservation completion - {$reservation->duration} hours (Grade points: {$cast->grade_points}, Duration: " . ($reservation->duration * 60) . " minutes)" . 
                ($nightTimeBonus > 0 ? " (Night time bonus: +{$nightTimeBonus})" : "") .
                ($extensionFee > 0 ? " (Extension fee: +{$extensionFee})" : "");
            $pendingTransaction->save();

            // Update reservation with points earned (including night time bonus and extension fee)
            $reservation->points_earned = $totalPointsForCast;
            $reservation->save();

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
                'unused_points' => $unusedPoints
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

            // Create pending transaction
            PointTransaction::create([
                'guest_id' => $guest->id,
                'cast_id' => null,
                'type' => 'pending',
                'amount' => $requiredPoints,
                'reservation_id' => $reservation->id,
                'description' => "Free call - {$reservation->duration} hours (pending)"
            ]);

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

            // Find the pending point transaction for this reservation
            $pendingTransaction = PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->first();

            if (!$pendingTransaction) {
                Log::error('No pending point transaction found for refund', [
                    'reservation_id' => $reservation->id
                ]);
                return false;
            }

            $reservedPoints = $pendingTransaction->amount;

            // Refund all reserved points to guest
            $guest->points += $reservedPoints;
            $guest->save();

            // Update the pending transaction
            $pendingTransaction->type = 'convert';
            $pendingTransaction->amount = $reservedPoints;
            $pendingTransaction->description = "Reservation cancelled - refunded all points ({$reservedPoints} points)";
            $pendingTransaction->save();

            DB::commit();

            Log::info('Points refunded successfully', [
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'refunded_points' => $reservedPoints,
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