<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['feedback_badge_id']);
            $table->dropColumn(['feedback_text', 'feedback_rating', 'feedback_badge_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->text('feedback_text')->nullable()->after('points_earned');
            $table->integer('feedback_rating')->nullable()->after('feedback_text');
            $table->unsignedBigInteger('feedback_badge_id')->nullable()->after('feedback_rating');
            $table->foreign('feedback_badge_id')->references('id')->on('badges')->nullOnDelete();
        });
    }
}; 