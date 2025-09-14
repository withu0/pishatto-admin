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
        Schema::table('cast_applications', function (Blueprint $table) {
            // Add phone number field
            $table->string('phone_number')->nullable()->after('line_url');
            
            // Rename line_url to line_id
            $table->renameColumn('line_url', 'line_id');
            
            // Update status enum to support two-stage screening
            $table->dropColumn('status');
        });
        
        // Re-add status column with new enum values
        Schema::table('cast_applications', function (Blueprint $table) {
            $table->enum('status', [
                'pending',           // Initial state
                'preliminary_passed', // Passed preliminary screening
                'preliminary_rejected', // Rejected at preliminary screening
                'final_passed',      // Passed final screening
                'final_rejected'     // Rejected at final screening
            ])->default('pending')->after('phone_number');
        });
        
        // Add preliminary screening fields
        Schema::table('cast_applications', function (Blueprint $table) {
            $table->timestamp('preliminary_reviewed_at')->nullable()->after('reviewed_at');
            $table->integer('preliminary_reviewed_by')->nullable()->after('preliminary_reviewed_at');
            $table->text('preliminary_notes')->nullable()->after('preliminary_reviewed_by');
            
            // Rename existing fields to final screening
            $table->renameColumn('reviewed_at', 'final_reviewed_at');
            $table->renameColumn('reviewed_by', 'final_reviewed_by');
            $table->renameColumn('admin_notes', 'final_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cast_applications', function (Blueprint $table) {
            // Remove new fields
            $table->dropColumn('phone_number');
            $table->dropColumn('preliminary_reviewed_at');
            $table->dropColumn('preliminary_reviewed_by');
            $table->dropColumn('preliminary_notes');
            
            // Revert column names
            $table->renameColumn('line_id', 'line_url');
            $table->renameColumn('final_reviewed_at', 'reviewed_at');
            $table->renameColumn('final_reviewed_by', 'reviewed_by');
            $table->renameColumn('final_notes', 'admin_notes');
        });
        
        // Revert status enum
        Schema::table('cast_applications', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        
        Schema::table('cast_applications', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        });
    }
};
