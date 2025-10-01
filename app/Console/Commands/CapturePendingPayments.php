<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CapturePendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:capture-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Capture pending payments that are scheduled for capture (2 days after authorization)';

    protected $stripeService;

    /**
     * Create a new command instance.
     */
    public function __construct(StripeService $stripeService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting capture of pending payments...');

        // Find payments that are pending and scheduled for capture
        $pendingPayments = Payment::where('status', 'pending')
            ->whereNotNull('stripe_payment_intent_id')
            ->where('created_at', '<=', Carbon::now()->subDays(2))
            ->where('is_automatic', true)
            ->get();

        if ($pendingPayments->isEmpty()) {
            $this->info('No pending payments found for capture.');
            return;
        }

        $this->info("Found {$pendingPayments->count()} pending payments to capture.");

        $successCount = 0;
        $failureCount = 0;

        foreach ($pendingPayments as $payment) {
            try {
                $this->info("Processing payment ID: {$payment->id} (Stripe PI: {$payment->stripe_payment_intent_id})");

                // Capture the payment intent
                $result = $this->stripeService->capturePaymentIntent($payment->stripe_payment_intent_id);

                if ($result['success']) {
                    // Update payment status to paid
                    $payment->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'captured_at' => now()->toISOString(),
                            'captured_amount' => $result['captured_amount'] ?? $payment->amount,
                            'capture_successful' => true
                        ])
                    ]);

                    $successCount++;
                    $this->info("✅ Successfully captured payment ID: {$payment->id}");

                    Log::info('Payment captured successfully', [
                        'payment_id' => $payment->id,
                        'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                        'amount' => $payment->amount,
                        'captured_amount' => $result['captured_amount'] ?? $payment->amount
                    ]);

                } else {
                    $failureCount++;
                    $this->error("❌ Failed to capture payment ID: {$payment->id} - {$result['error']}");

                    // Update payment status to failed
                    $payment->update([
                        'status' => 'failed',
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'capture_failed_at' => now()->toISOString(),
                            'capture_error' => $result['error'],
                            'capture_successful' => false
                        ])
                    ]);

                    Log::error('Payment capture failed', [
                        'payment_id' => $payment->id,
                        'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                        'error' => $result['error']
                    ]);
                }

            } catch (\Exception $e) {
                $failureCount++;
                $this->error("❌ Exception while processing payment ID: {$payment->id} - {$e->getMessage()}");

                // Update payment status to failed
                $payment->update([
                    'status' => 'failed',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'capture_failed_at' => now()->toISOString(),
                        'capture_error' => $e->getMessage(),
                        'capture_successful' => false
                    ])
                ]);

                Log::error('Payment capture exception', [
                    'payment_id' => $payment->id,
                    'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("Capture process completed:");
        $this->info("✅ Successfully captured: {$successCount} payments");
        $this->info("❌ Failed to capture: {$failureCount} payments");

        Log::info('Pending payments capture process completed', [
            'total_processed' => $pendingPayments->count(),
            'successful' => $successCount,
            'failed' => $failureCount
        ]);
    }
}
