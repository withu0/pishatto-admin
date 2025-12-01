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
        Schema::table('casts', function (Blueprint $table) {
            if (!Schema::hasColumn('casts', 'stripe_connect_account_id')) {
                $table->string('stripe_connect_account_id')
                    ->nullable()
                    ->after('stripe_customer_id');
            }

            if (!Schema::hasColumn('casts', 'stripe_onboarding_status')) {
                $table->string('stripe_onboarding_status')
                    ->default('not_started')
                    ->after('stripe_connect_account_id');
            }

            if (!Schema::hasColumn('casts', 'stripe_requirements')) {
                $table->json('stripe_requirements')
                    ->nullable()
                    ->after('stripe_onboarding_status');
            }

            if (!Schema::hasColumn('casts', 'payouts_enabled')) {
                $table->boolean('payouts_enabled')
                    ->default(false)
                    ->after('stripe_requirements');
            }

            if (!Schema::hasColumn('casts', 'stripe_connect_last_synced_at')) {
                $table->timestamp('stripe_connect_last_synced_at')
                    ->nullable()
                    ->after('payouts_enabled');
            }

            if (!Schema::hasColumn('casts', 'stripe_payouts_enabled_at')) {
                $table->timestamp('stripe_payouts_enabled_at')
                    ->nullable()
                    ->after('stripe_connect_last_synced_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casts', function (Blueprint $table) {
            $columns = [
                'stripe_connect_account_id',
                'stripe_onboarding_status',
                'stripe_requirements',
                'payouts_enabled',
                'stripe_connect_last_synced_at',
                'stripe_payouts_enabled_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('casts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};


