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
        Schema::table('point_transactions', function (Blueprint $table) {
            // Update the enum to include exceeded_pending type
            $table->enum('type', ['buy', 'transfer', 'convert', 'gift', 'pending', 'exceeded_pending'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            // Revert to original enum without exceeded_pending
            $table->enum('type', ['buy', 'transfer', 'convert', 'gift', 'pending'])->change();
        });
    }
};