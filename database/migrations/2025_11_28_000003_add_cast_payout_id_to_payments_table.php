<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'cast_payout_id')) {
                $table->foreignId('cast_payout_id')->nullable()->after('user_type')->constrained('cast_payouts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'cast_payout_id')) {
                $table->dropForeign(['cast_payout_id']);
                $table->dropColumn('cast_payout_id');
            }
        });
    }
};


