<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cast;
use App\Models\Guest;
use App\Models\PointTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResetQuarterlyPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:reset-quarterly {--dry-run : Show what would be reset without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset quarterly points for casts and guests on the 1st of January, April, July, and October';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $month = (int) $now->format('n');
        $day = (int) $now->format('j');
        
        // Check if today is the 1st of a quarter month
        if (!in_array($month, [1, 4, 7, 10]) || $day !== 1) {
            $this->error('This command should only be run on the 1st of January, April, July, or October.');
            $this->info('Current date: ' . $now->format('Y-m-d'));
            return 1;
        }

        $quarterName = $this->getQuarterName($month);
        $this->info("Starting quarterly points reset for {$quarterName}...");

        if ($this->option('dry-run')) {
            $this->info('DRY RUN MODE - No actual changes will be made');
        }

        try {
            DB::beginTransaction();

            // Reset cast points
            $castResults = $this->resetCastPoints();
            
            // Reset guest grade_points
            $guestResults = $this->resetGuestGradePoints();

            if (!$this->option('dry-run')) {
                DB::commit();
                $this->info('✅ Quarterly points reset completed successfully!');
            } else {
                DB::rollBack();
                $this->info('DRY RUN COMPLETED - No changes were made');
            }

            $this->displayResults($castResults, $guestResults, $quarterName);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error during quarterly points reset: ' . $e->getMessage());
            Log::error('Quarterly points reset failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'quarter' => $quarterName,
                'date' => $now->toISOString(),
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Reset cast points for the new quarter
     */
    private function resetCastPoints(): array
    {
        $casts = Cast::all();
        $resetCount = 0;
        $totalPoints = 0;

        foreach ($casts as $cast) {
            $oldPoints = $cast->points ?? 0;
            
            if (!$this->option('dry-run')) {
                $cast->update(['points' => 0]);
            }
            
            $resetCount++;
            $totalPoints += $oldPoints;
        }

        return [
            'count' => $resetCount,
            'total_points_reset' => $totalPoints,
            'message' => "Reset points for {$resetCount} casts (total: {$totalPoints} points)"
        ];
    }

    /**
     * Reset guest grade_points for the new quarter
     */
    private function resetGuestGradePoints(): array
    {
        $guests = Guest::all();
        $resetCount = 0;
        $totalGradePoints = 0;

        foreach ($guests as $guest) {
            $oldGradePoints = $guest->grade_points ?? 0;
            
            if (!$this->option('dry-run')) {
                $guest->update(['grade_points' => 0]);
            }
            
            $resetCount++;
            $totalGradePoints += $oldGradePoints;
        }

        return [
            'count' => $resetCount,
            'total_grade_points_reset' => $totalGradePoints,
            'message' => "Reset grade_points for {$resetCount} guests (total: {$totalGradePoints} points)"
        ];
    }

    /**
     * Get quarter name based on month
     */
    private function getQuarterName(int $month): string
    {
        switch ($month) {
            case 1:
                return 'Q1 (January-March)';
            case 4:
                return 'Q2 (April-June)';
            case 7:
                return 'Q3 (July-September)';
            case 10:
                return 'Q4 (October-December)';
            default:
                return 'Unknown Quarter';
        }
    }

    /**
     * Display results of the reset operation
     */
    private function displayResults(array $castResults, array $guestResults, string $quarterName): void
    {
        $this->info("\n📊 Quarterly Points Reset Results for {$quarterName}");
        $this->info('=' . str_repeat('=', 50));
        
        $this->info("\n🎭 Casts:");
        $this->info("  - {$castResults['message']}");
        
        $this->info("\n👥 Guests:");
        $this->info("  - {$guestResults['message']}");
        
        $this->info("\n📈 Summary:");
        $this->info("  - Total casts processed: {$castResults['count']}");
        $this->info("  - Total guests processed: {$guestResults['count']}");
        $this->info("  - Total cast points reset: {$castResults['total_points_reset']}");
        $this->info("  - Total guest grade points reset: {$guestResults['total_grade_points_reset']}");
        
        if ($this->option('dry-run')) {
            $this->warn("\n⚠️  This was a dry run. No actual changes were made.");
        } else {
            $this->info("\n✅ All quarterly points have been reset successfully!");
        }
    }
}
