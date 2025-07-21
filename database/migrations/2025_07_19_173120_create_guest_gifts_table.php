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
        Schema::create('guest_gifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_guest_id')->nullable();
            $table->unsignedBigInteger('receiver_cast_id')->nullable();
            $table->unsignedBigInteger('gift_id')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('sender_guest_id');
            $table->index('receiver_cast_id');
            $table->index('gift_id');
            $table->foreign('sender_guest_id')->references('id')->on('guests')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('receiver_cast_id')->references('id')->on('casts')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('gift_id')->references('id')->on('gifts')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_gifts');
    }
};
