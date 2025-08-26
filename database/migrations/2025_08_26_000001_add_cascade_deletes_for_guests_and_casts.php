<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // point_transactions: ensure guest_id and cast_id cascade on delete
        Schema::table('point_transactions', function (Blueprint $table) {
            // Drop existing FKs if present, then recreate with cascade
            if (Schema::hasColumn('point_transactions', 'guest_id')) {
                $table->dropForeign(['guest_id']);
                $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');
            }
            if (Schema::hasColumn('point_transactions', 'cast_id')) {
                $table->dropForeign(['cast_id']);
                $table->foreign('cast_id')->references('id')->on('casts')->onDelete('cascade');
            }
            if (Schema::hasColumn('point_transactions', 'reservation_id')) {
                // keep reservation delete behavior consistent (cascade)
                $table->dropForeign(['reservation_id']);
                $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');
            }
        });

        // reservations: add FK for guest_id with cascade (cast_id already handled by another migration with set null)
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'guest_id')) {
                // guest_id currently only indexed; convert to FK with cascade
                $table->dropIndex(['guest_id']);
                $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('point_transactions', 'guest_id')) {
                $table->dropForeign(['guest_id']);
                $table->foreign('guest_id')->references('id')->on('guests');
            }
            if (Schema::hasColumn('point_transactions', 'cast_id')) {
                $table->dropForeign(['cast_id']);
                $table->foreign('cast_id')->references('id')->on('casts');
            }
            if (Schema::hasColumn('point_transactions', 'reservation_id')) {
                $table->dropForeign(['reservation_id']);
                $table->foreign('reservation_id')->references('id')->on('reservations');
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'guest_id')) {
                $table->dropForeign(['guest_id']);
                $table->index('guest_id');
            }
        });
    }
};


