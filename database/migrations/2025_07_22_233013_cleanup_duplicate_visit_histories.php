<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clean up duplicate visit history records
        // Keep only the most recent record for each guest_id + cast_id combination
        
        $duplicates = DB::table('visit_histories')
            ->select('guest_id', 'cast_id', DB::raw('COUNT(*) as count'))
            ->groupBy('guest_id', 'cast_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            // Get the most recent record for this combination
            $latestRecord = DB::table('visit_histories')
                ->where('guest_id', $duplicate->guest_id)
                ->where('cast_id', $duplicate->cast_id)
                ->orderBy('updated_at', 'desc')
                ->first();

            // Delete all other records for this combination
            DB::table('visit_histories')
                ->where('guest_id', $duplicate->guest_id)
                ->where('cast_id', $duplicate->cast_id)
                ->where('id', '!=', $latestRecord->id)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be reversed as it deletes data
    }
}; 