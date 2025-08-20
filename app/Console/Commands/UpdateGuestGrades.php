<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GradeService;
use App\Models\Guest;

class UpdateGuestGrades extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grades:quarterly {--guest-id= : Update specific guest grade} {--auto-downgrade : Run auto downgrades for the quarter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update guest grades based on their usage points and run quarterly auto-downgrades';

    protected $gradeService;

    /**
     * Create a new command instance.
     */
    public function __construct(GradeService $gradeService)
    {
        parent::__construct();
        $this->gradeService = $gradeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $guestId = $this->option('guest-id');

        if ($this->option('auto-downgrade')) {
            $this->runAutoDowngrade();
            return;
        } elseif ($guestId) {
            $this->updateSpecificGuest($guestId);
        } else {
            $this->updateAllGuests();
        }
    }

    private function runAutoDowngrade(): void
    {
        $this->info('Running quarterly auto-downgrades...');
        
        // Get current quarter information for context
        $now = \Carbon\Carbon::now();
        $month = (int) $now->format('n');
        
        if ($month >= 1 && $month <= 3) {
            $quarterName = 'Q1 (Jan-Mar)';
            $evaluationPeriod = 'Oct-Dec (previous year)';
        } elseif ($month >= 4 && $month <= 6) {
            $quarterName = 'Q2 (Apr-Jun)';
            $evaluationPeriod = 'Jan-Mar (current year)';
        } elseif ($month >= 7 && $month <= 9) {
            $quarterName = 'Q3 (Jul-Sep)';
            $evaluationPeriod = 'Apr-Jun (current year)';
        } else {
            $quarterName = 'Q4 (Oct-Dec)';
            $evaluationPeriod = 'Jul-Sep (current year)';
        }
        
        $this->info("Current quarter: {$quarterName}");
        $this->info("Evaluating performance for: {$evaluationPeriod}");
        
        $result = $this->gradeService->applyQuarterlyDowngrades();
        
        $this->info("✅ Quarterly evaluation completed!");
        $this->info("Guests downgraded: {$result['guest_downgraded']}");
        $this->info("Casts downgraded: {$result['cast_downgraded']}");
        
        if (!empty($result['guests'])) {
            $this->info("\nGuest downgrades:");
            foreach ($result['guests'] as $guest) {
                $this->line("  - Guest ID {$guest['guest_id']}: {$guest['old']} → {$guest['new']}");
            }
        }
        
        if (!empty($result['casts'])) {
            $this->info("\nCast downgrades:");
            foreach ($result['casts'] as $cast) {
                $this->line("  - Cast ID {$cast['cast_id']}: {$cast['old']} → {$cast['new']}");
            }
        }
    }

    private function updateSpecificGuest($guestId)
    {
        $guest = Guest::find($guestId);
        
        if (!$guest) {
            $this->error("Guest with ID {$guestId} not found.");
            return;
        }

        $this->info("Updating grade for guest: {$guest->nickname} (ID: {$guest->id})");
        
        $result = $this->gradeService->calculateAndUpdateGrade($guest);
        
        if ($result['upgraded']) {
            $this->info("✅ Guest upgraded from {$result['old_grade']} to {$result['new_grade']}!");
        } else {
            $this->info("ℹ️  Guest grade remains {$result['new_grade']} (no upgrade)");
        }
        
        $this->info("Grade points: {$result['grade_points']}P");
    }

    private function updateAllGuests()
    {
        $this->info("Starting grade update for all guests...");
        
        $results = $this->gradeService->updateAllGuestGrades();
        
        $this->info("✅ Grade update completed!");
        $this->info("Total guests processed: {$results['total_guests']}");
        $this->info("Guests upgraded: {$results['upgraded_guests']}");
        
        if (!empty($results['upgrades'])) {
            $this->info("\nUpgrades:");
            foreach ($results['upgrades'] as $upgrade) {
                $this->line("  - {$upgrade['nickname']}: {$upgrade['old_grade']} → {$upgrade['new_grade']} ({$upgrade['grade_points']}P)");
            }
        }
    }
} 