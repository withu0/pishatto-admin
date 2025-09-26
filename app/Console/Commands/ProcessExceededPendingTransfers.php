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
    protected $description = 'Process exceeded pending transactions after 2 days and transfer to cast points';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting exceeded pending transactions processing...');

        $pointService = app(PointTransactionService::class);
        $processedCount = $pointService->processAutoTransferExceededPending();

        $this->info("Processed {$processedCount} exceeded pending transactions.");

        return Command::SUCCESS;
    }
}

