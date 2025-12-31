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
            if (!Schema::hasColumn('casts', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('stripe_payouts_enabled_at');
            }
            if (!Schema::hasColumn('casts', 'branch_name')) {
                $table->string('branch_name')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('casts', 'account_type')) {
                $table->enum('account_type', ['普通', '当座'])->nullable()->after('branch_name');
            }
            if (!Schema::hasColumn('casts', 'account_number')) {
                $table->string('account_number')->nullable()->after('account_type');
            }
            if (!Schema::hasColumn('casts', 'account_holder_name')) {
                $table->string('account_holder_name')->nullable()->after('account_number');
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
                'bank_name',
                'branch_name',
                'account_type',
                'account_number',
                'account_holder_name',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('casts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
