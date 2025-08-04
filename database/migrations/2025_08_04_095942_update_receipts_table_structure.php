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
            $table->dropForeign(['guest_id']);
            $table->dropIndex(['guest_id']);
            $table->dropColumn('guest_id');
            
            // Add new columns
            $table->string('receipt_number')->unique()->after('id');
            $table->enum('user_type', ['guest', 'cast'])->after('receipt_number');
            $table->unsignedBigInteger('user_id')->after('user_type');
            $table->string('recipient_name')->after('user_id');
            $table->decimal('amount', 10, 2)->after('recipient_name');
            $table->decimal('tax_amount', 10, 2)->after('amount');
            $table->decimal('tax_rate', 5, 2)->default(10.00)->after('tax_amount');
            $table->decimal('total_amount', 10, 2)->after('tax_rate');
            $table->string('purpose')->after('total_amount');
            $table->string('company_name')->default('株式会社キネカ')->after('purpose');
            $table->text('company_address')->default('〒106-0032 東京都港区六本木4丁目8-7六本木三河台ビル')->after('company_name');
            $table->string('company_phone')->default('TEL: 03-5860-6178')->after('company_address');
            $table->string('registration_number')->default('登録番号:T3010401129426')->after('company_phone');
            $table->enum('status', ['draft', 'issued', 'cancelled'])->default('issued')->after('registration_number');
            $table->string('pdf_url')->nullable()->after('status');
            $table->longText('html_content')->nullable()->after('pdf_url');
            
            // Add indexes
            $table->index(['user_type', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            // Remove new columns
            $table->dropColumn([
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
            ]);
            
            // Drop indexes
            $table->dropIndex(['user_type', 'user_id']);
            
            // Restore original columns
            $table->unsignedBigInteger('guest_id')->nullable();
            $table->index('guest_id');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade')->onUpdate('restrict');
        });
    }
};
