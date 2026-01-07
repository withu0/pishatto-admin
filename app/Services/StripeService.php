<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Refund;
use Stripe\Webhook;
use Stripe\Balance;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Payout;
use Stripe\Transfer;

class StripeService
{
    protected $secretKey;
    protected $publicKey;
    protected $webhookSecret;
    protected $clientId;
    protected $connectWebhookSecret;
    protected $connectRefreshIntervalMinutes;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret_key', env('STRIPE_SECRET_KEY'));
        $this->publicKey = config('services.stripe.public_key', env('STRIPE_PUBLIC_KEY'));
        $this->webhookSecret = config('services.stripe.webhook_secret', env('STRIPE_WEBHOOK_SECRET'));
        $this->clientId = config('services.stripe.client_id');
        $this->connectWebhookSecret = config('services.stripe.connect_webhook_secret');
        $this->connectRefreshIntervalMinutes = (int) config('services.stripe.connect_refresh_interval_minutes', 60);

        if (!$this->secretKey) {
            throw new \Exception('Stripe secret key is not configured');
        }

        // Set the API key for Stripe
        Stripe::setApiKey($this->secretKey);
    }

    /**
     * Create a customer
     */
    public function createCustomer($data)
    {
        try {
            $customerData = [
                'email' => $data['email'] ?? null,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ];

            // Remove null values
            $customerData = array_filter($customerData, function($value) {
                return $value !== null;
            });

            $customer = Customer::create($customerData);

            Log::info('Stripe customer created successfully', [
                'customer_id' => $customer->id,
                'email' => $customer->email
            ]);

            return $customer->toArray();

        } catch (Exception $e) {
            Log::error('Stripe customer creation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Get customer information
     */
    public function getCustomer($customerId)
    {
        try {
            $customer = Customer::retrieve($customerId);
            return $customer->toArray();
        } catch (Exception $e) {
            Log::error('Stripe customer retrieval failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent($data)
    {
        try {
            $paymentIntentData = [
                'amount' => $data['amount'], // Amount in cents
                'currency' => $data['currency'] ?? 'jpy',
                'description' => $data['description'] ?? 'Payment',
                'metadata' => $data['metadata'] ?? [],
            ];

            // Add customer if provided
            if (isset($data['customer_id'])) {
                $paymentIntentData['customer'] = $data['customer_id'];
            }

            // Add payment method if provided
            if (isset($data['payment_method'])) {
                $paymentIntentData['payment_method'] = $data['payment_method'];
                $paymentIntentData['confirm'] = true;
                $paymentIntentData['capture_method'] = 'automatic';
                $paymentIntentData['off_session'] = true;
                // Note: setup_future_usage is not needed here because SetupIntent during registration
                // already configures the payment method for off-session usage
                $paymentIntentData['return_url'] = config('app.url') . '/payment/return';
                $paymentIntentData['automatic_payment_methods'] = [
                    'enabled' => true,
                    'allow_redirects' => 'never'
                ];
            }

            $paymentIntent = PaymentIntent::create($paymentIntentData);

            // If payment method is provided, ensure it's properly attached
            if (isset($data['payment_method']) && isset($data['customer_id'])) {
                try {
                    // Attach payment method to customer if not already attached
                    $paymentMethod = PaymentMethod::retrieve($data['payment_method']);
                    if (!$paymentMethod->customer) {
                        $paymentMethod->attach(['customer' => $data['customer_id']]);
                    }
                } catch (Exception $e) {
                    Log::warning('Payment method attachment failed', [
                        'payment_method' => $data['payment_method'],
                        'customer_id' => $data['customer_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Handle different payment intent statuses
            if ($paymentIntent->status === 'requires_action') {
                // Payment requires 3D Secure authentication
                Log::info('Payment requires 3D Secure authentication', [
                    'payment_intent_id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret
                ]);

                // For server-side payments, we can't handle 3DS interactively
                // Return the client_secret for frontend handling
                return [
                    'id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'client_secret' => $paymentIntent->client_secret,
                    'requires_action' => true,
                    'next_action' => $paymentIntent->next_action
                ];
            } elseif ($paymentIntent->status !== 'succeeded') {
                // Try to confirm the payment intent
                try {
                    $paymentIntent->confirm(['payment_method' => $data['payment_method']]);
                    Log::info('Payment intent confirmed', [
                        'payment_intent_id' => $paymentIntent->id,
                        'status' => $paymentIntent->status
                    ]);
                } catch (Exception $e) {
                    Log::warning('Payment intent confirmation failed', [
                        'payment_intent_id' => $paymentIntent->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Stripe payment intent created', [
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount,
                'status' => $paymentIntent->status,
                'confirmation_method' => $paymentIntent->confirmation_method,
                'capture_method' => $paymentIntent->capture_method
            ]);

            return $paymentIntent->toArray();

        } catch (Exception $e) {
            Log::error('Stripe payment intent creation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Confirm a payment intent
     */
    public function confirmPaymentIntent($paymentIntentId, $paymentMethodId = null)
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            if ($paymentMethodId) {
                $paymentIntent->confirm([
                    'payment_method' => $paymentMethodId
                ]);
            } else {
                $paymentIntent->confirm();
            }

            return $paymentIntent->toArray();

        } catch (Exception $e) {
            Log::error('Stripe payment intent confirmation failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Complete a payment intent after 3DS authentication
     */
    public function completePaymentIntent($paymentIntentId)
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            // If the payment intent is still in requires_action status,
            // we need to confirm it again after 3DS completion
            if ($paymentIntent->status === 'requires_action') {
                $paymentIntent->confirm();
            }

            return $paymentIntent->toArray();

        } catch (Exception $e) {
            Log::error('Stripe payment intent completion failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get payment intent
     */
    public function getPaymentIntent($paymentIntentId)
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            return $paymentIntent->toArray();
        } catch (Exception $e) {
            Log::error('Stripe payment intent retrieval failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a payment method
     */
    public function createPaymentMethod($data)
    {
        try {
            $paymentMethod = PaymentMethod::create([
                'type' => 'card',
                'card' => [
                    'number' => $data['number'],
                    'exp_month' => $data['exp_month'],
                    'exp_year' => $data['exp_year'],
                    'cvc' => $data['cvc'],
                ],
                'billing_details' => $data['billing_details'] ?? [],
            ]);

            return $paymentMethod->toArray();

        } catch (Exception $e) {
            Log::error('Stripe payment method creation failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Attach payment method to customer
     */
    public function attachPaymentMethod($paymentMethodId, $customerId)
    {
        try {
            // First, verify the PaymentMethod exists with retry logic
            // Sometimes there's a slight delay after creation
            $paymentMethod = null;
            $maxRetries = 3;
            $retryDelay = 0.5; // 500ms

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    Log::info('Attempting to retrieve PaymentMethod', [
                        'payment_method_id' => $paymentMethodId,
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries
                    ]);

                    $paymentMethod = PaymentMethod::retrieve($paymentMethodId);

                    // Successfully retrieved
                    Log::info('PaymentMethod retrieved successfully', [
                        'payment_method_id' => $paymentMethodId,
                        'attempt' => $attempt,
                        'payment_method_type' => $paymentMethod->type ?? 'unknown',
                        'created' => $paymentMethod->created ?? null
                    ]);
                    break;

                } catch (Exception $e) {
                    // Check if it's a "No such PaymentMethod" error
                    if (strpos($e->getMessage(), 'No such PaymentMethod') !== false) {
                        if ($attempt < $maxRetries) {
                            // Wait before retrying
                            Log::warning('PaymentMethod not found, retrying...', [
                                'payment_method_id' => $paymentMethodId,
                                'attempt' => $attempt,
                                'max_retries' => $maxRetries,
                                'retry_delay_seconds' => $retryDelay
                            ]);
                            usleep($retryDelay * 1000000); // Convert to microseconds
                            continue;
                        } else {
                            // Final attempt failed
                            Log::error('PaymentMethod does not exist in Stripe after all retries', [
                                'payment_method_id' => $paymentMethodId,
                                'customer_id' => $customerId,
                                'error' => $e->getMessage(),
                                'attempts' => $attempt,
                                'suggestion' => 'The PaymentMethod may have been deleted, expired, or created on a different Stripe account. Please create a new PaymentMethod.'
                            ]);

                            // Throw a more user-friendly error
                            throw new Exception(
                                'PaymentMethod does not exist. This may happen if the card information was already used or expired. Please enter your card information again.',
                                404
                            );
                        }
                    }
                    // Re-throw other errors immediately
                    throw $e;
                }
            }

            if (!$paymentMethod) {
                throw new Exception('Failed to retrieve PaymentMethod after all retry attempts', 500);
            }

            // Check if PaymentMethod is already attached to a customer
            if ($paymentMethod->customer) {
                if ($paymentMethod->customer === $customerId) {
                    // Already attached to this customer, return success
                    Log::info('PaymentMethod already attached to customer', [
                        'payment_method_id' => $paymentMethodId,
                        'customer_id' => $customerId
                    ]);
                    return $paymentMethod->toArray();
                } else {
                    // Attached to a different customer - detach first
                    Log::warning('PaymentMethod attached to different customer, detaching first', [
                        'payment_method_id' => $paymentMethodId,
                        'old_customer_id' => $paymentMethod->customer,
                        'new_customer_id' => $customerId
                    ]);
                    $paymentMethod->detach();
                }
            }

            // Attach to the customer
            $paymentMethod->attach(['customer' => $customerId]);

            Log::info('PaymentMethod successfully attached to customer', [
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId
            ]);

            return $paymentMethod->toArray();

        } catch (Exception $e) {
            Log::error('Stripe payment method attachment failed', [
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get customer's payment methods
     */
    public function getCustomerPaymentMethods($customerId)
    {
        try {
            $paymentMethods = PaymentMethod::all([
                'customer' => $customerId,
                'type' => 'card',
            ]);

            return $paymentMethods->toArray();

        } catch (Exception $e) {
            Log::error('Stripe customer payment methods retrieval failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete a payment method
     */
    public function deletePaymentMethod($paymentMethodId)
    {
        try {
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->detach();

            return ['deleted' => true];

        } catch (Exception $e) {
            Log::error('Stripe payment method deletion failed', [
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a refund
     */
    public function createRefund($paymentIntentId, $amount = null)
    {
        try {
            $refundData = ['payment_intent' => $paymentIntentId];
            if ($amount !== null) {
                $refundData['amount'] = $amount;
            }

            $refund = Refund::create($refundData);

            return $refund->toArray();

        } catch (Exception $e) {
            Log::error('Stripe refund creation failed', [
                'payment_intent_id' => $paymentIntentId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process a payment using Stripe with manual capture (for delayed processing)
     */
    public function processPaymentWithManualCapture($paymentData)
    {
        try {
            // Create payment intent with manual capture
            $paymentIntentData = [
                'amount' => $paymentData['amount'], // Amount in cents
                'currency' => 'jpy',
                'description' => $paymentData['description'] ?? 'Payment',
                'metadata' => $paymentData['metadata'] ?? [],
                'capture_method' => 'manual', // Manual capture for delayed processing
            ];

            // Add customer if provided
            if (isset($paymentData['customer_id'])) {
                $paymentIntentData['customer'] = $paymentData['customer_id'];

                // Get customer's default payment method
                if (!isset($paymentData['payment_method'])) {
                    try {
                        $paymentMethods = PaymentMethod::all([
                            'customer' => $paymentData['customer_id'],
                            'type' => 'card',
                        ]);

                        if (!empty($paymentMethods->data)) {
                            $paymentMethodId = $paymentMethods->data[0]->id;

                            // Ensure payment method is attached to customer
                            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
                            if (!$paymentMethod->customer) {
                                $paymentMethod->attach(['customer' => $paymentData['customer_id']]);
                            }

                            // Set payment method and configuration
                            $paymentIntentData['payment_method'] = $paymentMethodId;
                            $paymentIntentData['confirm'] = true;
                            $paymentIntentData['off_session'] = true;
                            // Note: setup_future_usage is not needed here because SetupIntent during registration
                            // already configures the payment method for off-session usage
                            $paymentIntentData['return_url'] = config('app.url') . '/payment/return';
                            $paymentIntentData['automatic_payment_methods'] = [
                                'enabled' => true,
                                'allow_redirects' => 'never'
                            ];

                            Log::info('Using customer default payment method for manual capture', [
                                'customer_id' => $paymentData['customer_id'],
                                'payment_method_id' => $paymentMethodId
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::warning('Failed to get customer payment methods for manual capture', [
                            'customer_id' => $paymentData['customer_id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Add payment method if provided (for direct payment method usage)
            if (isset($paymentData['payment_method']) && !isset($paymentIntentData['payment_method'])) {
                $paymentIntentData['payment_method'] = $paymentData['payment_method'];
                $paymentIntentData['confirm'] = true;
                $paymentIntentData['off_session'] = true;
                // Note: setup_future_usage is not needed here because SetupIntent during registration
                // already configures the payment method for off-session usage
                $paymentIntentData['return_url'] = config('app.url') . '/payment/return';
                $paymentIntentData['automatic_payment_methods'] = [
                    'enabled' => true,
                    'allow_redirects' => 'never'
                ];
            }

            // Log the payment intent data being sent to Stripe
            Log::info('Creating payment intent with manual capture', [
                'payment_intent_data' => $paymentIntentData
            ]);

            $paymentIntent = PaymentIntent::create($paymentIntentData);

            Log::info('Stripe payment intent created with manual capture', [
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'confirmation_method' => $paymentIntent->confirmation_method,
                'capture_method' => $paymentIntent->capture_method
            ]);

            // Handle different payment intent statuses
            if ($paymentIntent->status === 'requires_confirmation' && isset($paymentIntentData['payment_method'])) {
                try {
                    $paymentIntent->confirm(['payment_method' => $paymentIntentData['payment_method']]);
                    Log::info('Payment intent confirmed for manual capture', [
                        'payment_intent_id' => $paymentIntent->id,
                        'status' => $paymentIntent->status
                    ]);
                } catch (Exception $e) {
                    Log::warning('Payment intent confirmation failed for manual capture', [
                        'payment_intent_id' => $paymentIntent->id,
                        'error' => $e->getMessage()
                    ]);
                }
            } elseif ($paymentIntent->status === 'requires_payment_method' && isset($paymentIntentData['payment_method'])) {
                // If payment intent still requires payment method, try to update it
                try {
                    $paymentIntent->payment_method = $paymentIntentData['payment_method'];
                    $paymentIntent->save();
                    $paymentIntent->confirm(['payment_method' => $paymentIntentData['payment_method']]);
                    Log::info('Payment intent updated and confirmed for manual capture', [
                        'payment_intent_id' => $paymentIntent->id,
                        'status' => $paymentIntent->status
                    ]);
                } catch (Exception $e) {
                    Log::warning('Payment intent update failed for manual capture', [
                        'payment_intent_id' => $paymentIntent->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // For manual capture, payment is successful if it's authorized (requires_capture)
            $isPaymentAuthorized = in_array($paymentIntent->status, ['requires_capture', 'succeeded']);

            // Create payment record in database
            $payment = \App\Models\Payment::create([
                'user_id' => $paymentData['user_id'],
                'user_type' => $paymentData['user_type'],
                'amount' => $paymentData['amount'],
                'payment_method' => $paymentData['payment_method_type'] ?? 'card',
                'status' => $isPaymentAuthorized ? 'pending' : 'failed',
                'description' => $paymentData['description'] ?? 'Payment',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'stripe_customer_id' => $paymentData['customer_id'] ?? null,
                'metadata' => $paymentData['metadata'] ?? [],
                'paid_at' => null, // Will be set when payment is captured
            ]);

            return [
                'success' => $isPaymentAuthorized,
                'payment' => $payment,
                'payment_intent' => $paymentIntent->toArray(),
            ];

        } catch (Exception $e) {
            Log::error('Manual capture payment processing failed', [
                'payment_data' => $paymentData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process a payment using Stripe
     */
    public function processPayment($paymentData)
    {
        try {
            // Create payment intent
            $captureMethod = $paymentData['capture_method'] ?? 'automatic';
            $paymentIntentData = [
                'amount' => $paymentData['amount'], // Amount in cents
                'currency' => 'jpy',
                'description' => $paymentData['description'] ?? 'Payment',
                'metadata' => $paymentData['metadata'] ?? [],
                'capture_method' => $captureMethod,
            ];

            // Add customer if provided
            if (isset($paymentData['customer_id'])) {
                $paymentIntentData['customer'] = $paymentData['customer_id'];

                // If no payment method provided, get customer's default payment method
                if (!isset($paymentData['payment_method'])) {
                    try {
                        $paymentMethods = PaymentMethod::all([
                            'customer' => $paymentData['customer_id'],
                            'type' => 'card',
                        ]);

                        if (!empty($paymentMethods->data)) {
                            $paymentMethodId = $paymentMethods->data[0]->id;

                            // Ensure payment method is attached to customer
                            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
                            if (!$paymentMethod->customer) {
                                $paymentMethod->attach(['customer' => $paymentData['customer_id']]);
                            }

                            // Set payment method and configuration for off-session payment
                            $paymentIntentData['payment_method'] = $paymentMethodId;
                            $paymentIntentData['off_session'] = true;
                            $paymentIntentData['confirm'] = true; // Confirm directly for off-session
                            $paymentIntentData['capture_method'] = $captureMethod;
                            $paymentIntentData['return_url'] = config('app.url') . '/payment/return';
                            $paymentIntentData['automatic_payment_methods'] = [
                                'enabled' => true,
                                'allow_redirects' => 'never' // Disable redirects for off-session
                            ];

                            Log::info('Using customer default payment method for off-session payment', [
                                'customer_id' => $paymentData['customer_id'],
                                'payment_method_id' => $paymentMethodId
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::warning('Failed to get customer payment methods', [
                            'customer_id' => $paymentData['customer_id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Add payment method if provided (for direct payment method usage)
            if (isset($paymentData['payment_method']) && !isset($paymentIntentData['payment_method'])) {
                $paymentIntentData['payment_method'] = $paymentData['payment_method'];
                $paymentIntentData['off_session'] = true;
                $paymentIntentData['confirm'] = true; // Confirm directly for off-session
                $paymentIntentData['capture_method'] = $captureMethod;
                $paymentIntentData['return_url'] = config('app.url') . '/payment/return';
                $paymentIntentData['automatic_payment_methods'] = [
                    'enabled' => true,
                    'allow_redirects' => 'never' // Disable redirects for off-session
                ];
            }

            // Log the payment intent data being sent to Stripe
            Log::info('Creating payment intent with data', [
                'payment_intent_data' => $paymentIntentData
            ]);

            $paymentIntent = null;

            try {
                $paymentIntent = PaymentIntent::create($paymentIntentData);

                Log::info('Stripe payment intent created', [
                    'payment_intent_id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'confirmation_method' => $paymentIntent->confirmation_method,
                    'capture_method' => $paymentIntent->capture_method
                ]);
            } catch (Exception $createException) {
                // Check if this is an on-session error during creation
                $errorMessage = $createException->getMessage();
                $isOnSessionError = stripos($errorMessage, 'on-session action') !== false ||
                    stripos($errorMessage, 'on_session') !== false ||
                    stripos($errorMessage, 'requires an on-session') !== false;

                if ($isOnSessionError) {
                    // Try to get payment intent from exception
                    $paymentIntentId = null;

                    if (method_exists($createException, 'getJsonBody')) {
                        $errorBody = $createException->getJsonBody();
                        if (isset($errorBody['error']['payment_intent'])) {
                            $paymentIntentId = is_string($errorBody['error']['payment_intent'])
                                ? $errorBody['error']['payment_intent']
                                : ($errorBody['error']['payment_intent']['id'] ?? null);
                        }
                    }

                    if (property_exists($createException, 'payment_intent')) {
                        $paymentIntentId = is_string($createException->payment_intent)
                            ? $createException->payment_intent
                            : ($createException->payment_intent->id ?? null);
                    }

                    if ($paymentIntentId) {
                        try {
                            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

                            Log::info('Retrieved payment intent from create exception for on-session', [
                                'payment_intent_id' => $paymentIntentId,
                                'status' => $paymentIntent->status
                            ]);

                            // Return early with payment intent details for on-session authentication
                            return [
                                'success' => false,
                                'error' => $errorMessage,
                                'requires_on_session' => true,
                                'payment_intent_id' => $paymentIntentId,
                                'client_secret' => $paymentIntent->client_secret,
                                'status' => $paymentIntent->status,
                                'requires_authentication' => true,
                                'payment_intent' => [
                                    'id' => $paymentIntent->id,
                                    'client_secret' => $paymentIntent->client_secret,
                                    'status' => $paymentIntent->status,
                                    'next_action' => $paymentIntent->next_action ?? null
                                ]
                            ];
                        } catch (\Exception $retrieveError) {
                            Log::error('Failed to retrieve payment intent from create exception', [
                                'payment_intent_id' => $paymentIntentId,
                                'error' => $retrieveError->getMessage()
                            ]);
                            // Re-throw the original exception
                            throw $createException;
                        }
                    } else {
                        // Re-throw if we can't get payment intent
                        throw $createException;
                    }
                } else {
                    // Re-throw if it's not an on-session error
                    throw $createException;
                }
            }

            // If payment intent is null at this point, something went wrong
            if (!$paymentIntent) {
                throw new Exception('Failed to create payment intent');
            }

            // Handle requires_action status (3D Secure or other authentication required)
            if ($paymentIntent->status === 'requires_action') {
                Log::warning('Payment requires action (3D Secure authentication)', [
                    'payment_intent_id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'next_action' => $paymentIntent->next_action
                ]);

                // Return payment intent details for on-session completion
                return [
                    'success' => false,
                    'error' => 'Card requires authentication. Please complete the authentication process.',
                    'requires_on_session' => true,
                    'payment_intent_id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                    'status' => $paymentIntent->status,
                    'requires_authentication' => true,
                    'payment_intent' => [
                        'id' => $paymentIntent->id,
                        'client_secret' => $paymentIntent->client_secret,
                        'status' => $paymentIntent->status,
                        'next_action' => $paymentIntent->next_action ?? null
                    ]
                ];
            }


            // Validate payment intent status
            $statusInfo = $this->validatePaymentIntentStatus($paymentIntent);
            $isAuthorizedManual = ($captureMethod === 'manual') && in_array($paymentIntent->status, ['requires_capture', 'succeeded']);
            $isPaymentSuccessful = ($captureMethod === 'automatic') ? $statusInfo['is_successful'] : $isAuthorizedManual;

            // Update existing payment record or create new one
            if (isset($paymentData['payment_id'])) {
                $payment = \App\Models\Payment::find($paymentData['payment_id']);
                if ($payment) {
                    $payment->update([
                        'status' => $isPaymentSuccessful ? (($captureMethod === 'automatic' && $paymentIntent->status === 'succeeded') ? 'paid' : 'pending') : 'failed',
                        'stripe_payment_intent_id' => $paymentIntent->id,
                        'paid_at' => ($captureMethod === 'automatic' && $paymentIntent->status === 'succeeded') ? now() : null,
                    ]);
                } else {
                    Log::warning('Payment record not found for update', [
                        'payment_id' => $paymentData['payment_id']
                    ]);
                    // Create new payment record if not found
                    $payment = \App\Models\Payment::create([
                        'user_id' => $paymentData['user_id'],
                        'user_type' => $paymentData['user_type'],
                        'amount' => $paymentData['amount'],
                        'payment_method' => $paymentData['payment_method_type'] ?? 'card',
                        'status' => $isPaymentSuccessful ? (($captureMethod === 'automatic' && $paymentIntent->status === 'succeeded') ? 'paid' : 'pending') : 'failed',
                        'description' => $paymentData['description'] ?? 'Payment',
                        'stripe_payment_intent_id' => $paymentIntent->id,
                        'stripe_customer_id' => $paymentData['customer_id'] ?? null,
                        'metadata' => $paymentData['metadata'] ?? [],
                        'paid_at' => ($captureMethod === 'automatic' && $paymentIntent->status === 'succeeded') ? now() : null,
                    ]);
                }
            } else {
                // Create payment record in database
                $payment = \App\Models\Payment::create([
                    'user_id' => $paymentData['user_id'],
                    'user_type' => $paymentData['user_type'],
                    'amount' => $paymentData['amount'],
                    'payment_method' => $paymentData['payment_method_type'] ?? 'card',
                    'status' => $isPaymentSuccessful ? (($captureMethod === 'automatic' && $paymentIntent->status === 'succeeded') ? 'paid' : 'pending') : 'failed',
                    'description' => $paymentData['description'] ?? 'Payment',
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'stripe_customer_id' => $paymentData['customer_id'] ?? null,
                    'metadata' => $paymentData['metadata'] ?? [],
                    'paid_at' => ($captureMethod === 'automatic' && $paymentIntent->status === 'succeeded') ? now() : null,
                ]);
            }

            return [
                'success' => $isPaymentSuccessful,
                'payment' => $payment,
                'payment_intent' => $paymentIntent->toArray(),
            ];

        } catch (Exception $e) {
            Log::error('Payment processing failed', [
                'payment_data' => $paymentData,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            // Check if this is an on-session error
            $errorMessage = $e->getMessage();
            $isOnSessionError = stripos($errorMessage, 'on-session action') !== false ||
                stripos($errorMessage, 'on_session') !== false ||
                stripos($errorMessage, 'requires an on-session') !== false ||
                stripos($errorMessage, 'on-session') !== false;

            if ($isOnSessionError) {
                // Try to get payment intent from exception
                $paymentIntentId = null;
                $clientSecret = null;

                // Check if exception has payment_intent property
                if (method_exists($e, 'getJsonBody')) {
                    $errorBody = $e->getJsonBody();
                    if (isset($errorBody['error']['payment_intent'])) {
                        $paymentIntentId = is_string($errorBody['error']['payment_intent'])
                            ? $errorBody['error']['payment_intent']
                            : ($errorBody['error']['payment_intent']['id'] ?? null);
                    }
                }

                if (property_exists($e, 'payment_intent')) {
                    $paymentIntentId = is_string($e->payment_intent)
                        ? $e->payment_intent
                        : ($e->payment_intent->id ?? null);
                }

                // If we have payment intent ID, retrieve it
                if ($paymentIntentId) {
                    try {
                        $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
                        $clientSecret = $paymentIntent->client_secret;

                        Log::info('Retrieved payment intent from processPayment error for on-session', [
                            'payment_intent_id' => $paymentIntentId,
                            'status' => $paymentIntent->status
                        ]);

                        return [
                            'success' => false,
                            'error' => $errorMessage,
                            'requires_on_session' => true,
                            'payment_intent_id' => $paymentIntentId,
                            'client_secret' => $clientSecret,
                            'status' => $paymentIntent->status,
                            'requires_authentication' => true,
                            'payment_intent' => [
                                'id' => $paymentIntent->id,
                                'client_secret' => $paymentIntent->client_secret,
                                'status' => $paymentIntent->status,
                                'next_action' => $paymentIntent->next_action ?? null
                            ]
                        ];
                    } catch (\Exception $retrieveError) {
                        Log::error('Failed to retrieve payment intent in processPayment catch', [
                            'payment_intent_id' => $paymentIntentId,
                            'error' => $retrieveError->getMessage()
                        ]);
                    }
                } else {
                    Log::warning('On-session error detected but no payment intent ID found in exception', [
                        'error_message' => $errorMessage,
                        'exception_class' => get_class($e)
                    ]);
                }
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Capture a payment intent (for manual capture payments)
     */
    public function capturePaymentIntent($paymentIntentId, $amount = null)
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'requires_capture') {
                Log::warning('Payment intent is not in requires_capture status', [
                    'payment_intent_id' => $paymentIntentId,
                    'current_status' => $paymentIntent->status
                ]);

                return [
                    'success' => false,
                    'error' => "Payment intent is not in requires_capture status. Current status: {$paymentIntent->status}"
                ];
            }

            $captureData = [];
            if ($amount !== null) {
                $captureData['amount_to_capture'] = $amount;
            }

            $capturedPaymentIntent = $paymentIntent->capture($captureData);

            Log::info('Payment intent captured successfully', [
                'payment_intent_id' => $paymentIntentId,
                'captured_amount' => $capturedPaymentIntent->amount_received,
                'status' => $capturedPaymentIntent->status
            ]);

            return [
                'success' => true,
                'payment_intent' => $capturedPaymentIntent->toArray(),
                'captured_amount' => $capturedPaymentIntent->amount_received
            ];

        } catch (Exception $e) {
            Log::error('Payment intent capture failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle webhook
     */
    public function handleWebhook($payload, $signature)
    {
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);

            // Handle different event types
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    Log::info('Payment succeeded', [
                        'payment_intent_id' => $event->data->object->id
                    ]);

                    // Update payment status in database
                    $payment = \App\Models\Payment::where('stripe_payment_intent_id', $event->data->object->id)->first();
                    if ($payment && $payment->status !== 'paid') {
                        // Check metadata to see if points were already credited
                        $metadata = is_string($payment->metadata) ? json_decode($payment->metadata, true) : ($payment->metadata ?? []);
                        $pointsCredited = $metadata['points_credited'] ?? false;

                        $payment->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        // Add points to user if not already added (idempotent check)
                        if (!$pointsCredited) {
                            if ($payment->user_type === 'guest') {
                                $model = \App\Models\Guest::find($payment->user_id);
                            } else {
                                $model = \App\Models\Cast::find($payment->user_id);
                            }

                            if ($model) {
                                $yenPerPoint = (float) config('points.yen_per_point', 1.2);
                                $pointsToCredit = (int) floor(((float) $payment->amount) / max(0.0001, $yenPerPoint));

                                $currentPoints = $model->points ?? 0;
                                $newPoints = $currentPoints + $pointsToCredit;
                                $model->points = $newPoints;
                                $model->save();

                                // Mark points as credited in payment metadata
                                $metadata['points_credited'] = true;
                                $metadata['points_credited_at'] = now()->toISOString();
                                $metadata['points_credited_by'] = 'webhook';
                                $payment->metadata = json_encode($metadata);
                                $payment->save();

                                Log::info('Points added via webhook', [
                                    'user_id' => $payment->user_id,
                                    'user_type' => $payment->user_type,
                                    'points_added' => $pointsToCredit,
                                    'new_balance' => $newPoints
                                ]);
                            }
                        } else {
                            Log::info('Points already credited for this payment, skipping webhook fulfillment', [
                                'payment_id' => $payment->id,
                                'payment_intent_id' => $event->data->object->id
                            ]);
                        }
                    }
                    break;

                case 'payment_intent.payment_failed':
                    Log::warning('Payment failed', [
                        'payment_intent_id' => $event->data->object->id
                    ]);

                    // Update payment status to failed
                    $payment = \App\Models\Payment::where('stripe_payment_intent_id', $event->data->object->id)->first();
                    if ($payment) {
                        $payment->update([
                            'status' => 'failed',
                            'failed_at' => now(),
                        ]);
                    }
                    break;

                case 'setup_intent.succeeded':
                    Log::info('SetupIntent succeeded', [
                        'setup_intent_id' => $event->data->object->id
                    ]);

                    try {
                        // Retrieve SetupIntent with expanded payment_method
                        $setupIntent = \Stripe\SetupIntent::retrieve($event->data->object->id, [
                            'expand' => ['payment_method']
                        ]);

                        $customerId = $setupIntent->customer;
                        $paymentMethodId = $setupIntent->payment_method;

                        if (!$customerId || !$paymentMethodId) {
                            Log::warning('SetupIntent missing customer or payment_method', [
                                'setup_intent_id' => $setupIntent->id,
                                'has_customer' => !!$customerId,
                                'has_payment_method' => !!$paymentMethodId
                            ]);
                            break;
                        }

                        // Ensure PaymentMethod is attached to Customer
                        if (is_string($paymentMethodId)) {
                            $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
                        } else {
                            $paymentMethod = $paymentMethodId;
                        }

                        if ($paymentMethod->customer !== $customerId) {
                            $paymentMethod->attach(['customer' => $customerId]);
                            Log::info('PaymentMethod attached to Customer via webhook', [
                                'payment_method_id' => $paymentMethod->id,
                                'customer_id' => $customerId
                            ]);
                        }

                        // Find user by customer_id and update payment_info
                        $guest = \App\Models\Guest::where('stripe_customer_id', $customerId)->first();
                        $cast = null;
                        if (!$guest) {
                            $cast = \App\Models\Cast::where('stripe_customer_id', $customerId)->first();
                        }

                        $model = $guest ?? $cast;
                        if ($model) {
                            $paymentInfo = json_decode($model->payment_info, true) ?: [];

                            // Check if this SetupIntent was already processed (idempotency)
                            $processedSetupIntents = $paymentInfo['processed_setup_intents'] ?? [];
                            if (!in_array($setupIntent->id, $processedSetupIntents)) {
                                $paymentInfo['last_payment_method'] = $paymentMethod->id;
                                $paymentInfo['last_card_added'] = now()->toISOString();
                                $paymentInfo['card_count'] = ($paymentInfo['card_count'] ?? 0) + 1;
                                $paymentInfo['setup_intent_id'] = $setupIntent->id;

                                // Track processed SetupIntents to prevent double counting
                                if (!is_array($processedSetupIntents)) {
                                    $processedSetupIntents = [];
                                }
                                $processedSetupIntents[] = $setupIntent->id;
                                $paymentInfo['processed_setup_intents'] = $processedSetupIntents;

                                $model->payment_info = json_encode($paymentInfo);
                                $model->save();
                            } else {
                                Log::info('SetupIntent already processed, skipping webhook fulfillment', [
                                    'setup_intent_id' => $setupIntent->id,
                                    'user_id' => $model->id,
                                    'user_type' => $guest ? 'guest' : 'cast'
                                ]);
                            }

                            Log::info('Payment info updated via setup_intent.succeeded webhook', [
                                'user_id' => $model->id,
                                'user_type' => $guest ? 'guest' : 'cast',
                                'customer_id' => $customerId,
                                'payment_method_id' => $paymentMethod->id,
                                'setup_intent_id' => $setupIntent->id
                            ]);
                        } else {
                            Log::warning('User not found for SetupIntent customer', [
                                'customer_id' => $customerId,
                                'setup_intent_id' => $setupIntent->id
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to handle setup_intent.succeeded webhook', [
                            'setup_intent_id' => $event->data->object->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                    break;

                default:
                    Log::info('Unhandled webhook event', ['type' => $event->type]);
            }

            return [
                'success' => true,
                'event' => $event->toArray(),
            ];

        } catch (Exception $e) {
            Log::error('Webhook handling failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle Stripe Connect specific webhook
     */
    public function handleConnectWebhook($payload, $signature): array
    {
        if (!$this->connectWebhookSecret) {
            throw new \Exception('Stripe Connect webhook secret is not configured');
        }

        $event = Webhook::constructEvent($payload, $signature, $this->connectWebhookSecret);

        Log::info('Stripe Connect webhook received', [
            'type' => $event->type,
        ]);

        return $event->toArray();
    }

    /**
     * Cancel a payment intent
     */
    public function cancelPaymentIntent(string $paymentIntentId): array
    {
        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret_key'));

            $paymentIntent = $stripe->paymentIntents->cancel($paymentIntentId);

            Log::info('Payment intent cancelled successfully', [
                'payment_intent_id' => $paymentIntentId,
                'status' => $paymentIntent->status
            ]);

            return [
                'success' => true,
                'payment_intent' => $paymentIntent->toArray()
            ];

        } catch (Exception $e) {
            Log::error('Failed to cancel payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get user-friendly error message for Stripe errors
     */
    private function getUserFriendlyErrorMessage($stripeError)
    {
        $errorMap = [
            'card_declined' => 'カードが拒否されました',
            'insufficient_funds' => '残高不足です',
            'expired_card' => 'カードの有効期限が切れています',
            'incorrect_cvc' => 'セキュリティコードが正しくありません',
            'processing_error' => '支払い処理中にエラーが発生しました',
            'authentication_required' => '3D Secure認証が必要です',
            'card_not_supported' => 'このカードはサポートされていません',
            'currency_not_supported' => 'この通貨はサポートされていません',
            'duplicate_transaction' => '重複した取引です',
            'generic_decline' => 'カードが拒否されました',
            'lost_card' => 'カードが紛失・盗難されています',
            'merchant_blacklist' => 'このカードは使用できません',
            'new_account_information_available' => 'カード情報を更新してください',
            'no_action_taken' => '取引が完了しませんでした',
            'not_permitted' => 'この取引は許可されていません',
            'pickup_card' => 'カードを回収してください',
            'pin_try_exceeded' => 'PIN試行回数が超過しました',
            'restricted_card' => 'カードの使用が制限されています',
            'revocation_of_all_authorizations' => 'すべての認証が取り消されました',
            'security_violation' => 'セキュリティ違反が検出されました',
            'service_not_allowed' => 'サービスが許可されていません',
            'stolen_card' => 'カードが盗難されています',
            'stop_payment_order' => '支払い停止命令が発行されています',
            'testmode_decline' => 'テストモードで拒否されました',
            'transaction_not_allowed' => 'この取引は許可されていません',
            'try_again_later' => 'しばらくしてから再試行してください',
            'withdrawal_count_limit_exceeded' => '引き出し回数制限を超過しました'
        ];

        return $errorMap[$stripeError] ?? '支払い処理中にエラーが発生しました';
    }

    /**
     * Validate and handle payment intent status
     */
    private function validatePaymentIntentStatus($paymentIntent)
    {
        $status = $paymentIntent->status;

        // Define status categories
        $successStatuses = ['succeeded'];
        $pendingStatuses = ['processing', 'requires_capture'];
        $problematicStatuses = ['requires_action', 'requires_payment_method', 'requires_confirmation'];
        $failureStatuses = ['canceled', 'payment_failed'];

        $statusInfo = [
            'status' => $status,
            'is_successful' => in_array($status, $successStatuses),
            'is_pending' => in_array($status, $pendingStatuses),
            'is_problematic' => in_array($status, $problematicStatuses),
            'is_failed' => in_array($status, $failureStatuses),
            'category' => $this->getStatusCategory($status)
        ];

        // Log status validation
        Log::info('Payment intent status validation', [
            'payment_intent_id' => $paymentIntent->id,
            'status_info' => $statusInfo,
            'next_action' => $paymentIntent->next_action ?? null,
            'client_secret' => $paymentIntent->client_secret ?? null
        ]);

        return $statusInfo;
    }

    /**
     * Get status category for payment intent
     */
    private function getStatusCategory($status)
    {
        switch ($status) {
            case 'succeeded':
                return 'success';
            case 'processing':
            case 'requires_capture':
                return 'pending';
            case 'requires_action':
                return '3ds_required';
            case 'requires_payment_method':
            case 'requires_confirmation':
                return 'needs_setup';
            case 'canceled':
            case 'payment_failed':
                return 'failed';
            default:
                return 'unknown';
        }
    }

    /**
     * Create a payment intent with capture delay
     */
    public function createPaymentIntentWithDelay($customerId, $amount, $description, $captureDelayDays = 1)
    {
        try {
            // For production: Use the captureDelayDays parameter (2 days = 172800 seconds)
            $captureDelay = $captureDelayDays * 24 * 60 * 60; // Convert days to seconds

            $paymentIntentData = [
                'amount' => $amount,
                'currency' => 'jpy',
                'customer' => $customerId,
                'description' => $description,
                'capture_method' => 'manual',
                'confirm' => false, // Don't confirm immediately - let it be authorized only
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'always' // Allow redirects for 3D Secure authentication
                ],
                'metadata' => [
                    'capture_delay_days' => $captureDelayDays,
                    'auto_capture_at' => now()->addDays($captureDelayDays)->toISOString()
                ]
            ];

            // Try to get customer's default payment method
            $defaultPaymentMethodId = null;
            try {
                $customer = Customer::retrieve($customerId);
                if ($customer && !empty($customer->invoice_settings) && !empty($customer->invoice_settings->default_payment_method)) {
                    $defaultPaymentMethodId = $customer->invoice_settings->default_payment_method;
                    Log::info('Using customer default payment method from invoice settings for delayed payment', [
                        'customer_id' => $customerId,
                        'payment_method_id' => $defaultPaymentMethodId
                    ]);
                } elseif ($customer && !empty($customer->default_source)) {
                    $defaultPaymentMethodId = $customer->default_source;
                    Log::info('Using customer default source for delayed payment', [
                        'customer_id' => $customerId,
                        'default_source' => $defaultPaymentMethodId
                    ]);
                }
            } catch (Exception $e) {
                Log::warning('Failed to retrieve customer default payment method', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }

            $paymentMethodId = null;
            try {
                $paymentMethods = PaymentMethod::all([
                    'customer' => $customerId,
                    'type' => 'card',
                ]);

                if ($defaultPaymentMethodId) {
                    try {
                        $paymentMethod = PaymentMethod::retrieve($defaultPaymentMethodId);
                        if ($paymentMethod && $paymentMethod->customer === $customerId) {
                            $paymentMethodId = $paymentMethod->id;
                        } elseif ($paymentMethod && !$paymentMethod->customer) {
                            $paymentMethod->attach(['customer' => $customerId]);
                            $paymentMethodId = $paymentMethod->id;
                        }
                    } catch (Exception $e) {
                        Log::warning('Failed to use default payment method, falling back to list', [
                            'customer_id' => $customerId,
                            'payment_method_id' => $defaultPaymentMethodId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                if (empty($paymentMethodId) && !empty($paymentMethods->data)) {
                    $paymentMethodId = $paymentMethods->data[0]->id;

                    // Ensure payment method is attached to customer
                    $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
                    if (!$paymentMethod->customer) {
                        $paymentMethod->attach(['customer' => $customerId]);
                    }

                    Log::info('Using first available customer payment method for delayed payment', [
                        'customer_id' => $customerId,
                        'payment_method_id' => $paymentMethodId
                    ]);
                } else {
                    // No payment methods found, use automatic payment methods only
                    Log::info('No saved payment methods found, using automatic payment methods only', [
                        'customer_id' => $customerId
                    ]);
                }
            } catch (Exception $e) {
                Log::warning('Failed to get customer payment methods for delayed payment', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }

            if (!empty($paymentMethodId)) {
                $paymentIntentData['payment_method'] = $paymentMethodId;
                // Note: setup_future_usage is not needed here because SetupIntent during registration
                // already configures the payment method for off-session usage
                Log::info('Selected payment method for delayed payment', [
                    'customer_id' => $customerId,
                    'payment_method_id' => $paymentMethodId
                ]);
            }

            $paymentIntent = PaymentIntent::create($paymentIntentData);

            // Store the payment intent ID before confirmation attempt
            $paymentIntentIdBeforeConfirm = $paymentIntent->id;
            $clientSecretBeforeConfirm = $paymentIntent->client_secret;

            // If we have a payment method, confirm the payment intent
            if (isset($paymentIntentData['payment_method'])) {
                try {
                    // For delayed capture, we need to confirm with off_session for automatic payments
                    $paymentIntent = $paymentIntent->confirm([
                        'payment_method' => $paymentIntentData['payment_method'],
                        'off_session' => true
                    ]);

                    Log::info('Payment intent confirmed for delayed capture', [
                        'payment_intent_id' => $paymentIntent->id,
                        'status' => $paymentIntent->status
                    ]);

                    // Check if payment requires action (3D Secure, etc.)
                    if ($paymentIntent->status === 'requires_action') {
                        Log::warning('Payment intent requires action for delayed capture', [
                            'payment_intent_id' => $paymentIntent->id,
                            'status' => $paymentIntent->status
                        ]);

                        return [
                            'success' => false,
                            'error' => 'Payment requires authentication. Please complete payment setup in your account.'
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to confirm payment intent', [
                        'payment_intent_id' => $paymentIntent->id ?? 'unknown',
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Try to get payment intent from exception (Stripe exceptions often contain it)
                    $errorPaymentIntent = null;
                    if (method_exists($e, 'getJsonBody')) {
                        $errorBody = $e->getJsonBody();
                        if (isset($errorBody['error']['payment_intent'])) {
                            $errorPaymentIntent = $errorBody['error']['payment_intent'];
                        }
                    }

                    // Also check if exception has payment_intent property directly
                    if (!$errorPaymentIntent && property_exists($e, 'payment_intent')) {
                        $errorPaymentIntent = $e->payment_intent;
                    }

                    // Check if error is related to requiring on-session action
                    $errorMessage = $e->getMessage();
                    $isOnSessionError = stripos($errorMessage, 'on-session action') !== false ||
                        stripos($errorMessage, 'on_session') !== false ||
                        stripos($errorMessage, 'requires an on-session') !== false ||
                        stripos($errorMessage, 'on-session') !== false ||
                        (stripos($errorMessage, 'requires_action') !== false && stripos($errorMessage, 'on-session') !== false);

                    Log::info('Checking for on-session error', [
                        'error_message' => $errorMessage,
                        'is_on_session_error' => $isOnSessionError,
                        'payment_intent_id_before_confirm' => $paymentIntentIdBeforeConfirm ?? null
                    ]);

                    if ($isOnSessionError) {
                        // Payment requires on-session authentication - return payment intent details for frontend handling
                        // Always retrieve the payment intent from Stripe to get the latest status
                        $paymentIntentId = $paymentIntentIdBeforeConfirm ?? $paymentIntent->id ?? null;
                        $paymentIntentToReturn = null;

                        // Try to get payment intent from exception first (Stripe exceptions often contain it)
                        if ($errorPaymentIntent) {
                            if (is_string($errorPaymentIntent)) {
                                $paymentIntentId = $errorPaymentIntent;
                            } elseif (is_object($errorPaymentIntent)) {
                                $paymentIntentToReturn = $errorPaymentIntent;
                                $paymentIntentId = $errorPaymentIntent->id ?? $paymentIntentId;
                            }
                        }

                        // Always retrieve the payment intent from Stripe to ensure we have the latest status
                        if ($paymentIntentId) {
                            try {
                                $paymentIntentToReturn = PaymentIntent::retrieve($paymentIntentId);
                                Log::info('Retrieved payment intent after on-session error', [
                                    'payment_intent_id' => $paymentIntentId,
                                    'status' => $paymentIntentToReturn->status ?? 'unknown'
                                ]);
                            } catch (\Exception $retrieveError) {
                                Log::error('Failed to retrieve payment intent after on-session error', [
                                    'payment_intent_id' => $paymentIntentId,
                                    'error' => $retrieveError->getMessage()
                                ]);
                                // Fallback: use stored values
                                $paymentIntentToReturn = (object)[
                                    'id' => $paymentIntentId,
                                    'client_secret' => $clientSecretBeforeConfirm,
                                    'status' => 'requires_action'
                                ];
                            }
                        } else {
                            Log::error('No payment intent ID available after on-session error');
                            return [
                                'success' => false,
                                'error' => 'Failed to create payment intent. Please try again.'
                            ];
                        }

                        $clientSecret = is_object($paymentIntentToReturn) ? $paymentIntentToReturn->client_secret : $clientSecretBeforeConfirm;
                        $status = is_object($paymentIntentToReturn) ? $paymentIntentToReturn->status : 'requires_action';
                        $nextAction = is_object($paymentIntentToReturn) && isset($paymentIntentToReturn->next_action)
                            ? $paymentIntentToReturn->next_action
                            : null;

                        Log::info('Payment intent requires on-session authentication - returning details', [
                            'payment_intent_id' => $paymentIntentId,
                            'client_secret' => $clientSecret ? substr($clientSecret, 0, 20) . '...' : null,
                            'status' => $status,
                            'has_next_action' => !empty($nextAction)
                        ]);

                        return [
                            'success' => false, // Changed to false so frontend knows it's an error that needs handling
                            'error' => 'This PaymentIntent requires an on-session action. Please get your customer back on session and re-confirm the PaymentIntent with a payment method when the customer is on session.',
                            'requires_on_session' => true,
                            'payment_intent_id' => $paymentIntentId,
                            'client_secret' => $clientSecret,
                            'status' => $status,
                            'requires_authentication' => true,
                            'payment_intent' => is_object($paymentIntentToReturn) ? [
                                'id' => $paymentIntentToReturn->id,
                                'client_secret' => $paymentIntentToReturn->client_secret,
                                'status' => $paymentIntentToReturn->status,
                                'next_action' => $nextAction
                            ] : null
                        ];
                    }

                    // Check if error is related to payment method
                    if (strpos($errorMessage, 'payment_method') !== false ||
                        strpos($errorMessage, 'authentication_required') !== false ||
                        strpos($errorMessage, 'requires_action') !== false) {
                        return [
                            'success' => false,
                            'error' => 'Payment method requires authentication. Please update your payment method.'
                        ];
                    }

                    return [
                        'success' => false,
                        'error' => 'Failed to confirm payment intent: ' . $errorMessage
                    ];
                }
            }

            Log::info('Payment intent with delay created', [
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amount,
                'capture_delay_days' => $captureDelayDays,
                'customer_id' => $customerId,
                'status' => $paymentIntent->status
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'status' => $paymentIntent->status
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create payment intent with delay', [
                'customer_id' => $customerId,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            // Check if this is an on-session error that might have been caught by outer catch
            $errorMessage = $e->getMessage();
            $isOnSessionError = stripos($errorMessage, 'on-session action') !== false ||
                stripos($errorMessage, 'on_session') !== false ||
                stripos($errorMessage, 'requires an on-session') !== false ||
                stripos($errorMessage, 'on-session') !== false;

            if ($isOnSessionError) {
                // Try to get payment intent from exception
                $paymentIntentId = null;
                $clientSecret = null;

                // Check if exception has payment_intent property
                if (method_exists($e, 'getJsonBody')) {
                    $errorBody = $e->getJsonBody();
                    if (isset($errorBody['error']['payment_intent'])) {
                        $paymentIntentId = is_string($errorBody['error']['payment_intent'])
                            ? $errorBody['error']['payment_intent']
                            : ($errorBody['error']['payment_intent']['id'] ?? null);
                    }
                }

                if (property_exists($e, 'payment_intent')) {
                    $paymentIntentId = is_string($e->payment_intent)
                        ? $e->payment_intent
                        : ($e->payment_intent->id ?? null);
                }

                // If we have payment intent ID, retrieve it
                if ($paymentIntentId) {
                    try {
                        $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
                        $clientSecret = $paymentIntent->client_secret;

                        Log::info('Retrieved payment intent from outer catch for on-session error', [
                            'payment_intent_id' => $paymentIntentId,
                            'status' => $paymentIntent->status
                        ]);

                        return [
                            'success' => false,
                            'error' => $errorMessage,
                            'requires_on_session' => true,
                            'payment_intent_id' => $paymentIntentId,
                            'client_secret' => $clientSecret,
                            'status' => $paymentIntent->status,
                            'requires_authentication' => true,
                            'payment_intent' => [
                                'id' => $paymentIntent->id,
                                'client_secret' => $paymentIntent->client_secret,
                                'status' => $paymentIntent->status,
                                'next_action' => $paymentIntent->next_action ?? null
                            ]
                        ];
                    } catch (\Exception $retrieveError) {
                        Log::error('Failed to retrieve payment intent in outer catch', [
                            'payment_intent_id' => $paymentIntentId,
                            'error' => $retrieveError->getMessage()
                        ]);
                    }
                }
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create (or update) a Stripe Connect Express account for a cast
     */
    public function createExpressAccount(array $data = []): array
    {
        try {
            $payload = $this->removeNullValues([
                'type' => $data['type'] ?? 'express',
                'country' => $data['country'] ?? config('services.stripe.connect_default_country', 'HK'),
                'email' => $data['email'] ?? null,
                'business_type' => $data['business_type'] ?? 'individual',
                'metadata' => $data['metadata'] ?? [],
                'settings' => $data['settings'] ?? [
                    'payouts' => [
                        'schedule' => [
                            'interval' => 'manual',
                        ],
                    ],
                ],
                'capabilities' => $data['capabilities'] ?? [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'business_profile' => $data['business_profile'] ?? [
                    'product_description' => $data['product_description'] ?? 'Pishatto cast services',
                    'support_email' => $data['support_email'] ?? null,
                    'support_phone' => $data['support_phone'] ?? null,
                ],
                'individual' => $data['individual'] ?? null,
                'company' => $data['company'] ?? null,
            ]);

            // Log the payload for debugging (remove sensitive data)
            $logPayload = $payload;
            if (isset($logPayload['business_profile']['support_email'])) {
                $logPayload['business_profile']['support_email'] = substr($logPayload['business_profile']['support_email'], 0, 3) . '***';
            }
            Log::info('Creating Stripe Connect Express account', [
                'payload_keys' => array_keys($logPayload),
                'business_profile' => $logPayload['business_profile'] ?? null,
            ]);

            $account = Account::create($payload);

            Log::info('Stripe Connect Express account created', [
                'account_id' => $account->id,
                'email' => $data['email'] ?? null,
            ]);

            return $account->toArray();
        } catch (Exception $e) {
            Log::error('Stripe Connect account creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Generate an onboarding link for a connected account
     */
    public function createOnboardingLink(string $accountId, string $refreshUrl, string $returnUrl, string $type = 'account_onboarding'): array
    {
        try {
            $accountLink = AccountLink::create([
                'account' => $accountId,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => $type,
            ]);

            Log::info('Stripe Connect onboarding link created', [
                'account_id' => $accountId,
                'link' => $accountLink->url,
            ]);

            return $accountLink->toArray();
        } catch (Exception $e) {
            Log::error('Stripe Connect onboarding link creation failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate a login link for an Express dashboard
     */
    public function createLoginLink(string $accountId): array
    {
        try {
            $loginLink = Account::createLoginLink($accountId);

            Log::info('Stripe Connect login link created', [
                'account_id' => $accountId,
                'link' => $loginLink->url,
            ]);

            return $loginLink->toArray();
        } catch (Exception $e) {
            Log::error('Stripe Connect login link creation failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Retrieve a connected account
     */
    public function retrieveAccount(string $accountId): array
    {
        try {
            $account = Account::retrieve($accountId);

            return $account->toArray();
        } catch (Exception $e) {
            Log::error('Stripe Connect account retrieval failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the balance for the platform account (not a connected account)
     */
    public function getPlatformBalance(): array
    {
        try {
            $balance = Balance::retrieve();

            Log::info('Stripe platform account balance retrieved', [
                'available' => $balance->available ?? [],
                'pending' => $balance->pending ?? [],
            ]);

            return [
                'available' => array_map(function($item) {
                    return [
                        'amount' => $item->amount,
                        'currency' => $item->currency,
                        'source_types' => $item->source_types ?? [],
                    ];
                }, $balance->available ?? []),
                'pending' => array_map(function($item) {
                    return [
                        'amount' => $item->amount,
                        'currency' => $item->currency,
                        'source_types' => $item->source_types ?? [],
                    ];
                }, $balance->pending ?? []),
            ];
        } catch (Exception $e) {
            Log::error('Stripe platform account balance retrieval failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the balance for a connected account
     */
    public function getAccountBalance(string $accountId): array
    {
        try {
            // Retrieve balance for connected account
            // Balance::retrieve accepts options as first parameter
            $balance = Balance::retrieve(['stripe_account' => $accountId]);

            Log::info('Stripe Connect account balance retrieved', [
                'account_id' => $accountId,
                'available' => $balance->available ?? [],
                'pending' => $balance->pending ?? [],
            ]);

            return [
                'available' => array_map(function($item) {
                    return [
                        'amount' => $item->amount,
                        'currency' => $item->currency,
                        'source_types' => $item->source_types ?? [],
                    ];
                }, $balance->available ?? []),
                'pending' => array_map(function($item) {
                    return [
                        'amount' => $item->amount,
                        'currency' => $item->currency,
                        'source_types' => $item->source_types ?? [],
                    ];
                }, $balance->pending ?? []),
                'connect_reserved' => array_map(function($item) {
                    return [
                        'amount' => $item->amount,
                        'currency' => $item->currency,
                    ];
                }, $balance->connect_reserved ?? []),
            ];
        } catch (Exception $e) {
            Log::error('Stripe Connect account balance retrieval failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Transfer money from platform account to a connected account
     */
    public function createTransfer(string $destinationAccountId, int $amount, string $currency = 'jpy', array $metadata = []): array
    {
        try {
            $transfer = Transfer::create([
                'amount' => $amount,
                'currency' => $currency,
                'destination' => $destinationAccountId,
                'metadata' => $metadata,
            ]);

            Log::info('Stripe Transfer to connected account created', [
                'destination_account_id' => $destinationAccountId,
                'amount' => $amount,
                'currency' => $currency,
                'transfer_id' => $transfer->id,
            ]);

            return $transfer->toArray();
        } catch (Exception $e) {
            Log::error('Stripe Transfer to connected account failed', [
                'destination_account_id' => $destinationAccountId,
                'amount' => $amount,
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a payout for a connected account
     */
    public function createPayout(string $accountId, int $amount, string $currency = 'jpy', array $metadata = []): array
    {
        try {
            $payout = Payout::create(
                [
                    'amount' => $amount,
                    'currency' => $currency,
                    'metadata' => $metadata,
                ],
                [
                    'stripe_account' => $accountId,
                ]
            );

            Log::info('Stripe Connect payout created', [
                'account_id' => $accountId,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $payout->toArray();
        } catch (Exception $e) {
            Log::error('Stripe Connect payout creation failed', [
                'account_id' => $accountId,
                'amount' => $amount,
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Summarize requirement blocks to show casts why payouts are disabled
     */
    public function summarizeAccountRequirements(array $account): array
    {
        $requirements = $account['requirements'] ?? [];

        return [
            'currently_due' => $requirements['currently_due'] ?? [],
            'eventually_due' => $requirements['eventually_due'] ?? [],
            'past_due' => $requirements['past_due'] ?? [],
            'pending_verification' => $requirements['pending_verification'] ?? [],
            'disabled_reason' => $requirements['disabled_reason'] ?? null,
        ];
    }

    /**
     * Normalize key account signals for UI consumption
     */
    public function formatAccountStatus(array $account): array
    {
        $requirements = $this->summarizeAccountRequirements($account);
        $payoutsEnabled = (bool) ($account['payouts_enabled'] ?? false);
        $chargesEnabled = (bool) ($account['charges_enabled'] ?? false);

        return [
            'id' => $account['id'] ?? null,
            'email' => $account['email'] ?? null,
            'payouts_enabled' => $payoutsEnabled,
            'charges_enabled' => $chargesEnabled,
            'details_submitted' => (bool) ($account['details_submitted'] ?? false),
            'requirements' => $requirements,
            'needs_attention' => !$payoutsEnabled || !empty($requirements['currently_due']) || !empty($requirements['past_due']),
            'last_requirement_refresh' => now()->toISOString(),
        ];
    }

    /**
     * Accessor for Connect metadata used elsewhere in the app
     */
    public function getConnectMetadata(): array
    {
        return [
            'client_id' => $this->clientId,
            'webhook_secret' => $this->connectWebhookSecret,
            'refresh_interval_minutes' => $this->connectRefreshIntervalMinutes,
        ];
    }

    /**
     * Determine if an account should be refreshed based on last sync timestamp
     */
    public function shouldRefreshAccountStatus(?\Carbon\CarbonInterface $lastSyncedAt): bool
    {
        if (empty($lastSyncedAt)) {
            return true;
        }

        return $lastSyncedAt->addMinutes($this->connectRefreshIntervalMinutes)->isPast();
    }

    /**
     * Filter out null values and invalid data from payloads to avoid Stripe validation errors
     */
    private function removeNullValues(array $payload, string $parentKey = ''): array
    {
        $cleaned = [];
        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }

            // Handle arrays recursively
            if (is_array($value)) {
                $cleanedArray = $this->removeNullValues($value, $key);
                // Only include non-empty arrays
                if (!empty($cleanedArray)) {
                    $cleaned[$key] = $cleanedArray;
                }
                continue;
            }

            // Remove empty strings for URL fields (works at any nesting level)
            if ($key === 'url' || $key === 'refresh_url' || $key === 'return_url') {
                if (empty($value) || !is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                    continue;
                }
                // Stripe doesn't accept localhost URLs for business_profile.url
                if ($key === 'url') {
                    $parsedUrl = parse_url($value);
                    if (isset($parsedUrl['host']) &&
                        ($parsedUrl['host'] === 'localhost' ||
                         $parsedUrl['host'] === '127.0.0.1' ||
                         preg_match('/^192\.168\./', $parsedUrl['host']) ||
                         preg_match('/^10\./', $parsedUrl['host']))) {
                        continue;
                    }
                }
            }

            // Remove empty strings for email fields
            if (($key === 'support_email' || $key === 'email') && (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL))) {
                continue;
            }

            // Remove empty strings for phone fields
            if ($key === 'support_phone' && empty($value)) {
                continue;
            }

            // Remove any empty strings
            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $cleaned[$key] = $value;
        }
        return $cleaned;
    }

    /**
     * Update payment intent with payment method
     */
    public function updatePaymentIntent(string $paymentIntentId, string $paymentMethodId)
    {
        try {
            $paymentIntent = \Stripe\PaymentIntent::update(
                $paymentIntentId,
                [
                    'payment_method' => $paymentMethodId,
                ]
            );

            return $paymentIntent;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe API error updating payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create SetupIntent for card registration
     * This is called BEFORE payment method creation to get client_secret for frontend
     */
    public function createSetupIntentForCardRegistration(string $customerId)
    {
        try {
            Log::info('Creating SetupIntent for card registration', [
                'customer_id' => $customerId
            ]);

            $setupIntent = SetupIntent::create([
                'customer' => $customerId,
                'usage' => 'off_session',
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never'
                ],
            ]);

            Log::info('SetupIntent created successfully for card registration', [
                'setup_intent_id' => $setupIntent->id,
                'status' => $setupIntent->status,
                'customer_id' => $customerId
            ]);

            return [
                'success' => true,
                'setup_intent' => $setupIntent->toArray(),
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Failed to create SetupIntent for card registration', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Setup payment method for off-session usage
     * Creates a SetupIntent to configure a payment method for future off-session payments
     * Used for fixing existing cards that weren't set up properly
     */
    public function setupPaymentMethodForOffSession(string $paymentMethodId, string $customerId)
    {
        try {
            Log::info('Setting up payment method for off-session usage', [
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId
            ]);

            $setupIntent = SetupIntent::create([
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'usage' => 'off_session',
                'confirm' => true,
                'return_url' => config('app.url') . '/payment/return',
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never'
                ],
            ]);

            Log::info('SetupIntent created successfully for off-session usage', [
                'setup_intent_id' => $setupIntent->id,
                'status' => $setupIntent->status,
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId
            ]);

            return [
                'success' => true,
                'setup_intent' => $setupIntent->toArray(),
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Failed to setup payment method for off-session', [
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

}
