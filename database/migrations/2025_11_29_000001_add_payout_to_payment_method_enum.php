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
        // MySQL doesn't support direct enum modification, so we need to use raw SQL
        DB::statement("ALTER TABLE `payments` MODIFY COLUMN `payment_method` ENUM('card', 'convenience_store', 'bank_transfer', 'linepay', 'other', 'payout') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'payout' from enum
        DB::statement("ALTER TABLE `payments` MODIFY COLUMN `payment_method` ENUM('card', 'convenience_store', 'bank_transfer', 'linepay', 'other') NULL");
    }
};




