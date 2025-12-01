<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cast_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cast_id')->constrained('casts')->cascadeOnDelete();
            $table->enum('type', ['scheduled', 'instant'])->default('scheduled');
            $table->string('closing_month', 7)->comment('YYYY-MM');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('total_points');
            $table->decimal('conversion_rate', 6, 4)->default(1.2);
            $table->unsignedBigInteger('gross_amount_yen');
            $table->decimal('fee_rate', 5, 4)->default(0);
            $table->unsignedBigInteger('fee_amount_yen')->default(0);
            $table->unsignedBigInteger('net_amount_yen');
            $table->unsignedInteger('transaction_count')->default(0);
            $table->enum('status', ['pending', 'scheduled', 'processing', 'paid', 'failed', 'cancelled'])->default('pending');
            $table->date('scheduled_payout_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['cast_id', 'closing_month']);
            $table->index(['status', 'scheduled_payout_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cast_payouts');
    }
};

