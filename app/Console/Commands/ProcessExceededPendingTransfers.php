<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PointTransactionService;

class ProcessExceededPendingTransfers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:process-exceeded-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process exceeded pending transactions that are older than 2 days and transfer them to casts';

    /**
     * Execute the console command.
     */
    public function handle(PointTransactionService $pointTransactionService)
    {
        $this->info('Starting exceeded pending transfer process...');
        
        try {
            $processedCount = $pointTransactionService->processAutoTransferExceededPending();
            
            if ($processedCount > 0) {
                $this->info("Successfully processed {$processedCount} exceeded pending transactions.");
            } else {
                $this->info('No exceeded pending transactions found that are ready for transfer.');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to process exceeded pending transfers: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}