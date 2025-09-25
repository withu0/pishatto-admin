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
                $paymentIntentData['confirmation_method'] = 'manual';
                $paymentIntentData['confirm'] = true;
            }

            $paymentIntent = PaymentIntent::create($paymentIntentData);

            Log::info('Stripe payment intent created', [
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount,
                'status' => $paymentIntent->status
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
     * Process a payment using Stripe
     */
    public function processPayment($paymentData)
    {
        try {
            // Create payment intent
            $paymentIntentData = [
                'amount' => $paymentData['amount'], // Amount in cents
                'currency' => 'jpy',
                'description' => $paymentData['description'] ?? 'Payment',
                'metadata' => $paymentData['metadata'] ?? [],
            ];

            // Add customer if provided
            if (isset($paymentData['customer_id'])) {
                $paymentIntentData['customer'] = $paymentData['customer_id'];
            }

            // Add payment method if provided
            if (isset($paymentData['payment_method'])) {
                $paymentIntentData['payment_method'] = $paymentData['payment_method'];
                $paymentIntentData['confirmation_method'] = 'manual';
                $paymentIntentData['confirm'] = true;
            }

            $paymentIntent = PaymentIntent::create($paymentIntentData);

            // If payment method is provided, confirm the payment intent
            if (isset($paymentData['payment_method'])) {
                $paymentIntent->confirm(['payment_method' => $paymentData['payment_method']]);
            }

            // Create payment record in database
            $payment = \App\Models\Payment::create([
                'user_id' => $paymentData['user_id'],
                'user_type' => $paymentData['user_type'],
                'amount' => $paymentData['amount'],
                'payment_method' => $paymentData['payment_method_type'] ?? 'card',
                'status' => $paymentIntent->status === 'succeeded' ? 'paid' : 'pending',
                'description' => $paymentData['description'] ?? 'Payment',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'stripe_customer_id' => $paymentData['customer_id'] ?? null,
                'metadata' => $paymentData['metadata'] ?? [],
            ]);

            return [
                'success' => true,
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
                    break;

                case 'payment_intent.payment_failed':
                    Log::warning('Payment failed', [
                        'payment_intent_id' => $event->data->object->id
                    ]);
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
}
