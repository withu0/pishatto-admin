<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('point_transactions', 'cast_payout_id')) {
                $table->foreignId('cast_payout_id')->nullable()->after('payment_id')->constrained('cast_payouts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('point_transactions', 'cast_payout_id')) {
                $table->dropForeign(['cast_payout_id']);
                $table->dropColumn('cast_payout_id');
            }
        });
    }
};


