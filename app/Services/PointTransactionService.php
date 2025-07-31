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
    const NIGHT_TIME_BONUS = 4000; // Bonus points for night time activities (12 AM - 6 AM)

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
            $nightTimeBonus = $this->calculateNightTimeBonus($reservation->created_at);
            $totalPointsForCast = $calculatedPoints;

            // Calculate unused points to refund
            $reservedPoints = $pendingTransaction->amount;
            $unusedPoints = $reservedPoints - $totalPointsForCast;

            // Add points to cast (including night time bonus)
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
            $pendingTransaction->description = "Reservation completion - {$reservation->duration} hours (Grade points: {$cast->grade_points}, Duration: " . ($reservation->duration * 60) . " minutes)" . ($nightTimeBonus > 0 ? " (Night time bonus: +{$nightTimeBonus})" : "");
            $pendingTransaction->save();

            // Update reservation with points earned (including night time bonus)
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
                'total_points_for_cast' => $totalPointsForCast,
                'reserved_points' => $reservedPoints,
                'unused_points_refunded' => $unusedPoints
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Point transaction failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Refund unused points for a completed reservation
     * This method can be called independently if needed
     */
    public function refundUnusedPoints(Reservation $reservation): bool
    {
        try {
            DB::beginTransaction();

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

            // Get the guest
            $guest = $reservation->guest;
            if (!$guest) {
                Log::error('Guest not found for refund', [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $reservation->guest_id
                ]);
                return false;
            }

            // Calculate actual points used
            $actualPointsUsed = $this->calculateReservationPoints($reservation);
            $reservedPoints = $pendingTransaction->amount;
            $unusedPoints = $reservedPoints - $actualPointsUsed;

            // Refund unused points to guest
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
                    'description' => "Reservation refund - unused points ({$unusedPoints} points)"
                ]);

                // Update the pending transaction
                $pendingTransaction->type = 'convert';
                $pendingTransaction->amount = $actualPointsUsed;
                $pendingTransaction->description = "Reservation completed - actual points used ({$actualPointsUsed} points)";
                $pendingTransaction->save();

                DB::commit();

                Log::info('Points refunded successfully', [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $guest->id,
                    'reserved_points' => $reservedPoints,
                    'actual_points_used' => $actualPointsUsed,
                    'unused_points_refunded' => $unusedPoints
                ]);

                return true;
            }

            DB::rollBack();
            return false;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Point refund failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Calculate night time bonus points (4000 points for activities after 12 AM)
     */
    public function calculateNightTimeBonus($scheduledAt): int
    {
        $hour = Carbon::parse($scheduledAt)->hour;
        return ($hour >= 0 && $hour < 6) ? self::NIGHT_TIME_BONUS : 0; // 12 AM to 6 AM
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
        
        // Add night time bonus based on scheduled start time
        $nightTimeBonus = $this->calculateNightTimeBonus($reservation->scheduled_at);
        $totalPoints = $calculatedPoints + $nightTimeBonus;
        
        Log::info('Point calculation for reservation', [
            'reservation_id' => $reservation->id,
            'cast_id' => $cast->id,
            'cast_grade_points' => $gradePoints,
            'duration_hours' => $reservation->duration,
            'duration_minutes' => $durationInMinutes,
            'calculated_points' => $calculatedPoints,
            'night_time_bonus' => $nightTimeBonus,
            'total_points' => $totalPoints
        ]);
        
        return $totalPoints;
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
        
        // Extract VIP counts from details
        preg_match('/VIP:(\d+)人/', $details, $vipMatches);
        preg_match('/ロイヤルVIP:(\d+)人/', $details, $royalVipMatches);
        preg_match('/プレミアム:(\d+)人/', $details, $premiumMatches);
        
        $vipCount = isset($vipMatches[1]) ? (int)$vipMatches[1] : 0;
        $royalVipCount = isset($royalVipMatches[1]) ? (int)$royalVipMatches[1] : 0;
        $premiumCount = isset($premiumMatches[1]) ? (int)$premiumMatches[1] : 0;
        
        // Calculate points based on VIP types and duration
        $duration = $reservation->duration ?? 1; // Default to 1 hour
        
        // Point rates per hour
        $royalVipRate = 25000; // ロイヤルVIP
        $vipRate = 14000;      // VIP
        $premiumRate = 9000;   // プレミアム
        
        $basePoints = ($royalVipCount * $royalVipRate + $vipCount * $vipRate + $premiumCount * $premiumRate) * $duration;
        
        // If no VIP details found, use a default calculation
        if ($basePoints === 0) {
            // Default: 9000 points per hour per person (assuming 1 person)
            $basePoints = 9000 * $duration;
        }
        
        // Add night time bonus based on scheduled start time
        $nightTimeBonus = $this->calculateNightTimeBonus($reservation->scheduled_at);
        
        return $basePoints + $nightTimeBonus;
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