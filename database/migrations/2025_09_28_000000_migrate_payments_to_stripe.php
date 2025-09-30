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
            // Add Stripe-specific fields
            $table->string('stripe_payment_intent_id')->nullable()->after('payjp_charge_id');
            $table->string('stripe_customer_id')->nullable()->after('payjp_customer_id');
            $table->string('stripe_payment_method_id')->nullable()->after('payjp_token');

            // Add indexes for Stripe fields
            $table->index('stripe_payment_intent_id');
            $table->index('stripe_customer_id');
            $table->index('stripe_payment_method_id');
        });

        // Add Stripe customer ID to users table
        Schema::table('guests', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('payjp_customer_id');
            $table->index('stripe_customer_id');
        });

        Schema::table('casts', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('payjp_customer_id');
            $table->index('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['stripe_payment_intent_id']);
            $table->dropIndex(['stripe_customer_id']);
            $table->dropIndex(['stripe_payment_method_id']);
            $table->dropColumn(['stripe_payment_intent_id', 'stripe_customer_id', 'stripe_payment_method_id']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('casts', function (Blueprint $table) {
            $table->dropIndex(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });
    }
};
