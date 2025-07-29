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
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedBigInteger('cast_id');
            $table->unsignedBigInteger('guest_id');
            $table->text('comment')->nullable();
            $table->integer('rating')->nullable(); // 1-5 rating
            $table->unsignedBigInteger('badge_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');
            $table->foreign('cast_id')->references('id')->on('casts')->onDelete('cascade');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');
            $table->foreign('badge_id')->references('id')->on('badges')->nullOnDelete();

            // Ensure one feedback per guest per cast per reservation
            $table->unique(['reservation_id', 'cast_id', 'guest_id'], 'unique_feedback_per_reservation_cast_guest');

            // Indexes for better performance
            $table->index(['reservation_id']);
            $table->index(['cast_id']);
            $table->index(['guest_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
