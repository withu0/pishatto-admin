<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes for better query performance
        Schema::table('guests', function (Blueprint $table) {
            // Add index for payjp_customer_id if not exists
            if (!Schema::hasIndex('guests', 'guests_payjp_customer_id_index')) {
                $table->index('payjp_customer_id', 'guests_payjp_customer_id_index');
            }
        });

        Schema::table('casts', function (Blueprint $table) {
            // Add index for payjp_customer_id if not exists
            if (!Schema::hasIndex('casts', 'casts_payjp_customer_id_index')) {
                $table->index('payjp_customer_id', 'casts_payjp_customer_id_index');
            }
        });

        // Add indexes to payments table for better performance
        Schema::table('payments', function (Blueprint $table) {
            // Add composite index for user lookups
            if (!Schema::hasIndex('payments', 'payments_user_lookup_index')) {
                $table->index(['user_id', 'user_type'], 'payments_user_lookup_index');
            }
            
            // Add index for customer-based queries
            if (!Schema::hasIndex('payments', 'payments_customer_lookup_index')) {
                $table->index(['payjp_customer_id', 'status'], 'payments_customer_lookup_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes safely using raw SQL to check existence
        $indexesToDrop = [
            ['guests', 'guests_payjp_customer_id_index'],
            ['casts', 'casts_payjp_customer_id_index'],
            ['payments', 'payments_user_lookup_index'],
            ['payments', 'payments_customer_lookup_index']
        ];
        
        foreach ($indexesToDrop as [$table, $indexName]) {
            $indexExists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            if (!empty($indexExists)) {
                DB::statement("DROP INDEX `{$indexName}` ON `{$table}`");
            }
        }
    }
}; 