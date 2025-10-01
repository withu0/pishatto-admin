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
        // Modify the message_type enum to include 'system'
        DB::statement("ALTER TABLE concierge_messages MODIFY COLUMN message_type ENUM('inquiry', 'support', 'reservation', 'payment', 'technical', 'general', 'system') DEFAULT 'general'");

        // Also update the category enum to include 'session_report'
        DB::statement("ALTER TABLE concierge_messages MODIFY COLUMN category ENUM('urgent', 'normal', 'low', 'session_report') DEFAULT 'normal'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'system' from message_type enum
        DB::statement("ALTER TABLE concierge_messages MODIFY COLUMN message_type ENUM('inquiry', 'support', 'reservation', 'payment', 'technical', 'general') DEFAULT 'general'");

        // Remove 'session_report' from category enum
        DB::statement("ALTER TABLE concierge_messages MODIFY COLUMN category ENUM('urgent', 'normal', 'low') DEFAULT 'normal'");
    }
};
