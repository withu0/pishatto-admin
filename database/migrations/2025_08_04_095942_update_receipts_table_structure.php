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
        Schema::table('receipts', function (Blueprint $table) {
            // Drop existing columns
            // $table->dropForeign(['guest_id']);
            // $table->dropIndex(['guest_id']);
            // $table->dropColumn('guest_id');
            
            // Add new columns only if they don't exist
            if (!Schema::hasColumn('receipts', 'receipt_number')) {
                $table->string('receipt_number')->unique()->after('id');
            }
            if (!Schema::hasColumn('receipts', 'user_type')) {
                $table->enum('user_type', ['guest', 'cast'])->after('receipt_number');
            }
            if (!Schema::hasColumn('receipts', 'user_id')) {
                $table->unsignedBigInteger('user_id')->after('user_type');
            }
            if (!Schema::hasColumn('receipts', 'recipient_name')) {
                $table->string('recipient_name')->after('user_id');
            }
            if (!Schema::hasColumn('receipts', 'amount')) {
                $table->decimal('amount', 10, 2)->after('recipient_name');
            }
            if (!Schema::hasColumn('receipts', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->after('amount');
            }
            if (!Schema::hasColumn('receipts', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(10.00)->after('tax_amount');
            }
            if (!Schema::hasColumn('receipts', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->after('tax_rate');
            }
            if (!Schema::hasColumn('receipts', 'purpose')) {
                $table->string('purpose')->after('total_amount');
            }
            if (!Schema::hasColumn('receipts', 'company_name')) {
                $table->string('company_name')->after('purpose');
            }
            if (!Schema::hasColumn('receipts', 'company_address')) {
                $table->text('company_address')->after('company_name');
            }
            if (!Schema::hasColumn('receipts', 'company_phone')) {
                $table->string('company_phone')->after('company_address');
            }
            if (!Schema::hasColumn('receipts', 'registration_number')) {
                $table->string('registration_number')->after('company_phone');
            }
            if (!Schema::hasColumn('receipts', 'status')) {
                $table->enum('status', ['draft', 'issued', 'cancelled'])->after('registration_number');
            }
            if (!Schema::hasColumn('receipts', 'pdf_url')) {
                $table->string('pdf_url')->nullable()->after('status');
            }
            if (!Schema::hasColumn('receipts', 'html_content')) {
                $table->longText('html_content')->nullable()->after('pdf_url');
            }
            
            // Note: Index is already created in the original migration, so we don't need to add it here
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            // Remove new columns safely
            $columnsToDrop = [
                'receipt_number',
                'user_type',
                'user_id',
                'recipient_name',
                'amount',
                'tax_amount',
                'tax_rate',
                'total_amount',
                'purpose',
                'company_name',
                'company_address',
                'company_phone',
                'registration_number',
                'status',
                'pdf_url',
                'html_content'
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('receipts', $column)) {
                    $table->dropColumn($column);
                }
            }
            
            // Note: We don't drop the index here since it was created in the original migration
            // and will be handled when the original migration is rolled back
            
            // Restore original columns
            if (!Schema::hasColumn('receipts', 'guest_id')) {
                $table->unsignedBigInteger('guest_id')->nullable();
                $table->index('guest_id');
                $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade')->onUpdate('restrict');
            }
        });
    }
};
