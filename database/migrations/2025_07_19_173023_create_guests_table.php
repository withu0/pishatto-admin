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
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique()->nullable();
            $table->string('line_id', 50)->unique()->nullable();
            $table->string('nickname', 50)->nullable();
            $table->string('location', 50)->nullable();
            $table->string('avatar', 255)->nullable();
            $table->integer('birth_year')->nullable();
            $table->integer('height')->nullable();
            $table->string('residence', 100)->nullable();
            $table->string('birthplace', 100)->nullable();
            $table->string('annual_income', 100)->nullable();
            $table->string('education', 100)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('alcohol', 20)->nullable();
            $table->string('tobacco', 30)->nullable();
            $table->string('siblings', 100)->nullable();
            $table->string('cohabitant', 100)->nullable();
            $table->enum('pressure', ['weak','medium','strong'])->nullable();
            $table->string('favorite_area', 100)->nullable();
            $table->json('interests')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
