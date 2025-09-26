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
        // Drop the existing type column
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        // Recreate the type column with the exceeded_pending type added
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->enum('type', ['buy', 'transfer', 'convert', 'gift', 'pending', 'exceeded_pending'])->after('cast_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the type column
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        // Recreate with original enum values (without exceeded_pending)
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->enum('type', ['buy', 'transfer', 'convert', 'gift', 'pending'])->after('cast_id');
        });
    }
};

