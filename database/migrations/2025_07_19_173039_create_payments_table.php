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
            $table->unsignedBigInteger('user_id');
            $table->enum('user_type', ['guest', 'cast']);
            $table->integer('amount'); // Amount in yen
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['card', 'convenience_store', 'bank_transfer', 'linepay', 'other'])->nullable();
            $table->string('payjp_charge_id')->nullable(); // PAY.JP charge ID
            $table->string('payjp_customer_id')->nullable(); // PAY.JP customer ID
            $table->string('payjp_token')->nullable(); // PAY.JP token
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional payment data
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'user_type']);
            $table->index('payjp_charge_id');
            $table->index('payjp_customer_id');
            $table->index('status');
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
