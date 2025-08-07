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
        Schema::table('concierge_messages', function (Blueprint $table) {
            $table->enum('message_type', ['inquiry', 'support', 'reservation', 'payment', 'technical', 'general'])->default('general')->after('message');
            $table->enum('category', ['urgent', 'normal', 'low'])->default('normal')->after('message_type');
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'closed'])->default('pending')->after('category');
            $table->text('admin_notes')->nullable()->after('status');
            $table->unsignedBigInteger('assigned_admin_id')->nullable()->after('admin_notes');
            $table->timestamp('resolved_at')->nullable()->after('assigned_admin_id');
            $table->string('user_agent')->nullable()->after('resolved_at');
            $table->string('ip_address')->nullable()->after('user_agent');
            $table->json('metadata')->nullable()->after('ip_address');
            
            $table->index(['message_type', 'category']);
            $table->index(['status', 'created_at']);
            $table->index('assigned_admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('concierge_messages', function (Blueprint $table) {
            // Drop indexes safely using raw SQL to check existence
            $indexesToDrop = [
                'concierge_messages_message_type_category_index',
                'concierge_messages_status_created_at_index',
                'concierge_messages_assigned_admin_id_index'
            ];
            
            foreach ($indexesToDrop as $indexName) {
                $indexExists = DB::select("SHOW INDEX FROM concierge_messages WHERE Key_name = ?", [$indexName]);
                if (!empty($indexExists)) {
                    DB::statement("DROP INDEX `{$indexName}` ON `concierge_messages`");
                }
            }
            
            // Drop columns safely
            $columnsToDrop = [
                'message_type',
                'category', 
                'status',
                'admin_notes',
                'assigned_admin_id',
                'resolved_at',
                'user_agent',
                'ip_address',
                'metadata'
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('concierge_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
