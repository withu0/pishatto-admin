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
        // Add payment_info to guests table if it doesn't exist
        if (!Schema::hasColumn('guests', 'payment_info')) {
            Schema::table('guests', function (Blueprint $table) {
                $table->text('payment_info')->nullable()->after('payjp_customer_id');
            });
        }

        // Add payment_info to casts table if it doesn't exist
        if (!Schema::hasColumn('casts', 'payment_info')) {
            Schema::table('casts', function (Blueprint $table) {
                $table->text('payment_info')->nullable()->after('payjp_customer_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove payment_info from guests table
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('payment_info');
        });

        // Remove payment_info from casts table
        Schema::table('casts', function (Blueprint $table) {
            $table->dropColumn('payment_info');
        });
    }
};
