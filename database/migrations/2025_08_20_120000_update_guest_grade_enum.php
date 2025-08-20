<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update guests.grade enum to include sapphire and emerald
        DB::statement("ALTER TABLE guests MODIFY grade ENUM('green','orange','bronze','silver','gold','sapphire','emerald','platinum','centurion') NOT NULL DEFAULT 'green'");
    }

    public function down(): void
    {
        // Revert to original enum set
        DB::statement("ALTER TABLE guests MODIFY grade ENUM('green','orange','bronze','silver','gold','platinum','centurion') NOT NULL DEFAULT 'green'");
    }
};

