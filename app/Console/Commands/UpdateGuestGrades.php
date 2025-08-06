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
    protected $signature = 'guests:update-grades {--guest-id= : Update specific guest grade}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update guest grades based on their usage points';

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

        if ($guestId) {
            $this->updateSpecificGuest($guestId);
        } else {
            $this->updateAllGuests();
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