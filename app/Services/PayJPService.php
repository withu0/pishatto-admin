<?php

namespace App\Services;

use Payjp\Payjp;
use Payjp\Charge;
use Payjp\Customer;
use Payjp\Token;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PayJPService
{
    public function __construct()
    {
        Payjp::setApiKey(config('services.payjp.secret_key'));
    }

    /**
     * Create a charge using PAY.JP
     */
    public function createCharge(array $data)
    {
        try {
            $chargeData = [
                'amount' => $data['amount'],
                'currency' => 'jpy',
                'card' => $data['token'],
                'description' => $data['description'] ?? 'ポイント購入',
                'metadata' => $data['metadata'] ?? [],
            ];

            // Add customer if provided
            if (isset($data['customer_id'])) {
                $chargeData['customer'] = $data['customer_id'];
            }

            $charge = Charge::create($chargeData);

            return [
                'success' => true,
                'charge' => $charge,
                'charge_id' => $charge->id,
            ];
        } catch (\Exception $e) {
            Log::error('PAY.JP charge creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a customer in PAY.JP
     */
    public function createCustomer(array $data)
    {
        try {
            $customerData = [
                'email' => $data['email'] ?? null,
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ];

            $customer = Customer::create($customerData);

            return [
                'success' => true,
                'customer' => $customer,
                'customer_id' => $customer->id,
            ];
        } catch (\Exception $e) {
            Log::error('PAY.JP customer creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a token for card information
     */
    public function createToken(array $cardData)
    {
        try {
            $token = Token::create([
                'card' => [
                    'number' => $cardData['number'],
                    'cvc' => $cardData['cvc'],
                    'exp_month' => $cardData['exp_month'],
                    'exp_year' => $cardData['exp_year'],
                ],
            ]);

            return [
                'success' => true,
                'token' => $token,
                'token_id' => $token->id,
            ];
        } catch (\Exception $e) {
            Log::error('PAY.JP token creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve a charge from PAY.JP
     */
    public function getCharge($chargeId)
    {
        try {
            $charge = Charge::retrieve($chargeId);
            return [
                'success' => true,
                'charge' => $charge,
            ];
        } catch (\Exception $e) {
            Log::error('PAY.JP charge retrieval failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refund a charge
     */
    public function refundCharge($chargeId, $amount = null)
    {
        try {
            $charge = Charge::retrieve($chargeId);
            $refundData = [];
            
            if ($amount) {
                $refundData['amount'] = $amount;
            }

            $refund = $charge->refunds->create($refundData);

            return [
                'success' => true,
                'refund' => $refund,
            ];
        } catch (\Exception $e) {
            Log::error('PAY.JP charge refund failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process payment and create database record
     */
    public function processPayment(array $paymentData)
    {
        try {
            // Create charge
            $chargeResult = $this->createCharge($paymentData);
            
            if (!$chargeResult['success']) {
                return $chargeResult;
            }

            $charge = $chargeResult['charge'];

            // Create payment record
            $payment = Payment::create([
                'user_id' => $paymentData['user_id'],
                'user_type' => $paymentData['user_type'],
                'amount' => $paymentData['amount'],
                'status' => $charge->paid ? 'paid' : 'pending',
                'payment_method' => $paymentData['payment_method'] ?? 'card',
                'payjp_charge_id' => $charge->id,
                'payjp_customer_id' => $charge->customer ?? null,
                'payjp_token' => $paymentData['token'],
                'description' => $paymentData['description'] ?? 'ポイント購入',
                'metadata' => $paymentData['metadata'] ?? [],
                'paid_at' => $charge->paid ? now() : null,
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'charge' => $charge,
            ];
        } catch (\Exception $e) {
            Log::error('Payment processing failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle webhook from PAY.JP
     */
    public function handleWebhook($payload, $signature)
    {
        try {
            // Verify webhook signature (implement based on PAY.JP docs)
            // $this->verifyWebhookSignature($payload, $signature);

            $event = json_decode($payload, true);
            
            switch ($event['type']) {
                case 'charge.succeeded':
                    return $this->handleChargeSucceeded($event['data']['object']);
                case 'charge.failed':
                    return $this->handleChargeFailed($event['data']['object']);
                case 'charge.refunded':
                    return $this->handleChargeRefunded($event['data']['object']);
                default:
                    Log::info('Unhandled PAY.JP webhook event: ' . $event['type']);
                    return ['success' => true];
            }
        } catch (\Exception $e) {
            Log::error('Webhook handling failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle successful charge
     */
    private function handleChargeSucceeded($charge)
    {
        $payment = Payment::where('payjp_charge_id', $charge['id'])->first();
        
        if ($payment) {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        return ['success' => true];
    }

    /**
     * Handle failed charge
     */
    private function handleChargeFailed($charge)
    {
        $payment = Payment::where('payjp_charge_id', $charge['id'])->first();
        
        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);
        }

        return ['success' => true];
    }

    /**
     * Handle refunded charge
     */
    private function handleChargeRefunded($charge)
    {
        $payment = Payment::where('payjp_charge_id', $charge['id'])->first();
        
        if ($payment) {
            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);
        }

        return ['success' => true];
    }
} 