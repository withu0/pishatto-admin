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
                'payjp_customer_id' => $model->payjp_customer_id,
                'has_registered_cards' => false,
                'cards' => [],
                'payment_info' => null
            ];

            // Parse payment_info if it exists
            if ($model->payment_info) {
                $paymentInfo = json_decode($model->payment_info, true);
                if (is_array($paymentInfo)) {
                    $customerInfo['payment_info'] = $paymentInfo;
                }
            }

            // If user has a customer ID, try to get card information from PAY.JP
            if ($model->payjp_customer_id) {
                try {
                    $payjpService = app(\App\Services\PayJPService::class);
                    $customer = $payjpService->getCustomer($model->payjp_customer_id);
                    $cards = $payjpService->getCustomerCards($model->payjp_customer_id);
                    
                    $customerInfo['cards'] = $cards['data'] ?? [];
                    $customerInfo['has_registered_cards'] = count($customerInfo['cards']) > 0;
                    
                    Log::info('Customer information retrieved successfully', [
                        'user_id' => $userId,
                        'user_type' => $userType,
                        'customer_id' => $model->payjp_customer_id,
                        'card_count' => count($customerInfo['cards'])
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve customer cards from PAY.JP', [
                        'user_id' => $userId,
                        'user_type' => $userType,
                        'customer_id' => $model->payjp_customer_id,
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
                'has_customer_id' => !empty($model->payjp_customer_id),
                'customer_id' => $model->payjp_customer_id,
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
            if ($model->payjp_customer_id) {
                try {
                    $payjpService = app(\App\Services\PayJPService::class);
                    $cardsResult = $payjpService->getCustomerCards($model->payjp_customer_id);
                    if ($cardsResult && isset($cardsResult['data'])) {
                        $stats['card_count'] = count($cardsResult['data']);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to get card count from PAY.JP', [
                        'user_id' => $userId,
                        'user_type' => $userType,
                        'customer_id' => $model->payjp_customer_id
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