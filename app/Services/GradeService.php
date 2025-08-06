<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\PointTransaction;
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
        return PointTransaction::where('guest_id', $guest->id)
            ->where('type', 'buy')
            ->sum('amount');
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
        $grade = $guest->grade ?? 'green';
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
} 