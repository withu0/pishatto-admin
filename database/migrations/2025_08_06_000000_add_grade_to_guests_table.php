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
        Schema::table('guests', function (Blueprint $table) {
            $table->enum('grade', ['green', 'orange', 'bronze', 'silver', 'gold', 'platinum', 'centurion'])->default('green')->after('points');
            $table->integer('grade_points')->default(0)->after('grade');
            $table->timestamp('grade_updated_at')->nullable()->after('grade_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['grade', 'grade_points', 'grade_updated_at']);
        });
    }
}; 