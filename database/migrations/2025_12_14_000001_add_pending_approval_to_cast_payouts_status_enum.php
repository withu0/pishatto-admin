<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL doesn't support direct enum modification, so we need to use raw SQL
        DB::statement("ALTER TABLE `cast_payouts` MODIFY COLUMN `status` ENUM('pending', 'pending_approval', 'scheduled', 'processing', 'paid', 'failed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'pending_approval' from enum
        DB::statement("ALTER TABLE `cast_payouts` MODIFY COLUMN `status` ENUM('pending', 'scheduled', 'processing', 'paid', 'failed', 'cancelled') DEFAULT 'pending'");
    }
};

