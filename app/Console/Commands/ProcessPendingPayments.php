<?php

namespace App\Console\Commands;

use App\Services\PendingPaymentCaptureService;
use Illuminate\Console\Command;

class ProcessPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:process-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending payments that are ready for capture after 2 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting pending payment processing...');

        try {
            $service = app(PendingPaymentCaptureService::class);
            $result = $service->processPendingPayments();

            if ($result['success']) {
                $this->info("Successfully processed {$result['processed_count']} pending payments.");
            } else {
                $this->error("Failed to process pending payments: {$result['error']}");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("Error processing pending payments: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
