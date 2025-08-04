<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Receipt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class TestReceipt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:receipt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test receipt creation and table structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing receipt functionality...');

        // Check if table exists
        if (!Schema::hasTable('receipts')) {
            $this->error('Receipts table does not exist!');
            return 1;
        }

        $this->info('Receipts table exists.');

        // Check table structure
        $columns = Schema::getColumnListing('receipts');
        $this->info('Table columns:');
        foreach ($columns as $column) {
            $this->info('  - ' . $column);
        }

        // Try to create a test receipt
        try {
            $receipt = Receipt::create([
                'receipt_number' => 'TEST' . date('Ymd') . '001',
                'user_type' => 'guest',
                'user_id' => 1,
                'recipient_name' => 'テスト株式会社',
                'amount' => 10000,
                'tax_amount' => 1000,
                'tax_rate' => 10.00,
                'total_amount' => 11000,
                'purpose' => 'テスト利用料',
                'issued_at' => now(),
            ]);

            $this->info('Receipt created successfully with ID: ' . $receipt->id);
            
            // Clean up
            $receipt->delete();
            $this->info('Test receipt deleted.');

        } catch (\Exception $e) {
            $this->error('Error creating receipt: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        $this->info('Receipt test completed successfully!');
        return 0;
    }
}
