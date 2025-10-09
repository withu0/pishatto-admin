<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'refund' to the point_transactions type enum
        \DB::statement("ALTER TABLE point_transactions MODIFY COLUMN type ENUM('buy', 'transfer', 'convert', 'gift', 'pending', 'exceeded_pending', 'refund') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'refund' from the point_transactions type enum
        \DB::statement("ALTER TABLE point_transactions MODIFY COLUMN type ENUM('buy', 'transfer', 'convert', 'gift', 'pending', 'exceeded_pending') NOT NULL");
    }
};
