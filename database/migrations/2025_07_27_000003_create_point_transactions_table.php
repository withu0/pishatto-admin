<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->nullable()->constrained('guests');
            $table->foreignId('cast_id')->nullable()->constrained('casts');
            $table->enum('type', ['buy', 'transfer', 'convert', 'gift']);
            $table->unsignedBigInteger('amount');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
}; 