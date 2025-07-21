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
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guest_id')->nullable();
            $table->unsignedBigInteger('cast_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('guest_id');
            $table->index('cast_id');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('cast_id')->references('id')->on('casts')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
