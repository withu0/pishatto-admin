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
        // Add cast_ids to reservations table
        Schema::table('reservations', function (Blueprint $table) {
            $table->json('cast_ids')->nullable()->after('cast_id');
        });

        // Add cast_ids to chat_groups table
        Schema::table('chat_groups', function (Blueprint $table) {
            $table->json('cast_ids')->nullable()->after('reservation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('cast_ids');
        });

        Schema::table('chat_groups', function (Blueprint $table) {
            $table->dropColumn('cast_ids');
        });
    }
}; 