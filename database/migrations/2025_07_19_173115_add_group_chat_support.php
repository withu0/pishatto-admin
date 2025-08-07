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
        // Create chat_groups table
        Schema::create('chat_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade')->onUpdate('restrict');
        });

        // Add group_id to chats table
        Schema::table('chats', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('reservation_id');
            $table->foreign('group_id')->references('id')->on('chat_groups')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
        
        Schema::dropIfExists('chat_groups');
    }
}; 