<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Refund;
use Stripe\Webhook;

class StripeService
{
    protected $secretKey;
    protected $publicKey;
    protected $webhookSecret;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret_key', env('STRIPE_SECRET_KEY'));
        $this->publicKey = config('services.stripe.public_key', env('STRIPE_PUBLIC_KEY'));
        $this->webhookSecret = config('services.stripe.webhook_secret', env('STRIPE_WEBHOOK_SECRET'));

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
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->attach(['customer' => $customerId]);

            return $paymentMethod->toArray();

        } catch (Exception $e) {
            Log::error('Stripe payment method attachment failed', [
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId,
                'error' => $e->getMessage()
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

                            // Set payment method and configuration
                            $paymentIntentData['payment_method'] = $paymentMethodId;
                            $paymentIntentData['confirm'] = true;
                            $paymentIntentData['capture_method'] = $captureMethod;
                            $paymentIntentData['off_session'] = true;
                            $paymentIntentData['return_url'] = config('app.url') . '/payment/return';
                            $paymentIntentData['automatic_payment_methods'] = [
                                'enabled' => true,
                                'allow_redirects' => 'never'
                            ];

                            Log::info('Using customer default payment method', [
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
                $paymentIntentData['confirm'] = true;
                $paymentIntentData['capture_method'] = $captureMethod;
                $paymentIntentData['off_session'] = true; // Indicates this is an off-session payment
                $paymentIntentData['return_url'] = config('app.url') . '/payment/return'; // Add return URL for redirect-based payments
                $paymentIntentData['automatic_payment_methods'] = [
                    'enabled' => true,
                    'allow_redirects' => 'never' // Disable redirect-based payment methods
                ];
            }

            // Log the payment intent data being sent to Stripe
            Log::info('Creating payment intent with data', [
                'payment_intent_data' => $paymentIntentData
            ]);

            $paymentIntent = PaymentIntent::create($paymentIntentData);

            Log::info('Stripe payment intent created', [
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'confirmation_method' => $paymentIntent->confirmation_method,
                'capture_method' => $paymentIntent->capture_method
            ]);

            // Handle different payment intent statuses
            if ($paymentIntent->status === 'requires_action') {
                // 3D Secure authentication required - cannot be handled automatically
                Log::warning('Payment requires 3D Secure authentication', [
                    'payment_intent_id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'next_action' => $paymentIntent->next_action
                ]);

                // Create payment record with failed status for 3DS
                $payment = \App\Models\Payment::create([
                    'user_id' => $paymentData['user_id'],
                    'user_type' => $paymentData['user_type'],
                    'amount' => $paymentData['amount'],
                    'payment_method' => $paymentData['payment_method_type'] ?? 'card',
                    'status' => 'failed',
                    'description' => $paymentData['description'] ?? 'Payment',
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'stripe_customer_id' => $paymentData['customer_id'] ?? null,
                    'metadata' => array_merge($paymentData['metadata'] ?? [], [
                        'requires_3ds' => true,
                        'failure_reason' => '3D Secure authentication required'
                    ]),
                    'paid_at' => null,
                ]);

                return [
                    'success' => false,
                    'error' => 'Card requires 3D Secure authentication',
                    'requires_action' => true,
                    'payment_intent' => $paymentIntent->toArray(),
                    'payment' => $payment
                ];

            } elseif ($paymentIntent->status === 'requires_confirmation' && isset($paymentIntentData['payment_method'])) {
                try {
                    $paymentIntent->confirm(['payment_method' => $paymentIntentData['payment_method']]);
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
            } elseif ($paymentIntent->status === 'requires_payment_method' && isset($paymentIntentData['payment_method'])) {
                // If payment intent still requires payment method, try to update it
                try {
                    $paymentIntent->payment_method = $paymentIntentData['payment_method'];
                    $paymentIntent->save();
                    $paymentIntent->confirm(['payment_method' => $paymentIntentData['payment_method']]);
                    Log::info('Payment intent updated and confirmed', [
                        'payment_intent_id' => $paymentIntent->id,
                        'status' => $paymentIntent->status
                    ]);
                } catch (Exception $e) {
                    Log::warning('Payment intent update failed', [
                        'payment_intent_id' => $paymentIntent->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Validate payment intent status
            $statusInfo = $this->validatePaymentIntentStatus($paymentIntent);
            $isAuthorizedManual = ($captureMethod === 'manual') && in_array($paymentIntent->status, ['requires_capture', 'succeeded']);
            $isPaymentSuccessful = ($captureMethod === 'automatic') ? $statusInfo['is_successful'] : $isAuthorizedManual;

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

            return [
                'success' => $isPaymentSuccessful,
                'payment' => $payment,
                'payment_intent' => $paymentIntent->toArray(),
            ];

        } catch (Exception $e) {
            Log::error('Payment processing failed', [
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
                        $payment->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        // Add points to user if not already added
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

                            Log::info('Points added via webhook', [
                                'user_id' => $payment->user_id,
                                'user_type' => $payment->user_type,
                                'points_added' => $pointsToCredit,
                                'new_balance' => $newPoints
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
                    'allow_redirects' => 'never'
                ],
                'metadata' => [
                    'capture_delay_days' => $captureDelayDays,
                    'auto_capture_at' => now()->addDays($captureDelayDays)->toISOString()
                ]
            ];

            // Try to get customer's default payment method
            try {
                $paymentMethods = PaymentMethod::all([
                    'customer' => $customerId,
                    'type' => 'card',
                ]);

                if (!empty($paymentMethods->data)) {
                    $paymentMethodId = $paymentMethods->data[0]->id;

                    // Ensure payment method is attached to customer
                    $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
                    if (!$paymentMethod->customer) {
                        $paymentMethod->attach(['customer' => $customerId]);
                    }

                    // Set payment method for automatic payment
                    $paymentIntentData['payment_method'] = $paymentMethodId;
                    // Don't set off_session when confirm is false

                    Log::info('Using customer default payment method for delayed payment', [
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

            $paymentIntent = PaymentIntent::create($paymentIntentData);

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
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Check if error is related to payment method
                    $errorMessage = $e->getMessage();
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
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

}
