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
        Schema::table('casts', function (Blueprint $table) {
            // Only add category column if it doesn't already exist
            if (!Schema::hasColumn('casts', 'category')) {
                $table->enum('category', ['プレミアム', 'VIP', 'ロイヤルVIP'])->default('プレミアム')->after('profile_text');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casts', function (Blueprint $table) {
            // Only drop category column if it exists
            if (Schema::hasColumn('casts', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
