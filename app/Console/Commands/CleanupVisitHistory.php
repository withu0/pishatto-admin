<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VisitHistory;
use Illuminate\Support\Facades\DB;

class CleanupVisitHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visit-history:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up duplicate visit history records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting visit history cleanup...');

        // Get all duplicate records (same guest_id and cast_id)
        $duplicates = DB::table('visit_histories')
            ->select('guest_id', 'cast_id', DB::raw('COUNT(*) as count'))
            ->groupBy('guest_id', 'cast_id')
            ->having('count', '>', 1)
            ->get();

        $this->info("Found " . $duplicates->count() . " duplicate combinations");

        foreach ($duplicates as $duplicate) {
            // Keep the most recent record and delete the rest
            $recordsToKeep = VisitHistory::where('guest_id', $duplicate->guest_id)
                ->where('cast_id', $duplicate->cast_id)
                ->orderBy('updated_at', 'desc')
                ->first();

            $deletedCount = VisitHistory::where('guest_id', $duplicate->guest_id)
                ->where('cast_id', $duplicate->cast_id)
                ->where('id', '!=', $recordsToKeep->id)
                ->delete();

            $this->info("Cleaned up {$deletedCount} duplicate records for guest_id={$duplicate->guest_id}, cast_id={$duplicate->cast_id}");
        }

        $this->info('Visit history cleanup completed!');
    }
} 