<?php

namespace App\Http\Controllers;

use App\Http\Requests\CastConnectAccountRequest;
use App\Http\Requests\CastOnboardingLinkRequest;
use App\Http\Requests\CastPayoutRequest;
use App\Models\Cast;
use App\Models\Payment;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CastConnectController extends Controller
{
    public function __construct(private StripeService $stripeService)
    {
    }

    public function ensureAccount(CastConnectAccountRequest $request, int $castId)
    {
        $cast = Cast::findOrFail($castId);
        $accountData = null;

        if ($cast->stripe_connect_account_id) {
            try {
                $accountData = $this->stripeService->retrieveAccount($cast->stripe_connect_account_id);
            } catch (\Exception $e) {
                Log::warning('Failed to retrieve existing Connect account, will recreate', [
                    'cast_id' => $cast->id,
                    'account_id' => $cast->stripe_connect_account_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($request->boolean('force_sync') && $cast->stripe_connect_account_id) {
            $accountData = $this->stripeService->retrieveAccount($cast->stripe_connect_account_id);
        }

        if (!$accountData) {
            $appUrl = config('app.url', 'http://localhost:8000');
            // Ensure URL is valid and absolute
            if (empty($appUrl) || !filter_var($appUrl, FILTER_VALIDATE_URL)) {
                $appUrl = 'http://localhost:8000';
            }

            $businessProfile = [
                'product_description' => $request->input('product_description', 'Pishatto cast services'),
            ];

            // Only add support_email if it's a valid email
            $supportEmail = $request->input('support_email', $this->inferCastEmail($cast));
            if (!empty($supportEmail) && filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
                $businessProfile['support_email'] = $supportEmail;
            }

            // Only add support_phone if it has a value
            $supportPhone = $request->input('support_phone', $cast->phone);
            if (!empty($supportPhone) && is_string($supportPhone)) {
                $businessProfile['support_phone'] = $supportPhone;
            }

            // Only add URL if it's a valid absolute URL and not localhost
            // Stripe doesn't accept localhost URLs for business_profile.url
            if (!empty($appUrl) && filter_var($appUrl, FILTER_VALIDATE_URL)) {
                $parsedUrl = parse_url($appUrl);
                // Only include URL if it's not localhost and has a valid domain
                if (isset($parsedUrl['host']) && 
                    $parsedUrl['host'] !== 'localhost' && 
                    $parsedUrl['host'] !== '127.0.0.1' &&
                    !preg_match('/^192\.168\./', $parsedUrl['host']) &&
                    !preg_match('/^10\./', $parsedUrl['host'])) {
                    $businessProfile['url'] = $appUrl;
                }
            }

            $accountData = $this->stripeService->createExpressAccount([
                'email' => $request->input('email', $this->inferCastEmail($cast)),
                'country' => $request->input('country', config('services.stripe.connect_default_country', 'HK')),
                'business_type' => $request->input('business_type', 'individual'),
                'metadata' => array_merge([
                    'cast_id' => $cast->id,
                    'cast_nickname' => $cast->nickname,
                    'line_id' => $cast->line_id,
                ], $request->input('metadata', [])),
                'business_profile' => $businessProfile,
            ]);
        }

        $accountSummary = $this->syncCastWithAccount($cast, $accountData);

        return response()->json([
            'success' => true,
            'account' => $accountSummary,
            'cast' => $cast->refresh(),
        ]);
    }

    public function createOnboardingLink(CastOnboardingLinkRequest $request, int $castId)
    {
        $cast = Cast::findOrFail($castId);

        if (!$cast->stripe_connect_account_id) {
            return response()->json([
                'success' => false,
                'error' => 'Stripe Connect account is not configured for this cast.',
            ], 404);
        }

        $appUrl = config('app.url', 'http://localhost:8000');
        // Ensure URL is valid and absolute
        if (empty($appUrl) || !filter_var($appUrl, FILTER_VALIDATE_URL)) {
            $appUrl = 'http://localhost:8000';
        }

        $refreshUrl = $request->input('refresh_url', rtrim($appUrl, '/') . '/cast/stripe/onboarding/retry');
        $returnUrl = $request->input('return_url', rtrim($appUrl, '/') . '/cast/stripe/onboarding/completed');
        $type = $request->input('type', 'account_onboarding');

        // Validate URLs before sending to Stripe
        if (!filter_var($refreshUrl, FILTER_VALIDATE_URL) || !filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid return or refresh URL. Please set APP_URL in your .env file.',
            ], 400);
        }

        try {
            $link = $this->stripeService->createOnboardingLink(
                $cast->stripe_connect_account_id,
                $refreshUrl,
                $returnUrl,
                $type
            );
        } catch (\Exception $e) {
            Log::error('Failed to generate Stripe onboarding link', [
                'cast_id' => $cast->id,
                'account_id' => $cast->stripe_connect_account_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate onboarding link.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'link' => $link,
        ]);
    }

    public function showAccount(Request $request, int $castId)
    {
        $cast = Cast::findOrFail($castId);

        if (!$cast->stripe_connect_account_id) {
            return response()->json([
                'success' => false,
                'error' => 'Stripe Connect account is not configured for this cast.',
            ], 404);
        }

        $shouldRefresh = $request->boolean('force', false)
            || $this->stripeService->shouldRefreshAccountStatus($cast->stripe_connect_last_synced_at);

        if ($shouldRefresh) {
            $accountData = $this->stripeService->retrieveAccount($cast->stripe_connect_account_id);
            $summary = $this->syncCastWithAccount($cast, $accountData);
        } else {
            $summary = $this->buildStoredAccountSnapshot($cast);
        }

        return response()->json([
            'success' => true,
            'account' => $summary,
        ]);
    }

    public function getAccountBalance(int $castId)
    {
        $cast = Cast::findOrFail($castId);

        if (!$cast->stripe_connect_account_id) {
            return response()->json([
                'success' => false,
                'error' => 'Stripe Connect account is not configured for this cast.',
            ], 404);
        }

        try {
            $balance = $this->stripeService->getAccountBalance($cast->stripe_connect_account_id);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve Stripe Connect account balance', [
                'cast_id' => $cast->id,
                'account_id' => $cast->stripe_connect_account_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve account balance.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'balance' => $balance,
        ]);
    }

    public function requestPayout(CastPayoutRequest $request, int $castId)
    {
        $cast = Cast::findOrFail($castId);

        if (!$cast->stripe_connect_account_id) {
            return response()->json([
                'success' => false,
                'error' => 'Stripe Connect account is not configured for this cast.',
            ], 404);
        }

        if (!$cast->payouts_enabled) {
            return response()->json([
                'success' => false,
                'error' => 'Payouts are not enabled for this cast account.',
                'requirements' => $cast->stripe_requirements,
            ], 422);
        }

        $amount = (int) $request->input('amount');
        $currency = strtolower($request->input('currency', 'jpy'));

        $metadata = array_merge([
            'cast_id' => $cast->id,
            'requested_by' => 'cast',
        ], $request->input('metadata', []));

        try {
            $payout = $this->stripeService->createPayout(
                $cast->stripe_connect_account_id,
                $amount,
                $currency,
                $metadata
            );
        } catch (\Exception $e) {
            Log::error('Stripe Connect payout creation failed', [
                'cast_id' => $cast->id,
                'account_id' => $cast->stripe_connect_account_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }

        $payment = Payment::create([
            'user_id' => $cast->id,
            'user_type' => 'cast',
            'amount' => $amount,
            'status' => 'pending',
            'payment_method' => 'payout',
            'stripe_payout_id' => $payout['id'] ?? null,
            'stripe_connect_account_id' => $cast->stripe_connect_account_id,
            'description' => $request->input('description', 'Stripe Connect payout'),
            'metadata' => array_merge($metadata, [
                'stripe_payout_status' => $payout['status'] ?? null,
                'currency' => $currency,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'payout' => $payout,
            'payment' => $payment,
        ]);
    }

    public function createLoginLink(int $castId)
    {
        $cast = Cast::findOrFail($castId);

        if (!$cast->stripe_connect_account_id) {
            return response()->json([
                'success' => false,
                'error' => 'Stripe Connect account is not configured for this cast.',
            ], 404);
        }

        try {
            $loginLink = $this->stripeService->createLoginLink($cast->stripe_connect_account_id);
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe Connect login link', [
                'cast_id' => $cast->id,
                'account_id' => $cast->stripe_connect_account_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create login link.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'link' => $loginLink,
        ]);
    }

    private function syncCastWithAccount(Cast $cast, array $accountData): array
    {
        $requirements = $this->stripeService->summarizeAccountRequirements($accountData);
        $payoutsEnabled = (bool) ($accountData['payouts_enabled'] ?? false);
        $detailsSubmitted = (bool) ($accountData['details_submitted'] ?? false);

        $cast->stripe_connect_account_id = $accountData['id'] ?? $cast->stripe_connect_account_id;
        $cast->stripe_requirements = $requirements;
        $cast->payouts_enabled = $payoutsEnabled;
        $cast->stripe_onboarding_status = $detailsSubmitted ? 'submitted' : 'incomplete';
        $cast->stripe_connect_last_synced_at = now();

        if ($payoutsEnabled && !$cast->stripe_payouts_enabled_at) {
            $cast->stripe_payouts_enabled_at = now();
        }

        $cast->save();

        return $this->stripeService->formatAccountStatus($accountData);
    }

    private function buildStoredAccountSnapshot(Cast $cast): array
    {
        return [
            'id' => $cast->stripe_connect_account_id,
            'payouts_enabled' => (bool) $cast->payouts_enabled,
            'charges_enabled' => null,
            'details_submitted' => $cast->stripe_onboarding_status === 'submitted',
            'requirements' => $cast->stripe_requirements ?? [],
            'needs_attention' => !$cast->payouts_enabled || !empty($cast->stripe_requirements['currently_due'] ?? []),
            'last_requirement_refresh' => optional($cast->stripe_connect_last_synced_at)->toISOString(),
        ];
    }

    private function inferCastEmail(Cast $cast): ?string
    {
        $paymentInfo = $cast->payment_info;

        if (empty($paymentInfo)) {
            return null;
        }

        $decoded = json_decode($paymentInfo, true);

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded['email'] ?? $decoded['contact_email'] ?? null;
    }
}

