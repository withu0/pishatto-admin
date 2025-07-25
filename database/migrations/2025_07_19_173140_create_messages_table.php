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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id')->nullable();
            $table->unsignedBigInteger('sender_guest_id')->nullable();
            $table->unsignedBigInteger('sender_cast_id')->nullable();
            $table->text('message')->nullable();
            $table->string('image', 255)->nullable();
            $table->unsignedBigInteger('gift_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('is_read')->default(false);

            $table->index('chat_id');
            $table->index('sender_guest_id');
            $table->index('sender_cast_id');
            $table->foreign('chat_id')->references('id')->on('chats')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('sender_guest_id')->references('id')->on('guests')->onDelete('set null')->onUpdate('restrict');
            $table->foreign('sender_cast_id')->references('id')->on('casts')->onDelete('set null')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
