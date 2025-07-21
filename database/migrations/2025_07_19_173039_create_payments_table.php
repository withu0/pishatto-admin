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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guest_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->enum('status', ['pending','paid','failed'])->nullable();
            $table->enum('method', ['card','linepay','other'])->nullable();
            $table->timestamp('paid_at')->useCurrentOnUpdate()->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index('guest_id');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
