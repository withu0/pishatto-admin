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
        Schema::table('cast_sessions', function (Blueprint $table) {
            $table->integer('frontend_elapsed_time')->nullable()->after('points_earned')->comment('Frontend-calculated elapsed time in seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cast_sessions', function (Blueprint $table) {
            $table->dropColumn('frontend_elapsed_time');
        });
    }
};
