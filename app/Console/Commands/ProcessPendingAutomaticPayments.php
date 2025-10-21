<?php

namespace App\Console\Commands;

use App\Services\AutomaticPaymentWithPendingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPendingAutomaticPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:process-pending-automatic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending automatic payments that are ready for capture (2 days old)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to process pending automatic payments...');

        try {
            $automaticPaymentService = app(AutomaticPaymentWithPendingService::class);
            $result = $automaticPaymentService->processPendingPaymentsForCapture();

            if ($result['success']) {
                $this->info("Successfully processed {$result['processed_count']} pending payments.");
                $this->info("Failed to process {$result['failed_count']} payments.");
                $this->info("Total found: {$result['total_found']} payments.");

                Log::info('Pending automatic payments processed', [
                    'processed_count' => $result['processed_count'],
                    'failed_count' => $result['failed_count'],
                    'total_found' => $result['total_found']
                ]);
            } else {
                $this->error("Failed to process pending payments: {$result['error']}");
                Log::error('Failed to process pending automatic payments', [
                    'error' => $result['error']
                ]);
            }

        } catch (\Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('ProcessPendingAutomaticPayments command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $this->info('Finished processing pending automatic payments.');
    }
}
