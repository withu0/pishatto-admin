<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Cast;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    /**
     * Get customer information for a user
     */
    public function getCustomerInfo($userType, $userId)
    {
        try {
            $model = $this->getUserModel($userType, $userId);
            
            if (!$model) {
                return [
                    'success' => false,
                    'error' => 'User not found'
                ];
            }

            $customerInfo = [
                'user_id' => $userId,
                'user_type' => $userType,
                'stripe_customer_id' => $model->stripe_customer_id,
                'payjp_customer_id' => $model->payjp_customer_id, // Keep for backward compatibility
                'has_registered_cards' => false,
                'cards' => [],
                'payment_info' => null,
                'card_count' => 0
            ];

            // Parse payment_info if it exists
            if ($model->payment_info) {
                $paymentInfo = json_decode($model->payment_info, true);
                if (is_array($paymentInfo)) {
                    $customerInfo['payment_info'] = $paymentInfo;
                }
            }

            // If user has a Stripe customer ID, try to get card information from Stripe
            if ($model->stripe_customer_id) {
                try {
                    $stripeService = app(\App\Services\StripeService::class);
                    $paymentMethods = $stripeService->getCustomerPaymentMethods($model->stripe_customer_id);
                    
                    $customerInfo['cards'] = $paymentMethods['data'] ?? [];
                    $customerInfo['has_registered_cards'] = count($customerInfo['cards']) > 0;
                    $customerInfo['card_count'] = count($customerInfo['cards']);
                    
                    Log::info('Customer information retrieved successfully from Stripe', [
                        'user_id' => $userId,
                        'user_type' => $userType,
                        'stripe_customer_id' => $model->stripe_customer_id,
                        'card_count' => $customerInfo['card_count']
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve customer cards from Stripe', [
                        'user_id' => $userId,
                        'user_type' => $userType,
                        'stripe_customer_id' => $model->stripe_customer_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => true,
                'customer_info' => $customerInfo
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get customer information', [
                'user_id' => $userId,
                'user_type' => $userType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve customer information'
            ];
        }
    }

    /**
     * Update customer metadata in database
     */
    public function updateCustomerMetadata($userType, $userId, $metadata)
    {
        try {
            $model = $this->getUserModel($userType, $userId);
            
            if (!$model) {
                return [
                    'success' => false,
                    'error' => 'User not found'
                ];
            }

            // Get existing payment info
            $paymentInfo = json_decode($model->payment_info, true) ?: [];
            
            // Merge new metadata
            $paymentInfo = array_merge($paymentInfo, $metadata);
            $paymentInfo['updated_at'] = now()->toISOString();
            
            // Save to database
            $model->payment_info = json_encode($paymentInfo);
            
            if (!$model->save()) {
                Log::error('Failed to update customer metadata in database', [
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'metadata' => $metadata
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Failed to save customer metadata'
                ];
            }

            Log::info('Customer metadata updated successfully', [
                'user_id' => $userId,
                'user_type' => $userType,
                'metadata' => $metadata
            ]);

            return [
                'success' => true,
                'message' => 'Customer metadata updated successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update customer metadata', [
                'user_id' => $userId,
                'user_type' => $userType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to update customer metadata'
            ];
        }
    }

    /**
     * Get user model by type and ID
     */
    private function getUserModel($userType, $userId)
    {
        return $userType === 'guest' 
            ? Guest::find($userId)
            : Cast::find($userId);
    }

    /**
     * Check if user has registered cards
     */
    public function hasRegisteredCards($userType, $userId)
    {
        $result = $this->getCustomerInfo($userType, $userId);
        
        if (!$result['success']) {
            return false;
        }

        return $result['customer_info']['has_registered_cards'];
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats($userType, $userId)
    {
        try {
            $model = $this->getUserModel($userType, $userId);
            
            if (!$model) {
                return [
                    'success' => false,
                    'error' => 'User not found'
                ];
            }

            $stats = [
                'user_id' => $userId,
                'user_type' => $userType,
                'has_customer_id' => !empty($model->stripe_customer_id),
                'stripe_customer_id' => $model->stripe_customer_id,
                'payjp_customer_id' => $model->payjp_customer_id, // Keep for backward compatibility
                'card_count' => 0,
                'last_payment_date' => null,
                'total_payments' => 0,
                'total_amount' => 0
            ];

            // Get payment statistics
            $payments = \App\Models\Payment::where('user_id', $userId)
                ->where('user_type', $userType)
                ->where('status', 'paid')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($payments->count() > 0) {
                $stats['total_payments'] = $payments->count();
                $stats['total_amount'] = $payments->sum('amount');
                $stats['last_payment_date'] = $payments->first()->created_at;
            }

            // Get card count if customer exists
            if ($model->stripe_customer_id) {
                try {
                    $stripeService = app(\App\Services\StripeService::class);
                    $paymentMethods = $stripeService->getCustomerPaymentMethods($model->stripe_customer_id);
                    if ($paymentMethods && isset($paymentMethods['data'])) {
                        $stats['card_count'] = count($paymentMethods['data']);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to get card count from Stripe', [
                        'user_id' => $userId,
                        'user_type' => $userType,
                        'stripe_customer_id' => $model->stripe_customer_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => true,
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get customer statistics', [
                'user_id' => $userId,
                'user_type' => $userType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve customer statistics'
            ];
        }
    }
} 