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
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('ended_at');
            $table->timestamp('scheduled_refund_at')->nullable()->after('cancelled_at');
            $table->string('cancellation_reason', 255)->nullable()->after('scheduled_refund_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'scheduled_refund_at', 'cancellation_reason']);
        });
    }
};
