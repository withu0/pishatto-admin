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
        Schema::table('visit_histories', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate guest_id + cast_id combinations
            $table->unique(['guest_id', 'cast_id'], 'unique_guest_cast_visit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_histories', function (Blueprint $table) {
            $table->dropUnique('unique_guest_cast_visit');
        });
    }
}; 