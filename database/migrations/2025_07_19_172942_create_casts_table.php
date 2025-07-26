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
        Schema::create('casts', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique()->nullable();
            $table->string('line_id', 50)->unique()->nullable();
            $table->string('nickname', 50)->nullable();
            $table->string('avatar', 255)->nullable();
            $table->integer('birth_year')->nullable();
            $table->integer('height')->nullable();
            $table->string('grade', 50)->nullable();
            $table->integer('grade_points')->nullable();
            $table->string('residence', 100)->nullable();
            $table->string('birthplace', 100)->nullable();
            $table->text('profile_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('casts');
    }
};
