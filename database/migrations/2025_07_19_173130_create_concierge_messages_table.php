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
        Schema::create('concierge_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // guest_id or cast_id
            $table->enum('user_type', ['guest', 'cast']);
            $table->text('message');
            $table->boolean('is_concierge')->default(false);
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'user_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concierge_messages');
    }
}; 