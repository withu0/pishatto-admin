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
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'stripe_payout_id')) {
                $table->string('stripe_payout_id')
                    ->nullable()
                    ->after('stripe_payment_method_id');
                $table->index('stripe_payout_id');
            }

            if (!Schema::hasColumn('payments', 'stripe_connect_account_id')) {
                $table->string('stripe_connect_account_id')
                    ->nullable()
                    ->after('stripe_payout_id');
                $table->index('stripe_connect_account_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'stripe_payout_id')) {
                $table->dropIndex(['stripe_payout_id']);
                $table->dropColumn('stripe_payout_id');
            }

            if (Schema::hasColumn('payments', 'stripe_connect_account_id')) {
                $table->dropIndex(['stripe_connect_account_id']);
                $table->dropColumn('stripe_connect_account_id');
            }
        });
    }
};


