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
        // Update the enum to include new period values
        DB::statement("ALTER TABLE rankings MODIFY COLUMN period ENUM('daily', 'weekly', 'monthly', 'period', 'current', 'yesterday', 'lastWeek', 'lastMonth', 'allTime') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE rankings MODIFY COLUMN period ENUM('daily', 'weekly', 'monthly', 'period') NULL");
    }
};
