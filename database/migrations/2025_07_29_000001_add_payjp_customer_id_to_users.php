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
        Schema::table('guests', function (Blueprint $table) {
            $table->string('payjp_customer_id')->nullable()->after('id');
            $table->index('payjp_customer_id');
        });

        Schema::table('casts', function (Blueprint $table) {
            $table->string('payjp_customer_id')->nullable()->after('id');
            $table->index('payjp_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['payjp_customer_id']);
            $table->dropColumn('payjp_customer_id');
        });

        Schema::table('casts', function (Blueprint $table) {
            $table->dropIndex(['payjp_customer_id']);
            $table->dropColumn('payjp_customer_id');
        });
    }
}; 