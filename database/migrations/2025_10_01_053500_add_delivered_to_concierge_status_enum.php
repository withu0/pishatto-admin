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
        // Add 'delivered' to the status enum
        DB::statement("ALTER TABLE concierge_messages MODIFY COLUMN status ENUM('pending', 'in_progress', 'resolved', 'closed', 'delivered') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'delivered' from status enum
        DB::statement("ALTER TABLE concierge_messages MODIFY COLUMN status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending'");
    }
};
