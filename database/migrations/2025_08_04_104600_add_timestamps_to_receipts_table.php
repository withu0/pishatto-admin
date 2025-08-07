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
        Schema::table('receipts', function (Blueprint $table) {
            // Only add timestamps if they don't already exist
            if (!Schema::hasColumn('receipts', 'created_at') && !Schema::hasColumn('receipts', 'updated_at')) {
                $table->timestamps();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            // Only drop timestamps if they exist
            if (Schema::hasColumn('receipts', 'created_at') && Schema::hasColumn('receipts', 'updated_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
