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
        // Drop indexes safely using raw SQL to check existence
        $indexesToDrop = [
            ['guests', 'guests_payjp_customer_id_index'],
            ['casts', 'casts_payjp_customer_id_index']
        ];
        
        foreach ($indexesToDrop as [$table, $indexName]) {
            $indexExists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            if (!empty($indexExists)) {
                DB::statement("DROP INDEX `{$indexName}` ON `{$table}`");
            }
        }
        
        // Drop columns safely
        Schema::table('guests', function (Blueprint $table) {
            if (Schema::hasColumn('guests', 'payjp_customer_id')) {
                $table->dropColumn('payjp_customer_id');
            }
        });

        Schema::table('casts', function (Blueprint $table) {
            if (Schema::hasColumn('casts', 'payjp_customer_id')) {
                $table->dropColumn('payjp_customer_id');
            }
        });
    }
}; 