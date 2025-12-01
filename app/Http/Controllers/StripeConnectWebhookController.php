<?php

namespace App\Http\Controllers;

use App\Models\Cast;
use App\Models\CastPayout;
use App\Models\Payment;
use App\Services\CastPayoutService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeConnectWebhookController extends Controller
{
    public function __construct(private StripeService $stripeService)
    {
    }

    public function handle(Request $request)
    {
        $signature = $request->header('Stripe-Signature');

        if (!$signature) {
            return response()->json(['error' => 'Missing Stripe signature header'], 400);
        }

        try {
            $event = $this->stripeService->handleConnectWebhook($request->getContent(), $signature);
            $type = $event['type'] ?? null;

            switch ($type) {
                case 'account.updated':
                    $this->handleAccountUpdated($event['data']['object'] ?? []);
                    break;
                case 'payout.paid':
                case 'payout.failed':
                case 'payout.canceled':
                    $this->handlePayoutEvent($event['data']['object'] ?? [], $type);
                    break;
                default:
                    Log::info('Received Stripe Connect webhook', [
                        'type' => $type,
                    ]);
            }

            return response()->json(['success' => true]);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Stripe Connect webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe Connect webhook payload invalid', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Exception $e) {
            Log::error('Stripe Connect webhook processing error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    private function handleAccountUpdated(array $account): void
    {
        if (empty($account['id'])) {
            return;
        }

        $cast = Cast::where('stripe_connect_account_id', $account['id'])->first();

        if (!$cast) {
            Log::warning('Stripe Connect account updated but no cast found', [
                'account_id' => $account['id'],
            ]);
            return;
        }

        $requirements = $this->stripeService->summarizeAccountRequirements($account);
        $payoutsEnabled = (bool) ($account['payouts_enabled'] ?? false);
        $detailsSubmitted = (bool) ($account['details_submitted'] ?? false);

        $cast->forceFill([
            'payouts_enabled' => $payoutsEnabled,
            'stripe_onboarding_status' => $detailsSubmitted ? 'submitted' : 'incomplete',
            'stripe_requirements' => $requirements,
            'stripe_connect_last_synced_at' => now(),
            'stripe_connect_account_id' => $account['id'],
        ]);

        if ($payoutsEnabled && !$cast->stripe_payouts_enabled_at) {
            $cast->stripe_payouts_enabled_at = now();
        }

        $cast->save();

        Log::info('Stripe Connect account synced from webhook', [
            'cast_id' => $cast->id,
            'account_id' => $account['id'],
            'payouts_enabled' => $payoutsEnabled,
        ]);
    }

    private function handlePayoutEvent(array $payout, string $eventType): void
    {
        if (empty($payout['id'])) {
            return;
        }

        $payment = Payment::where('stripe_payout_id', $payout['id'])->with('castPayout')->first();

        if (!$payment) {
            Log::warning('Stripe Connect payout event without matching payment record', [
                'payout_id' => $payout['id'],
                'event_type' => $eventType,
            ]);
            return;
        }

        $status = match ($eventType) {
            'payout.paid' => 'paid',
            'payout.failed', 'payout.canceled' => 'failed',
            default => $payment->status,
        };

        $metadata = $payment->metadata ?? [];
        $metadata['stripe_payout_status'] = $payout['status'] ?? $metadata['stripe_payout_status'] ?? null;
        $metadata['last_payout_event'] = $eventType;

        $payment->update([
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : $payment->paid_at,
            'failed_at' => $status === 'failed' ? now() : $payment->failed_at,
            'metadata' => $metadata,
        ]);

        if ($payment->cast_payout_id && $payment->castPayout) {
            if ($status === 'paid') {
                app(CastPayoutService::class)->finalizePayout($payment->castPayout, $payment);
            } elseif ($status === 'failed') {
                $payment->castPayout->update(['status' => CastPayout::STATUS_FAILED]);
            }
        }

        Log::info('Stripe Connect payout status updated', [
            'payment_id' => $payment->id,
            'payout_id' => $payout['id'],
            'status' => $status,
        ]);
    }
}


