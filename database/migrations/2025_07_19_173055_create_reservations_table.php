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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('guest_id')->nullable();
            $table->enum('type', ['free','Pishatto'])->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('time',10)->nullable();
            $table->string('location', 255)->nullable();
            $table->integer('duration')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('points_earned')->nullable();

            $table->index('guest_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
