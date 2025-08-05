<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_type'); // 'guest' or 'cast'
            $table->string('setting_key'); // e.g., 'footprints', 'likes', 'messages', etc.
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            
            // Ensure unique combination of user and setting
            $table->unique(['user_id', 'user_type', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
}; 