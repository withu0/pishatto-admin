<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PayJPService
{
    protected $secretKey;
    protected $publicKey;
    protected $baseUrl = 'https://api.pay.jp/v1';

    public function __construct()
    {
        $this->secretKey = config('services.payjp.secret_key', env('PAYJP_SECRET_KEY'));
        $this->publicKey = config('services.payjp.public_key', env('PAYJP_PUBLIC_KEY'));
        
        if (!$this->secretKey) {
            throw new \Exception('PayJP secret key is not configured');
        }
        
        if (!$this->publicKey) {
            // For server-side operations, public key is not always required
            // Only throw if we're doing operations that need it
        }
        
        // Set the API key for the PayJP SDK if it's being used
        if (class_exists('\Payjp\Payjp')) {
            \Payjp\Payjp::setApiKey($this->secretKey);
        }
    }

 
    /**
     * Create a customer
     */
    public function createCustomer($data)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->post($this->baseUrl . '/customers', $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('PayJP customer creation failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            throw new Exception('Failed to create customer: ' . $response->body());
        } catch (Exception $e) {
            Log::error('PayJP customer creation error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create a charge/payment using PayJP SDK
     */
    public function createCharge($data)
    {
        try {
            // Use PayJP SDK directly
            if (class_exists('\Payjp\Charge')) {
                $charge = \Payjp\Charge::create($data);
                return (array) $charge;
            } else {
                // Fallback to HTTP API if SDK is not available
                $response = Http::withBasicAuth($this->secretKey, '')
                    ->post($this->baseUrl . '/charges', $data);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('PayJP charge creation failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                throw new Exception('Failed to create charge: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('PayJP charge creation error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get customer information
     */
    public function getCustomer($customerId)
    {
        try {
            // Use PayJP SDK for getting customer
            if (class_exists('\Payjp\Customer')) {
                $customer = \Payjp\Customer::retrieve($customerId);
                return (array) $customer;
            } else {
                // Fallback to HTTP API
                $response = Http::withBasicAuth($this->secretKey, '')
                    ->get($this->baseUrl . '/customers/' . $customerId);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('PayJP customer retrieval failed', [
                    'customer_id' => $customerId,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                throw new Exception('Failed to get customer: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('PayJP customer retrieval error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get charge information
     */
    public function getCharge($chargeId)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->get($this->baseUrl . '/charges/' . $chargeId);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('PayJP charge retrieval failed', [
                'charge_id' => $chargeId,
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            throw new Exception('Failed to get charge: ' . $response->body());
        } catch (Exception $e) {
            Log::error('PayJP charge retrieval error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Refund a charge
     */
    public function refundCharge($chargeId, $amount = null)
    {
        try {
            $data = [];
            if ($amount !== null) {
                $data['amount'] = $amount;
            }

            $response = Http::withBasicAuth($this->secretKey, '')
                ->post($this->baseUrl . '/charges/' . $chargeId . '/refunds', $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('PayJP charge refund failed', [
                'charge_id' => $chargeId,
                'amount' => $amount,
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            throw new Exception('Failed to refund charge: ' . $response->body());
        } catch (Exception $e) {
            Log::error('PayJP charge refund error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete a customer
     */
    public function deleteCustomer($customerId)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->delete($this->baseUrl . '/customers/' . $customerId);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('PayJP customer deletion failed', [
                'customer_id' => $customerId,
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            throw new Exception('Failed to delete customer: ' . $response->body());
        } catch (Exception $e) {
            Log::error('PayJP customer deletion error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get customer's cards
     */
    public function getCustomerCards($customerId)
    {
        try {
            // Use PayJP SDK for getting customer cards
            if (class_exists('\Payjp\Customer')) {
                $customer = \Payjp\Customer::retrieve($customerId);
                $cards = $customer->cards->all();
                return (array) $cards;
            } else {
                // Fallback to HTTP API
                $response = Http::withBasicAuth($this->secretKey, '')
                    ->get($this->baseUrl . '/customers/' . $customerId . '/cards');

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('PayJP customer cards retrieval failed', [
                    'customer_id' => $customerId,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                throw new Exception('Failed to get customer cards: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('PayJP customer cards retrieval error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Add a card to a customer
     */
    public function addCardToCustomer($customerId, $token)
    {
        try {
            // Use PayJP SDK for adding card to customer
            if (class_exists('\Payjp\Customer')) {
                $customer = \Payjp\Customer::retrieve($customerId);
                $card = $customer->cards->create(['card' => $token]);
                return (array) $card;
            } else {
                // Fallback to HTTP API
                $response = Http::withBasicAuth($this->secretKey, '')
                    ->post($this->baseUrl . '/customers/' . $customerId . '/cards', [
                        'card' => $token
                    ]);
                
                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('PayJP add card to customer failed', [
                    'customer_id' => $customerId,
                    'token' => $token,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                throw new Exception('Failed to add card to customer: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('PayJP add card to customer error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete a card from a customer
     */
    public function deleteCardFromCustomer($customerId, $cardId)
    {
        try {
            // Use PayJP SDK for deleting card from customer
            if (class_exists('\Payjp\Customer')) {
                $customer = \Payjp\Customer::retrieve($customerId);
                $card = $customer->cards->retrieve($cardId);
                $card->delete();
                return ['deleted' => true];
            } else {
                // Fallback to HTTP API
                $response = Http::withBasicAuth($this->secretKey, '')
                    ->delete($this->baseUrl . '/customers/' . $customerId . '/cards/' . $cardId);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('PayJP delete card from customer failed', [
                    'customer_id' => $customerId,
                    'card_id' => $cardId,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                throw new Exception('Failed to delete card from customer: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('PayJP delete card from customer error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create a charge using the direct PayJP SDK approach
     */
    public function createChargeDirect($card, $amount, $currency = 'jpy', $tenant = null)
    {
        try {
            $chargeData = [
                'card' => $card,
                'amount' => $amount,
                'currency' => $currency,
            ];

            // Add tenant if provided (required for PAY.JP Platform)
            if ($tenant) {
                $chargeData['tenant'] = $tenant;
            }

            // Use PayJP SDK directly
            if (class_exists('\Payjp\Charge')) {
                $charge = \Payjp\Charge::create($chargeData);
                
                // Convert PayJP object to array properly
                $chargeArray = [];
                if (is_object($charge)) {
                    // Get all public properties
                    $chargeArray = get_object_vars($charge);
                    
                    // If the object has a toArray method, use it
                    if (method_exists($charge, 'toArray')) {
                        $chargeArray = $charge->toArray();
                    }
                    
                    // Ensure we have the essential fields
                    if (!isset($chargeArray['id']) && isset($charge->id)) {
                        $chargeArray['id'] = $charge->id;
                    }
                    if (!isset($chargeArray['amount']) && isset($charge->amount)) {
                        $chargeArray['amount'] = $charge->amount;
                    }
                    if (!isset($chargeArray['currency']) && isset($charge->currency)) {
                        $chargeArray['currency'] = $charge->currency;
                    }
                    if (!isset($chargeArray['paid']) && isset($charge->paid)) {
                        $chargeArray['paid'] = $charge->paid;
                    }
                } else {
                    $chargeArray = (array) $charge;
                }
                
                // Log the charge response for debugging
                Log::info('PayJP charge created successfully', [
                    'charge_id' => $chargeArray['id'] ?? 'unknown',
                    'amount' => $chargeArray['amount'] ?? 'unknown',
                    'currency' => $chargeArray['currency'] ?? 'unknown',
                    'paid' => $chargeArray['paid'] ?? 'unknown'
                ]);
                
                return $chargeArray;
            } else {
                throw new Exception('PayJP SDK is not available');
            }
        } catch (Exception $e) {
            Log::error('PayJP direct charge creation error', [
                'error' => $e->getMessage(),
                'charge_data' => $chargeData ?? []
            ]);
            throw $e;
        }
    }

    /**
     * Process a payment (create charge) using PayJP SDK
     */
    public function processPayment($paymentData)
    {
        try {
            // Create charge data using the direct PayJP SDK approach
            $chargeData = [
                'amount' => $paymentData['amount'],
                'currency' => 'jpy',
                'description' => $paymentData['description'] ?? 'Payment',
                'metadata' => $paymentData['metadata'] ?? [],
            ];

            // Add payment method (customer_id or token)
            if (isset($paymentData['customer_id'])) {
                $chargeData['customer'] = $paymentData['customer_id'];
            } elseif (isset($paymentData['token'])) {
                $chargeData['card'] = $paymentData['token'];
            } else {
                throw new Exception('Either customer_id or token is required');
            }

            // Create the charge using PayJP SDK directly
            if (class_exists('\Payjp\Charge')) {
                $charge = \Payjp\Charge::create($chargeData);
                
                // Convert PayJP object to array properly
                $chargeArray = [];
                if (is_object($charge)) {
                    // Get all public properties
                    $chargeArray = get_object_vars($charge);
                    
                    // If the object has a toArray method, use it
                    if (method_exists($charge, 'toArray')) {
                        $chargeArray = $charge->toArray();
                    }
                    
                    // Ensure we have the essential fields
                    if (!isset($chargeArray['id']) && isset($charge->id)) {
                        $chargeArray['id'] = $charge->id;
                    }
                    if (!isset($chargeArray['amount']) && isset($charge->amount)) {
                        $chargeArray['amount'] = $charge->amount;
                    }
                    if (!isset($chargeArray['currency']) && isset($charge->currency)) {
                        $chargeArray['currency'] = $charge->currency;
                    }
                    if (!isset($chargeArray['paid']) && isset($charge->paid)) {
                        $chargeArray['paid'] = $charge->paid;
                    }
                } else {
                    $chargeArray = (array) $charge;
                }
            } else {
                // Fallback to HTTP API if SDK is not available
                $chargeArray = $this->createCharge($chargeData);
            }

            // Validate that we have a charge ID
            if (!isset($chargeArray['id'])) {
                throw new Exception('Charge creation failed: No charge ID returned');
            }

            // Create payment record in database
            $payment = \App\Models\Payment::create([
                'user_id' => $paymentData['user_id'],
                'user_type' => $paymentData['user_type'],
                'amount' => $paymentData['amount'],
                'payment_method' => $paymentData['payment_method'] ?? 'card',
                'status' => 'paid',
                'description' => $paymentData['description'] ?? 'Payment',
                'payjp_charge_id' => $chargeArray['id'],
                'payjp_customer_id' => $paymentData['customer_id'] ?? null,
                'metadata' => $paymentData['metadata'] ?? [],
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'charge' => $chargeArray,
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
            // Verify webhook signature
            $expectedSignature = hash_hmac('sha256', $payload, $this->secretKey);
            
            if (!hash_equals($expectedSignature, $signature)) {
                throw new Exception('Invalid webhook signature');
            }

            $event = json_decode($payload, true);
            
            if (!$event) {
                throw new Exception('Invalid webhook payload');
            }

            // Handle different event types
            switch ($event['type']) {
                case 'charge.succeeded':
                    // Payment succeeded
                    Log::info('Payment succeeded', ['charge_id' => $event['data']['id']]);
                    break;
                    
                case 'charge.failed':
                    // Payment failed
                    Log::warning('Payment failed', ['charge_id' => $event['data']['id']]);
                    break;
                    
                default:
                    Log::info('Unhandled webhook event', ['type' => $event['type']]);
            }

            return [
                'success' => true,
                'event' => $event,
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
