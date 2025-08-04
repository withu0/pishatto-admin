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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->enum('user_type', ['guest', 'cast']);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('recipient_name');
            $table->decimal('amount', 10, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('tax_rate', 5, 2)->default(10.00);
            $table->decimal('total_amount', 10, 2);
            $table->string('purpose');
            $table->timestamp('issued_at')->useCurrent();
            $table->string('company_name')->default('株式会社キネカ');
            $table->text('company_address')->default('〒106-0032 東京都港区六本木4丁目8-7六本木三河台ビル');
            $table->string('company_phone')->default('TEL: 03-5860-6178');
            $table->string('registration_number')->default('登録番号:T3010401129426');
            $table->enum('status', ['draft', 'issued', 'cancelled'])->default('issued');
            $table->string('pdf_url')->nullable();
            $table->longText('html_content')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id']);
            $table->index('payment_id');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
