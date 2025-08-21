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
        Schema::create('cast_applications', function (Blueprint $table) {
            $table->id();
            $table->string('line_url')->nullable();
            $table->string('front_image')->nullable(); // Path to front image
            $table->string('profile_image')->nullable(); // Path to profile image
            $table->string('full_body_image')->nullable(); // Path to full body image
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable(); // Admin notes for approval/rejection
            $table->timestamp('reviewed_at')->nullable();
            $table->integer('reviewed_by')->nullable(); // Admin user ID who reviewed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cast_applications');
    }
};
