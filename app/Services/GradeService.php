<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Cast;
use App\Models\PointTransaction;
use App\Models\Reservation;
use App\Models\Feedback;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GradeService
{
    // Grade thresholds based on the screenshots
    const GRADE_THRESHOLDS = [
        'green' => 0,      // Default grade
        'orange' => 100000, // 100,000P
        'bronze' => 300000, // 300,000P
        'silver' => 500000, // 500,000P
        'gold' => 1000000,  // 1,000,000P
        'platinum' => 6000000, // 6,000,000P
        'centurion' => 30000000, // 30,000,000P
    ];

    /**
     * Normalize guest grade to valid keys
     */
    private function normalizeGuestGrade(string $grade): string
    {
        $legacyMap = [
            'blue' => 'green',
        ];

        $normalized = $legacyMap[$grade] ?? $grade;

        return array_key_exists($normalized, self::GRADE_NAMES) ? $normalized : 'green';
    }

    /**
     * Normalize cast grade to valid keys
     */
    private function normalizeCastGrade(string $grade): string
    {
        $legacyMap = [
            'blue' => 'green',
        ];

        $normalized = $legacyMap[$grade] ?? $grade;

        return array_key_exists($normalized, self::CAST_GRADE_NAMES) ? $normalized : 'beginner';
    }

    // Grade display names in Japanese
    const GRADE_NAMES = [
        'green' => 'グリーン',
        'orange' => 'オレンジ',
        'bronze' => 'ブロンズ',
        'silver' => 'シルバー',
        'gold' => 'ゴールド',
        'platinum' => 'プラチナ',
        'centurion' => 'センチュリオン',
    ];

    // Grade benefits based on the screenshots
    const GRADE_BENEFITS = [
        'green' => [
            'chat_background' => false,
            'tweet_grade_display' => false,
            'grade_gift' => false,
            'birthday_gift' => false,
            'private_settings_expansion' => false,
            'dedicated_concierge' => false,
            'class_up_rights' => false,
        ],
        'orange' => [
            'chat_background' => false,
            'tweet_grade_display' => false,
            'grade_gift' => false,
            'birthday_gift' => false,
            'private_settings_expansion' => false,
            'dedicated_concierge' => false,
            'class_up_rights' => false,
        ],
        'bronze' => [
            'chat_background' => false,
            'tweet_grade_display' => false,
            'grade_gift' => false,
            'birthday_gift' => false,
            'private_settings_expansion' => false,
            'dedicated_concierge' => false,
            'class_up_rights' => false,
        ],
        'silver' => [
            'chat_background' => true,
            'tweet_grade_display' => true,
            'grade_gift' => true,
            'birthday_gift' => false,
            'private_settings_expansion' => false,
            'dedicated_concierge' => false,
            'class_up_rights' => false,
        ],
        'gold' => [
            'chat_background' => true,
            'tweet_grade_display' => true,
            'grade_gift' => true,
            'birthday_gift' => true,
            'private_settings_expansion' => true,
            'dedicated_concierge' => false, // Only for Luxury Gold
            'class_up_rights' => false,
        ],
        'platinum' => [
            'chat_background' => true,
            'tweet_grade_display' => true,
            'grade_gift' => true,
            'birthday_gift' => true,
            'private_settings_expansion' => true,
            'dedicated_concierge' => true,
            'class_up_rights' => true,
        ],
        'centurion' => [
            'chat_background' => true,
            'tweet_grade_display' => true,
            'grade_gift' => true,
            'birthday_gift' => true,
            'private_settings_expansion' => true,
            'dedicated_concierge' => true,
            'class_up_rights' => true,
        ],
    ];

    // Cast grade thresholds (FP-based)
    const CAST_GRADE_THRESHOLDS = [
        'beginner' => 0,      // Default grade
        'green' => 500000,    // 500,000 FP
        'orange' => 1000000,  // 1,000,000 FP
        'bronze' => 2000000,  // 2,000,000 FP
        'silver' => 5000000,  // 5,000,000 FP
        'gold' => 10000000,   // 10,000,000 FP
        'platinum' => 30000000, // 30,000,000 FP
    ];

    // Cast grade display names in Japanese
    const CAST_GRADE_NAMES = [
        'beginner' => 'ビギナー',
        'green' => 'グリーン',
        'orange' => 'オレンジ',
        'bronze' => 'ブロンズ',
        'silver' => 'シルバー',
        'gold' => 'ゴールド',
        'platinum' => 'プラチナ',
    ];

    // Cast grade benefits
    const CAST_GRADE_BENEFITS = [
        'beginner' => [
            'easy_message' => false,
            'suspension_system' => false,
            'auto_goodbye_message' => false,
            'welcome_message' => false,
            'transfer_fee_discount' => false,
        ],
        'green' => [
            'easy_message' => false,
            'suspension_system' => false,
            'auto_goodbye_message' => false,
            'welcome_message' => false,
            'transfer_fee_discount' => false,
        ],
        'orange' => [
            'easy_message' => true,
            'suspension_system' => false,
            'auto_goodbye_message' => false,
            'welcome_message' => false,
            'transfer_fee_discount' => false,
        ],
        'bronze' => [
            'easy_message' => true,
            'suspension_system' => true,
            'auto_goodbye_message' => false,
            'welcome_message' => false,
            'transfer_fee_discount' => false,
        ],
        'silver' => [
            'easy_message' => true,
            'suspension_system' => true,
            'auto_goodbye_message' => true,
            'welcome_message' => false,
            'transfer_fee_discount' => false,
        ],
        'gold' => [
            'easy_message' => true,
            'suspension_system' => true,
            'auto_goodbye_message' => true,
            'welcome_message' => true,
            'transfer_fee_discount' => true,
        ],
        'platinum' => [
            'easy_message' => true,
            'suspension_system' => true,
            'auto_goodbye_message' => true,
            'welcome_message' => true,
            'transfer_fee_discount' => true,
        ],
    ];

    /**
     * Calculate and update guest grade based on their usage points
     */
    public function calculateAndUpdateGrade(Guest $guest): array
    {
        $currentGrade = $guest->grade ?? 'green';
        $currentGradePoints = $guest->grade_points ?? 0;
        
        // Calculate total usage points from point transactions
        $usagePoints = $this->calculateUsagePoints($guest);
        
        // Determine new grade based on usage points
        $newGrade = $this->determineGrade($usagePoints);
        
        // Check if grade should be updated
        if ($newGrade !== $currentGrade) {
            $guest->update([
                'grade' => $newGrade,
                'grade_points' => $usagePoints,
                'grade_updated_at' => now(),
            ]);
            
            return [
                'old_grade' => $currentGrade,
                'new_grade' => $newGrade,
                'grade_points' => $usagePoints,
                'upgraded' => true,
            ];
        }
        
        // Update grade points even if grade didn't change
        if ($usagePoints !== $currentGradePoints) {
            $guest->update([
                'grade_points' => $usagePoints,
            ]);
        }
        
        return [
            'old_grade' => $currentGrade,
            'new_grade' => $currentGrade,
            'grade_points' => $usagePoints,
            'upgraded' => false,
        ];
    }

    /**
     * Calculate usage points from point transactions
     */
    public function calculateUsagePoints(Guest $guest): int
    {
        // Sum all point transactions representing actual spending
        $spent = \App\Models\PointTransaction::where('guest_id', $guest->id)
            ->whereIn('type', ['pending', 'transfer'])
            ->sum('amount');
        // Subtract all refund transactions
        $refunded = \App\Models\PointTransaction::where('guest_id', $guest->id)
            ->where('type', 'convert')
            ->sum('amount');
        return max(0, $spent - $refunded);
    }

    /**
     * Determine grade based on usage points
     */
    public function determineGrade(int $usagePoints): string
    {
        if ($usagePoints >= self::GRADE_THRESHOLDS['centurion']) {
            return 'centurion';
        } elseif ($usagePoints >= self::GRADE_THRESHOLDS['platinum']) {
            return 'platinum';
        } elseif ($usagePoints >= self::GRADE_THRESHOLDS['gold']) {
            return 'gold';
        } elseif ($usagePoints >= self::GRADE_THRESHOLDS['silver']) {
            return 'silver';
        } elseif ($usagePoints >= self::GRADE_THRESHOLDS['bronze']) {
            return 'bronze';
        } elseif ($usagePoints >= self::GRADE_THRESHOLDS['orange']) {
            return 'orange';
        } else {
            return 'green';
        }
    }

    /**
     * Get grade information for a guest
     */
    public function getGradeInfo(Guest $guest): array
    {
        $grade = $this->normalizeGuestGrade($guest->grade ?? 'green');
        $gradePoints = $guest->grade_points ?? 0;
        
        // Calculate next grade threshold
        $nextGrade = $this->getNextGrade($grade);
        $nextGradeThreshold = $nextGrade ? self::GRADE_THRESHOLDS[$nextGrade] : null;
        $pointsToNextGrade = $nextGradeThreshold ? $nextGradeThreshold - $gradePoints : 0;
        
        return [
            'current_grade' => $grade,
            'current_grade_name' => self::GRADE_NAMES[$grade],
            'grade_points' => $gradePoints,
            'next_grade' => $nextGrade,
            'next_grade_name' => $nextGrade ? self::GRADE_NAMES[$nextGrade] : null,
            'points_to_next_grade' => $pointsToNextGrade,
            'benefits' => self::GRADE_BENEFITS[$grade],
            'all_benefits' => self::GRADE_BENEFITS,
            'grade_names' => self::GRADE_NAMES,
        ];
    }

    /**
     * Get the next grade level
     */
    public function getNextGrade(string $currentGrade): ?string
    {
        $grades = array_keys(self::GRADE_THRESHOLDS);
        $currentIndex = array_search($currentGrade, $grades);
        
        if ($currentIndex === false || $currentIndex >= count($grades) - 1) {
            return null;
        }
        
        return $grades[$currentIndex + 1];
    }

    /**
     * Get grade benefits for a specific grade
     */
    public function getGradeBenefits(string $grade): array
    {
        $grade = $this->normalizeGuestGrade($grade);
        return self::GRADE_BENEFITS[$grade] ?? [];
    }

    /**
     * Get all grade information
     */
    public function getAllGradesInfo(): array
    {
        return [
            'thresholds' => self::GRADE_THRESHOLDS,
            'names' => self::GRADE_NAMES,
            'benefits' => self::GRADE_BENEFITS,
        ];
    }

    /**
     * Update grades for all guests (batch process)
     */
    public function updateAllGuestGrades(): array
    {
        $results = [
            'total_guests' => 0,
            'upgraded_guests' => 0,
            'upgrades' => [],
        ];

        Guest::chunk(100, function ($guests) use (&$results) {
            foreach ($guests as $guest) {
                $results['total_guests']++;
                $gradeUpdate = $this->calculateAndUpdateGrade($guest);
                
                if ($gradeUpdate['upgraded']) {
                    $results['upgraded_guests']++;
                    $results['upgrades'][] = [
                        'guest_id' => $guest->id,
                        'nickname' => $guest->nickname,
                        'old_grade' => $gradeUpdate['old_grade'],
                        'new_grade' => $gradeUpdate['new_grade'],
                        'grade_points' => $gradeUpdate['grade_points'],
                    ];
                }
            }
        });

        return $results;
    }

    /**
     * Calculate and update cast grade based on their FP (Friend Points)
     */
    public function calculateAndUpdateCastGrade(Cast $cast): array
    {
        $currentGrade = $cast->grade ?? 'beginner';
        $currentGradePoints = $cast->grade_points ?? 0;
        
        // Calculate total FP from various sources
        $totalFP = $this->calculateCastFP($cast);
        
        // Determine new grade based on FP
        $newGrade = $this->determineCastGrade($totalFP);
        
        // Check if grade should be updated
        if ($newGrade !== $currentGrade) {
            $cast->update([
                'grade' => $newGrade,
                'grade_points' => $totalFP,
                'grade_updated_at' => now(),
            ]);
            
            return [
                'old_grade' => $currentGrade,
                'new_grade' => $newGrade,
                'grade_points' => $totalFP,
                'upgraded' => true,
            ];
        }
        
        // Update grade points even if grade didn't change
        if ($totalFP !== $currentGradePoints) {
            $cast->update([
                'grade_points' => $totalFP,
            ]);
        }
        
        return [
            'old_grade' => $currentGrade,
            'new_grade' => $currentGrade,
            'grade_points' => $totalFP,
            'upgraded' => false,
        ];
    }

    /**
     * Calculate cast FP from various sources
     */
    public function calculateCastFP(Cast $cast): int
    {
        $totalFP = 0;
        
        // Get reservations for this cast
        $reservations = Reservation::where('cast_ids', 'like', '%' . $cast->id . '%')
            ->orWhere('cast_ids', 'like', $cast->id . ',%')
            ->orWhere('cast_ids', 'like', '%,' . $cast->id)
            ->orWhere('cast_ids', $cast->id)
            ->get();
        
        foreach ($reservations as $reservation) {
            // Base FP from reservation duration and cast category
            $duration = $reservation->duration ?? 1;
            $categoryPoints = $this->getCastCategoryPoints($cast->category ?? 'プレミアム');
            $baseFP = $categoryPoints * $duration * 60 / 30; // Convert to FP
            
            // Add bonus FP for repeat guests
            $repeatBonus = $this->calculateRepeatBonus($cast->id, $reservation->guest_id);
            
            // Add bonus FP for positive feedback
            $feedbackBonus = $this->calculateFeedbackBonus($cast->id, $reservation->id);
            
            $totalFP += $baseFP + $repeatBonus + $feedbackBonus;
        }
        
        return $totalFP;
    }

    /**
     * Get cast category points
     */
    private function getCastCategoryPoints(string $category): int
    {
        switch ($category) {
            case 'ロイヤルVIP':
                return 15000;
            case 'VIP':
                return 12000;
            case 'プレミアム':
            default:
                return 9000;
        }
    }

    /**
     * Calculate repeat bonus FP
     */
    private function calculateRepeatBonus(int $castId, int $guestId): int
    {
        $repeatCount = Reservation::where('cast_ids', 'like', '%' . $castId . '%')
            ->where('guest_id', $guestId)
            ->count();
        
        if ($repeatCount > 1) {
            return 50000 * ($repeatCount - 1); // 50,000 FP per repeat
        }
        
        return 0;
    }

    /**
     * Calculate feedback bonus FP
     */
    private function calculateFeedbackBonus(int $castId, int $reservationId): int
    {
        $feedback = Feedback::where('cast_id', $castId)
            ->where('reservation_id', $reservationId)
            ->first();
        
        if ($feedback && $feedback->rating >= 4) {
            return 100000; // 100,000 FP for good feedback
        }
        
        return 0;
    }

    /**
     * Determine cast grade based on FP
     */
    public function determineCastGrade(int $fp): string
    {
        if ($fp >= self::CAST_GRADE_THRESHOLDS['platinum']) {
            return 'platinum';
        } elseif ($fp >= self::CAST_GRADE_THRESHOLDS['gold']) {
            return 'gold';
        } elseif ($fp >= self::CAST_GRADE_THRESHOLDS['silver']) {
            return 'silver';
        } elseif ($fp >= self::CAST_GRADE_THRESHOLDS['bronze']) {
            return 'bronze';
        } elseif ($fp >= self::CAST_GRADE_THRESHOLDS['orange']) {
            return 'orange';
        } elseif ($fp >= self::CAST_GRADE_THRESHOLDS['green']) {
            return 'green';
        } else {
            return 'beginner';
        }
    }

    /**
     * Get cast grade information
     */
    public function getCastGradeInfo(Cast $cast): array
    {
        $grade = $this->normalizeCastGrade($cast->grade ?? 'beginner');
        $gradePoints = $cast->grade_points ?? 0;
        
        // Calculate next grade threshold
        $nextGrade = $this->getNextCastGrade($grade);
        $nextGradeThreshold = $nextGrade ? self::CAST_GRADE_THRESHOLDS[$nextGrade] : null;
        $pointsToNextGrade = $nextGradeThreshold ? $nextGradeThreshold - $gradePoints : 0;
        
        // Calculate detailed FP breakdown
        $fpBreakdown = $this->calculateCastFPBreakdown($cast);
        
        return [
            'current_grade' => $grade,
            'current_grade_name' => self::CAST_GRADE_NAMES[$grade],
            'grade_points' => $gradePoints,
            'next_grade' => $nextGrade,
            'next_grade_name' => $nextGrade ? self::CAST_GRADE_NAMES[$nextGrade] : null,
            'points_to_next_grade' => $pointsToNextGrade,
            'benefits' => self::CAST_GRADE_BENEFITS[$grade],
            'all_benefits' => self::CAST_GRADE_BENEFITS,
            'grade_names' => self::CAST_GRADE_NAMES,
            'fp_breakdown' => $fpBreakdown,
        ];
    }

    /**
     * Get the next cast grade level
     */
    public function getNextCastGrade(string $currentGrade): ?string
    {
        $grades = array_keys(self::CAST_GRADE_THRESHOLDS);
        $currentIndex = array_search($currentGrade, $grades);
        
        if ($currentIndex === false || $currentIndex >= count($grades) - 1) {
            return null;
        }
        
        return $grades[$currentIndex + 1];
    }

    /**
     * Calculate detailed FP breakdown for cast
     */
    private function calculateCastFPBreakdown(Cast $cast): array
    {
        $reservations = Reservation::where('cast_ids', 'like', '%' . $cast->id . '%')
            ->orWhere('cast_ids', 'like', $cast->id . ',%')
            ->orWhere('cast_ids', 'like', '%,' . $cast->id)
            ->orWhere('cast_ids', $cast->id)
            ->get();
        
        $repeatPoints = 0;
        $giftPoints = 0;
        $extensionCount = 0;
        $wantToMeetAgainCount = 0;
        $newGuestCount = 0;
        $repeaterCount = 0;
        
        foreach ($reservations as $reservation) {
            // Calculate repeat points
            $repeatCount = Reservation::where('cast_ids', 'like', '%' . $cast->id . '%')
                ->where('guest_id', $reservation->guest_id)
                ->count();
            
            if ($repeatCount > 1) {
                $repeatPoints += 50000 * ($repeatCount - 1);
                $repeaterCount++;
            } else {
                $newGuestCount++;
            }
            
            // Calculate gift points (simplified)
            $giftPoints += 10000; // Base gift points per reservation
            
            // Count extensions (60min+)
            if (($reservation->duration ?? 1) > 1) {
                $extensionCount++;
            }
            
            // Count "want to meet again" (simplified as positive feedback)
            $feedback = Feedback::where('cast_id', $cast->id)
                ->where('reservation_id', $reservation->id)
                ->where('rating', '>=', 4)
                ->first();
            
            if ($feedback) {
                $wantToMeetAgainCount++;
            }
        }
        
        return [
            'repeat_points' => $repeatPoints,
            'gift_points' => $giftPoints,
            'extension_count' => $extensionCount,
            'want_to_meet_again_count' => $wantToMeetAgainCount,
            'new_guest_count' => $newGuestCount,
            'repeater_count' => $repeaterCount,
        ];
    }
} 