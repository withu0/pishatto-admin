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
            if (!Schema::hasColumn('casts', 'status')) {
                $table->string('status')->default('active');
            }
            if (!Schema::hasColumn('casts', 'name')) {
                $table->text('name')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casts', function (Blueprint $table) {
            if (Schema::hasColumn('casts', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('casts', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
