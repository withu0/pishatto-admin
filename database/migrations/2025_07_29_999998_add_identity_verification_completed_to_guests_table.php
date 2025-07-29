<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->enum('identity_verification_completed',['pending','success','failed'])->default('failed');
        });
    }

    public function down()
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('identity_verification_completed');
        });
    }
}; 