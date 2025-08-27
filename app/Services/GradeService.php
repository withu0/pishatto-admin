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
    // Guest grade thresholds per quarter (usage points)
    const GRADE_THRESHOLDS = [
        'green' => 0,
        'orange' => 100000,
        'bronze' => 300000,
        'silver' => 500000,
        'gold' => 1000000,
        'sapphire' => 3000000,
        'emerald' => 5000000,
        'platinum' => 10000000,
        'centurion' => 30000000,
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
        'sapphire' => 'サファイア',
        'emerald' => 'エメラルド',
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

    // Cast grade thresholds per quarter (FPT earned)
    const CAST_GRADE_THRESHOLDS = [
        'beginner' => 0,
        'green' => 100000,
        'orange' => 300000,
        'bronze' => 500000,
        'silver' => 1000000,
        'gold' => 3000000,
        'sapphire' => 5000000,
        'emerald' => 10000000,
        'platinum' => 30000000,
        'black' => 50000000,
    ];

    // Cast grade display names in Japanese
    const CAST_GRADE_NAMES = [
        'beginner' => 'ビギナー',
        'green' => 'グリーン',
        'orange' => 'オレンジ',
        'bronze' => 'ブロンズ',
        'silver' => 'シルバー',
        'gold' => 'ゴールド',
        'sapphire' => 'サファイア',
        'emerald' => 'エメラルド',
        'platinum' => 'プラチナ',
        'black' => 'ブラック',
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
     * Calculate and update guest grade based on their grade_points field
     */
    public function calculateAndUpdateGrade(Guest $guest): array
    {
        $currentGrade = $guest->grade ?? 'green';
        $currentGradePoints = (int) ($guest->grade_points ?? 0);

        // Determine new grade based on stored grade_points
        $newGrade = $this->determineGrade($currentGradePoints);

        // Update grade only when it changes; do not overwrite grade_points here
        if ($newGrade !== $currentGrade) {
            $guest->update([
                'grade' => $newGrade,
                'grade_updated_at' => now(),
            ]);

            return [
                'old_grade' => $currentGrade,
                'new_grade' => $newGrade,
                'grade_points' => $currentGradePoints,
                'upgraded' => true,
            ];
        }

        return [
            'old_grade' => $currentGrade,
            'new_grade' => $currentGrade,
            'grade_points' => $currentGradePoints,
            'upgraded' => false,
        ];
    }

    /**
     * Calculate usage points from point transactions
     */
    public function calculateUsagePoints(Guest $guest): int
    {
        // Sum all point transactions representing actual spending
        $spent = PointTransaction::where('guest_id', $guest->id)
            ->whereIn('type', ['pending', 'transfer'])
            ->sum('amount');
        // Subtract all refund transactions
        $refunded = PointTransaction::where('guest_id', $guest->id)
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
        } elseif ($usagePoints >= self::GRADE_THRESHOLDS['emerald']) {
            return 'emerald';
        } elseif ($usagePoints >= self::GRADE_THRESHOLDS['sapphire']) {
            return 'sapphire';
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
            'guest' => [
                'thresholds' => self::GRADE_THRESHOLDS,
                'names' => self::GRADE_NAMES,
                'benefits' => self::GRADE_BENEFITS,
            ],
            'cast' => [
                'thresholds' => self::CAST_GRADE_THRESHOLDS,
                'names' => self::CAST_GRADE_NAMES,
                'benefits' => self::CAST_GRADE_BENEFITS,
            ],
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
     * Calculate and update cast grade based on their points balance
     */
    public function calculateAndUpdateCastGrade(Cast $cast): array
    {
        $currentGrade = $cast->grade ?? 'beginner';
        $currentPoints = (int) ($cast->points ?? 0);

        // Determine new grade based on accumulated cast points
        $newGrade = $this->determineCastGrade($currentPoints);

        // Update grade only when it changes; NEVER overwrite cast.grade_points (used as rate)
        if ($newGrade !== $currentGrade) {
            $cast->update([
                'grade' => $newGrade,
                'grade_updated_at' => now(),
            ]);

            return [
                'old_grade' => $currentGrade,
                'new_grade' => $newGrade,
                'grade_points' => $currentPoints,
                'upgraded' => true,
            ];
        }

        return [
            'old_grade' => $currentGrade,
            'new_grade' => $currentGrade,
            'grade_points' => $currentPoints,
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
                return 18000;
            case 'VIP':
                return 15000;
            case 'プレミアム':
            default:
                return 12000;
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
        if ($fp >= self::CAST_GRADE_THRESHOLDS['black']) {
            return 'black';
        } elseif ($fp >= self::CAST_GRADE_THRESHOLDS['platinum']) {
            return 'platinum';
        } elseif ($fp >= self::CAST_GRADE_THRESHOLDS['emerald']) {
            return 'emerald';
        } elseif ($fp >= self::CAST_GRADE_THRESHOLDS['sapphire']) {
            return 'sapphire';
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
        $accumulatedPoints = (int) ($cast->points ?? 0);

        // Calculate next grade threshold using accumulated points
        $nextGrade = $this->getNextCastGrade($grade);
        $nextGradeThreshold = $nextGrade ? self::CAST_GRADE_THRESHOLDS[$nextGrade] : null;
        $pointsToNextGrade = $nextGradeThreshold ? max(0, $nextGradeThreshold - $accumulatedPoints) : 0;

        // Detailed FP breakdown can still be presented for analytics
        $fpBreakdown = $this->calculateCastFPBreakdown($cast);

        return [
            'current_grade' => $grade,
            'current_grade_name' => self::CAST_GRADE_NAMES[$grade],
            'grade_points' => $accumulatedPoints,
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
     * Quarterly evaluation helpers and candidate collection/apply logic
     * Evaluations are conducted four times a year:
     * - 1-3 months (Jan-Mar)
     * - 4-6 months (Apr-Jun) 
     * - 7-9 months (Jul-Sep)
     * - 10-12 months (Oct-Dec)
     */
    public function getCurrentQuarterPeriod(?Carbon $reference = null): array
    {
        $ref = $reference ? $reference->copy() : Carbon::now();
        $month = (int) $ref->format('n');
        
        if ($month >= 1 && $month <= 3) {
            $start = Carbon::create($ref->year, 1, 1, 0, 0, 0);
            $end = Carbon::create($ref->year, 3, 31, 23, 59, 59);
        } elseif ($month >= 4 && $month <= 6) {
            $start = Carbon::create($ref->year, 4, 1, 0, 0, 0);
            $end = Carbon::create($ref->year, 6, 30, 23, 59, 59);
        } elseif ($month >= 7 && $month <= 9) {
            $start = Carbon::create($ref->year, 7, 1, 0, 0, 0);
            $end = Carbon::create($ref->year, 9, 30, 23, 59, 59);
        } else {
            $start = Carbon::create($ref->year, 10, 1, 0, 0, 0);
            $end = Carbon::create($ref->year, 12, 31, 23, 59, 59);
        }
        
        return [$start, $end];
    }

    /**
     * Get the previous quarter period for evaluation purposes
     * This is used to evaluate the previous quarter's performance
     */
    public function getPreviousQuarterPeriod(?Carbon $reference = null): array
    {
        $ref = $reference ? $reference->copy() : Carbon::now();
        $month = (int) $ref->format('n');
        
        if ($month >= 1 && $month <= 3) {
            // Previous quarter: Oct-Dec of previous year
            $start = Carbon::create($ref->year - 1, 10, 1, 0, 0, 0);
            $end = Carbon::create($ref->year - 1, 12, 31, 23, 59, 59);
        } elseif ($month >= 4 && $month <= 6) {
            // Previous quarter: Jan-Mar of current year
            $start = Carbon::create($ref->year, 1, 1, 0, 0, 0);
            $end = Carbon::create($ref->year, 3, 31, 23, 59, 59);
        } elseif ($month >= 7 && $month <= 9) {
            // Previous quarter: Apr-Jun of current year
            $start = Carbon::create($ref->year, 4, 1, 0, 0, 0);
            $end = Carbon::create($ref->year, 6, 30, 23, 59, 59);
        } else {
            // Previous quarter: Jul-Sep of current year
            $start = Carbon::create($ref->year, 7, 1, 0, 0, 0);
            $end = Carbon::create($ref->year, 9, 30, 23, 59, 59);
        }
        
        return [$start, $end];
    }

    /**
     * Get current evaluation period information for display purposes
     * Returns information about the current quarter and what period is being evaluated
     */
    public function getCurrentEvaluationInfo(?Carbon $reference = null): array
    {
        $ref = $reference ? $reference->copy() : Carbon::now();
        $month = (int) $ref->format('n');
        
        if ($month >= 1 && $month <= 3) {
            $currentQuarter = 'Q1 (Jan-Mar)';
            $evaluationPeriod = 'Oct-Dec (previous year)';
            $evaluationStart = Carbon::create($ref->year - 1, 10, 1, 0, 0, 0);
            $evaluationEnd = Carbon::create($ref->year - 1, 12, 31, 23, 59, 59);
        } elseif ($month >= 4 && $month <= 6) {
            $currentQuarter = 'Q2 (Apr-Jun)';
            $evaluationPeriod = 'Jan-Mar (current year)';
            $evaluationStart = Carbon::create($ref->year, 1, 1, 0, 0, 0);
            $evaluationEnd = Carbon::create($ref->year, 3, 31, 23, 59, 59);
        } elseif ($month >= 7 && $month <= 9) {
            $currentQuarter = 'Q3 (Jul-Sep)';
            $evaluationPeriod = 'Apr-Jun (current year)';
            $evaluationStart = Carbon::create($ref->year, 4, 1, 0, 0, 0);
            $evaluationEnd = Carbon::create($ref->year, 6, 30, 23, 59, 59);
        } else {
            $currentQuarter = 'Q4 (Oct-Dec)';
            $evaluationPeriod = 'Jul-Sep (current year)';
            $evaluationStart = Carbon::create($ref->year, 7, 1, 0, 0, 0);
            $evaluationEnd = Carbon::create($ref->year, 9, 30, 23, 59, 59);
        }
        
        return [
            'current_quarter' => $currentQuarter,
            'evaluation_period' => $evaluationPeriod,
            'evaluation_start' => $evaluationStart,
            'evaluation_end' => $evaluationEnd,
            'next_evaluation_date' => $this->getNextEvaluationDate($ref),
        ];
    }

    /**
     * Get quarterly points information for casts and guests
     * This shows the current quarter's accumulated points
     */
    public function getQuarterlyPointsInfo(?Carbon $reference = null): array
    {
        $ref = $reference ? $reference->copy() : Carbon::now();
        $month = (int) $ref->format('n');
        
        if ($month >= 1 && $month <= 3) {
            $currentQuarter = 'Q1 (Jan-Mar)';
            $quarterStart = Carbon::create($ref->year, 1, 1, 0, 0, 0);
            $quarterEnd = Carbon::create($ref->year, 3, 31, 23, 59, 59);
        } elseif ($month >= 4 && $month <= 6) {
            $currentQuarter = 'Q2 (Apr-Jun)';
            $quarterStart = Carbon::create($ref->year, 4, 1, 0, 0, 0);
            $quarterEnd = Carbon::create($ref->year, 6, 30, 23, 59, 59);
        } elseif ($month >= 7 && $month <= 9) {
            $currentQuarter = 'Q3 (Jul-Sep)';
            $quarterStart = Carbon::create($ref->year, 7, 1, 0, 0, 0);
            $quarterEnd = Carbon::create($ref->year, 9, 30, 23, 59, 59);
        } else {
            $currentQuarter = 'Q4 (Oct-Dec)';
            $quarterStart = Carbon::create($ref->year, 10, 1, 0, 0, 0);
            $quarterEnd = Carbon::create($ref->year, 12, 31, 23, 59, 59);
        }
        
        // Get next reset date
        $nextResetDate = $this->getNextQuarterResetDate($ref);
        
        return [
            'current_quarter' => $currentQuarter,
            'quarter_start' => $quarterStart,
            'quarter_end' => $quarterEnd,
            'next_reset_date' => $nextResetDate,
            'days_until_reset' => $quarterEnd->diffInDays($ref, false),
        ];
    }

    /**
     * Get the next quarter reset date (when points will be reset)
     */
    private function getNextQuarterResetDate(Carbon $reference): Carbon
    {
        $month = (int) $reference->format('n');
        
        if ($month >= 1 && $month <= 3) {
            // Next reset: April 1st
            return Carbon::create($reference->year, 4, 1, 0, 1, 0);
        } elseif ($month >= 4 && $month <= 6) {
            // Next reset: July 1st
            return Carbon::create($reference->year, 7, 1, 0, 1, 0);
        } elseif ($month >= 7 && $month <= 9) {
            // Next reset: October 1st
            return Carbon::create($reference->year, 10, 1, 0, 1, 0);
        } else {
            // Next reset: January 1st (next year)
            return Carbon::create($reference->year + 1, 1, 1, 0, 1, 0);
        }
    }

    /**
     * Get the next evaluation date (when the next quarterly evaluation will run)
     */
    private function getNextEvaluationDate(Carbon $reference): Carbon
    {
        $month = (int) $reference->format('n');
        
        if ($month >= 1 && $month <= 3) {
            // Next evaluation: April 1st
            return Carbon::create($reference->year, 4, 1, 4, 0, 0);
        } elseif ($month >= 4 && $month <= 6) {
            // Next evaluation: July 1st
            return Carbon::create($reference->year, 7, 1, 4, 0, 0);
        } elseif ($month >= 7 && $month <= 9) {
            // Next evaluation: October 1st
            return Carbon::create($reference->year, 10, 1, 4, 0, 0);
        } else {
            // Next evaluation: January 1st (next year)
            return Carbon::create($reference->year + 1, 1, 1, 4, 0, 0);
        }
    }

    public function calculateGuestQuarterUsage(Guest $guest, ?Carbon $reference = null): int
    {
        [$start, $end] = $this->getCurrentQuarterPeriod($reference);
        $spent = PointTransaction::where('guest_id', $guest->id)
            ->whereIn('type', ['pending', 'transfer'])
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
        $refunded = PointTransaction::where('guest_id', $guest->id)
            ->where('type', 'convert')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
        return max(0, $spent - $refunded);
    }

    public function calculateCastQuarterEarnedPoints(Cast $cast, ?Carbon $reference = null): int
    {
        [$start, $end] = $this->getCurrentQuarterPeriod($reference);
        return (int) PointTransaction::where('cast_id', $cast->id)
            ->whereIn('type', ['transfer', 'gift'])
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
    }

    public function getNextGuestGrade(string $currentGrade): ?string
    {
        $grades = array_keys(self::GRADE_THRESHOLDS);
        $index = array_search($currentGrade, $grades, true);
        if ($index === false || $index >= count($grades) - 1) {
            return null;
        }
        return $grades[$index + 1];
    }

    public function getPreviousGuestGrade(string $currentGrade): ?string
    {
        $grades = array_keys(self::GRADE_THRESHOLDS);
        $index = array_search($currentGrade, $grades, true);
        if ($index === false || $index === 0) {
            return null;
        }
        return $grades[$index - 1];
    }

    public function getNextCastGradeKey(string $currentGrade): ?string
    {
        $grades = array_keys(self::CAST_GRADE_THRESHOLDS);
        $index = array_search($currentGrade, $grades, true);
        if ($index === false || $index >= count($grades) - 1) {
            return null;
        }
        return $grades[$index + 1];
    }

    public function getPreviousCastGradeKey(string $currentGrade): ?string
    {
        $grades = array_keys(self::CAST_GRADE_THRESHOLDS);
        $index = array_search($currentGrade, $grades, true);
        if ($index === false || $index === 0) {
            return null;
        }
        return $grades[$index - 1];
    }

    public function getGuestUpgradeCandidate(Guest $guest, ?Carbon $reference = null): ?array
    {
        // Use the stored grade_points field directly instead of calculating from transactions
        $currentGradePoints = (int) ($guest->grade_points ?? 0);
        $current = $this->normalizeGuestGrade($guest->grade ?? 'green');
        $next = $this->getNextGuestGrade($current);
        if (!$next) {
            return null;
        }
        $threshold = self::GRADE_THRESHOLDS[$next];
        if ($currentGradePoints >= $threshold) {
            return [
                'guest_id' => $guest->id,
                'current_grade' => $current,
                'target_grade' => $next,
                'quarter_usage' => $currentGradePoints,
                'threshold' => $threshold,
            ];
        }
        return null;
    }

    public function getGuestDowngradeCandidate(Guest $guest, ?Carbon $reference = null): ?array
    {
        // Use the stored grade_points field directly instead of calculating from transactions
        $currentGradePoints = (int) ($guest->grade_points ?? 0);
        $current = $this->normalizeGuestGrade($guest->grade ?? 'green');
        if ($current === 'green') {
            return null;
        }
        $threshold = self::GRADE_THRESHOLDS[$current];
        if ($currentGradePoints < $threshold) {
            $previous = $this->getPreviousGuestGrade($current);
            return [
                'guest_id' => $guest->id,
                'current_grade' => $current,
                'target_grade' => $previous,
                'quarter_usage' => $currentGradePoints,
                'threshold' => $threshold,
            ];
        }
        return null;
    }

    public function getCastUpgradeCandidate(Cast $cast, ?Carbon $reference = null): ?array
    {
        // Use the stored points field directly instead of calculating from transactions
        $currentPoints = (int) ($cast->points ?? 0);
        $current = $this->normalizeCastGrade($cast->grade ?? 'beginner');
        $next = $this->getNextCastGradeKey($current);
        if (!$next) {
            return null;
        }
        $threshold = self::CAST_GRADE_THRESHOLDS[$next];
        if ($currentPoints >= $threshold) {
            return [
                'cast_id' => $cast->id,
                'current_grade' => $current,
                'target_grade' => $next,
                'quarter_earned_points' => $currentPoints,
                'threshold' => $threshold,
            ];
        }
        return null;
    }

    public function getCastDowngradeCandidate(Cast $cast, ?Carbon $reference = null): ?array
    {
        // Use the stored points field directly instead of calculating from transactions
        $currentPoints = (int) ($cast->points ?? 0);
        $current = $this->normalizeCastGrade($cast->grade ?? 'beginner');
        if ($current === 'beginner') {
            return null;
        }
        $threshold = self::CAST_GRADE_THRESHOLDS[$current];
        if ($currentPoints < $threshold) {
            $previous = $this->getPreviousCastGradeKey($current);
            return [
                'cast_id' => $cast->id,
                'current_grade' => $current,
                'target_grade' => $previous,
                'quarter_earned_points' => $currentPoints,
                'threshold' => $threshold,
            ];
        }
        return null;
    }

    public function collectUpgradeCandidates(?Carbon $reference = null): array
    {
        $guestCandidates = [];
        $castCandidates = [];
        Guest::select('id', 'grade', 'grade_points')
            ->chunk(200, function ($guests) use (&$guestCandidates, $reference) {
                foreach ($guests as $guest) {
                    $candidate = $this->getGuestUpgradeCandidate($guest, $reference);
                    if ($candidate) {
                        $guestCandidates[] = $candidate;
                    }
                }
            });
        Cast::select('id', 'grade', 'points')
            ->chunk(200, function ($casts) use (&$castCandidates, $reference) {
                foreach ($casts as $cast) {
                    $candidate = $this->getCastUpgradeCandidate($cast, $reference);
                    if ($candidate) {
                        $castCandidates[] = $candidate;
                    }
                }
            });
        return [
            'guests' => $guestCandidates,
            'casts' => $castCandidates,
        ];
    }

    public function collectDowngradeCandidates(?Carbon $reference = null): array
    {
        $guestCandidates = [];
        $castCandidates = [];
        
        // For downgrade evaluation, we need to evaluate the previous quarter
        // This ensures we're evaluating completed performance, not current quarter
        $evalReference = $reference ? $reference->copy() : Carbon::now();
        
        Guest::select('id', 'grade', 'grade_points')
            ->chunk(200, function ($guests) use (&$guestCandidates, $evalReference) {
                foreach ($guests as $guest) {
                    $candidate = $this->getGuestDowngradeCandidate($guest, $evalReference);
                    if ($candidate) {
                        $guestCandidates[] = $candidate;
                    }
                }
            });
            
        Cast::select('id', 'grade', 'points')
            ->chunk(200, function ($casts) use (&$castCandidates, $evalReference) {
                foreach ($casts as $cast) {
                    $candidate = $this->getCastDowngradeCandidate($cast, $evalReference);
                    if ($candidate) {
                        $castCandidates[] = $candidate;
                    }
                }
            });
            
        return [
            'guests' => $guestCandidates,
            'casts' => $castCandidates,
        ];
    }

    public function applyGuestUpgrade(Guest $guest): array
    {
        $current = $this->normalizeGuestGrade($guest->grade ?? 'green');
        $next = $this->getNextGuestGrade($current);
        if (!$next) {
            return ['updated' => false, 'message' => 'No higher grade available'];
        }
        $guest->update([
            'grade' => $next,
            'grade_updated_at' => now(),
        ]);
        return ['updated' => true, 'old_grade' => $current, 'new_grade' => $next];
    }

    public function applyCastUpgrade(Cast $cast): array
    {
        $current = $this->normalizeCastGrade($cast->grade ?? 'beginner');
        $next = $this->getNextCastGradeKey($current);
        if (!$next) {
            return ['updated' => false, 'message' => 'No higher grade available'];
        }
        $cast->update([
            'grade' => $next,
            'grade_updated_at' => now(),
        ]);
        return ['updated' => true, 'old_grade' => $current, 'new_grade' => $next];
    }

    public function applyQuarterlyDowngrades(?Carbon $reference = null): array
    {
        $results = [
            'guest_downgraded' => 0,
            'cast_downgraded' => 0,
            'guests' => [],
            'casts' => [],
        ];
        // Evaluate the PREVIOUS quarter relative to reference (or now)
        $evalRef = $reference ? $reference->copy()->subMonthNoOverflow() : Carbon::now()->subMonthNoOverflow();
        $collected = $this->collectDowngradeCandidates($evalRef);
        foreach ($collected['guests'] as $g) {
            $guest = Guest::find($g['guest_id']);
            if ($guest && $g['target_grade']) {
                $old = $guest->grade;
                $guest->update(['grade' => $g['target_grade'], 'grade_updated_at' => now()]);
                $results['guest_downgraded']++;
                $results['guests'][] = ['guest_id' => $guest->id, 'old' => $old, 'new' => $g['target_grade']];
            }
        }
        foreach ($collected['casts'] as $c) {
            $cast = Cast::find($c['cast_id']);
            if ($cast && $c['target_grade']) {
                $old = $cast->grade;
                $cast->update(['grade' => $c['target_grade'], 'grade_updated_at' => now()]);
                $results['cast_downgraded']++;
                $results['casts'][] = ['cast_id' => $cast->id, 'old' => $old, 'new' => $c['target_grade']];
            }
        }
        return $results;
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