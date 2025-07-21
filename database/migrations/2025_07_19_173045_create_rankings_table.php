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
        Schema::create('rankings', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['guest','cast'])->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->integer('points')->nullable();
            $table->enum('period', ['daily','weekly','monthly'])->nullable();
            $table->string('region', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rankings');
    }
};
