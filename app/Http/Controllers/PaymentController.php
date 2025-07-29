<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\PayJPService;
use App\Services\CustomerService;
use App\Services\PointTransactionService;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $payJPService;
    protected $customerService;

    public function __construct(PayJPService $payJPService, CustomerService $customerService)
    {
        $this->payJPService = $payJPService;
        $this->customerService = $customerService;
    }

    /**
     * Debug PayJP SDK response structure
     */
    public function debugPayJPResponse(Request $request)
    {
        $request->validate([
            'card' => 'required|string',
            'amount' => 'required|integer|min:100',
        ]);

        try {
            $chargeData = [
                'card' => $request->card,
                'amount' => $request->amount,
                'currency' => 'jpy',
            ];

            if (class_exists('\Payjp\Charge')) {
                $charge = \Payjp\Charge::create($chargeData);
                
                // Debug the response structure
                $debug = [
                    'is_object' => is_object($charge),
                    'class' => get_class($charge),
                    'methods' => get_class_methods($charge),
                    'properties' => get_object_vars($charge),
                    'to_array_cast' => (array) $charge,
                ];
                
                // Try different ways to access the data
                if (is_object($charge)) {
                    $debug['direct_access'] = [
                        'id' => $charge->id ?? 'not_set',
                        'amount' => $charge->amount ?? 'not_set',
                        'currency' => $charge->currency ?? 'not_set',
                        'paid' => $charge->paid ?? 'not_set',
                    ];
                }

                return response()->json([
                    'success' => true,
                    'debug' => $debug,
                    'charge' => $charge,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'PayJP SDK is not available',
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Create a charge using direct PayJP SDK approach
     */
    public function createChargeDirect(Request $request)
    {
        $request->validate([
            'card' => 'required|string',
            'amount' => 'required|integer|min:100',
            'currency' => 'nullable|string|in:jpy',
            'tenant' => 'nullable|string', // Required for PAY.JP Platform
            'user_id' => 'nullable|integer', // Optional: for adding points to user
            'user_type' => 'nullable|string|in:guest,cast', // Optional: for adding points to user
        ]);

        try {
            $result = $this->payJPService->createChargeDirect(
                $request->card,
                $request->amount,
                $request->currency ?? 'jpy',
                $request->tenant
            );

            // Validate the result
            if (!is_array($result) || !isset($result['id'])) {
                Log::error('Invalid charge result structure', [
                    'result' => $result,
                    'card' => $request->card,
                    'amount' => $request->amount
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid charge result structure',
                    'debug' => $result
                ], 500);
            }

            $response = [
                'success' => true,
                'charge' => $result,
            ];

            // Add points to user if user_id and user_type are provided
            if ($request->user_id && $request->user_type) {
                $model = $request->user_type === 'guest' 
                    ? \App\Models\Guest::find($request->user_id)
                    : \App\Models\Cast::find($request->user_id);

                if ($model) {
                    $currentPoints = $model->points ?? 0;
                    $newPoints = $currentPoints + $request->amount;
                    $model->points = $newPoints;
                    $model->save();

                    $response['points_added'] = $request->amount;
                    $response['total_points'] = $newPoints;
                    $response['user'] = $model->fresh();

                    Log::info('Points added to user after direct charge', [
                        'user_id' => $request->user_id,
                        'user_type' => $request->user_type,
                        'amount' => $request->amount,
                        'previous_points' => $currentPoints,
                        'new_points' => $newPoints
                    ]);
                }
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Direct charge creation failed: ' . $e->getMessage(), [
                'card' => $request->card,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'tenant' => $request->tenant,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => '決済処理中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Initiate a point purchase with PAY.JP
     */
    public function purchase(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:guest,cast',
            'amount' => 'required|integer|min:100',
            'token' => 'nullable|string', // Make token optional since we can use customer
            'payment_method' => 'nullable|string|in:card,convenience_store,bank_transfer,linepay,other',
        ]);

        try {
            // Get user model
            $model = $request->user_type === 'guest' 
                ? \App\Models\Guest::find($request->user_id)
                : \App\Models\Cast::find($request->user_id);

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'error' => 'ユーザーが見つかりません',
                ], 404);
            }

            $paymentData = [
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method ?? 'card',
                'description' => $request->description ?? "{$request->amount}ポイント購入",
                'metadata' => [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'points' => $request->amount,
                    'customer_id' => $model->payjp_customer_id,
                    'payment_type' => $request->token ? 'token' : 'customer',
                ],
            ];

            // If user has a customer ID, use it for payment
            if ($model->payjp_customer_id) {
                $paymentData['customer_id'] = $model->payjp_customer_id;
                
                Log::info('Processing payment with registered customer', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'customer_id' => $model->payjp_customer_id,
                    'amount' => $request->amount
                ]);
            } else if ($request->token) {
                // Fallback to token if no customer ID
                $paymentData['token'] = $request->token;
                
                Log::info('Processing payment with token (no registered customer)', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'amount' => $request->amount
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'カード情報が必要です。カードを登録してください。',
                ], 404);
            }

            $result = $this->payJPService->processPayment($paymentData);

            if (!$result['success']) {
                Log::error('Payment processing failed', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'amount' => $request->amount,
                    'error' => $result['error']
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }

            // Add points to user after successful payment
            $currentPoints = $model->points ?? 0;
            $newPoints = $currentPoints + $request->amount;
            $model->points = $newPoints;
            $model->save();

            // Log successful payment and points update
            Log::info('Payment processed successfully and points added', [
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'amount' => $request->amount,
                'payment_id' => $result['payment']->id ?? 'unknown',
                'charge_id' => $result['charge']['id'] ?? 'unknown',
                'customer_id' => $model->payjp_customer_id,
                'previous_points' => $currentPoints,
                'new_points' => $newPoints
            ]);

            return response()->json([
                'success' => true,
                'payment' => $result['payment'],
                'charge' => $result['charge'],
                'points_added' => $request->amount,
                'total_points' => $newPoints,
                'user' => $model->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Payment purchase failed: ' . $e->getMessage(), [
                'user_id' => $request->user_id ?? 'unknown',
                'user_type' => $request->user_type ?? 'unknown',
                'amount' => $request->amount ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => '決済処理中にエラーが発生しました',
            ], 500);
        }
    }


    /**
     * List payment/point history
     */
    public function history($userType, $userId)
    {
        $payments = Payment::where('user_type', $userType)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['payments' => $payments]);
    }

    /**
     * List receipts
     */
    public function receipts($userType, $userId)
    {
        $receipts = Receipt::where('user_type', $userType)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['receipts' => $receipts]);
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus($paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        
        return response()->json([
            'payment' => $payment,
            'status' => $payment->status,
        ]);
    }

    /**
     * Refund a payment
     */
    public function refund(Request $request, $paymentId)
    {
        $request->validate([
            'amount' => 'nullable|integer|min:1',
        ]);

        $payment = Payment::findOrFail($paymentId);

        if ($payment->status !== 'paid') {
            return response()->json([
                'success' => false,
                'error' => '返金可能な決済ではありません',
            ], 400);
        }

        try {
            $result = $this->payJPService->refundCharge(
                $payment->payjp_charge_id,
                $request->amount
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }

            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'payment' => $payment,
                'refund' => $result['refund'],
            ]);

        } catch (\Exception $e) {
            Log::error('Payment refund failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => '返金処理中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * Handle PAY.JP webhook
     */
    public function webhook(Request $request)
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('Payjp-Signature');

            $result = $this->payJPService->handleWebhook($payload, $signature);

            if ($result['success']) {
                return response()->json(['status' => 'success'], 200);
            } else {
                Log::error('Webhook processing failed: ' . $result['error']);
                return response()->json(['status' => 'error'], 400);
            }

        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Cast payout request
     */
    public function requestPayout(Request $request)
    {
        $request->validate([
            'cast_id' => 'required|integer',
            'amount' => 'required|integer|min:100',
        ]);

        try {
            $payment = Payment::create([
                'user_id' => $request->cast_id,
                'user_type' => 'cast',
                'amount' => $request->amount,
                'payment_method' => 'payout',
                'status' => 'pending',
                'description' => 'キャスト出金リクエスト',
                'metadata' => [
                    'type' => 'payout',
                    'cast_id' => $request->cast_id,
                ],
            ]);

            return response()->json([
                'success' => true,
                'payout' => $payment,
            ]);

        } catch (\Exception $e) {
            Log::error('Payout request failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => '出金リクエストの処理中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * Register a card for a user
     */
    public function registerCard(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:guest,cast',
            'token' => 'required|string',
        ]);


        try {
            // Get or create customer
            $model = $request->user_type === 'guest' 
                ? \App\Models\Guest::find($request->user_id)
                : \App\Models\Cast::find($request->user_id);

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'error' => 'ユーザーが見つかりません',
                ], 404);
            }

            // If user doesn't have a PayJP customer ID, create one
            if (!$model->payjp_customer_id) {
                try {
                    $customerResult = $this->payJPService->createCustomer([
                        'description' => "{$request->user_type}_{$request->user_id}",
                        'metadata' => [
                            'user_id' => $request->user_id,
                            'user_type' => $request->user_type,
                            'nickname' => $model->nickname ?? '',
                            'phone' => $model->phone ?? '',
                        ],
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create customer', [
                        'user_id' => $request->user_id,
                        'user_type' => $request->user_type,
                        'error' => $e->getMessage()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'error' => '顧客の作成に失敗しました: ' . $e->getMessage(),
                    ], 400);
                }

                // Save customer ID to user model
                $model->payjp_customer_id = $customerResult['id'];
                
                // Store additional customer metadata
                $customerMetadata = [
                    'customer_id' => $customerResult['id'],
                    'created_at' => now()->toISOString(),
                    'user_type' => $request->user_type,
                    'user_id' => $request->user_id,
                ];
                
                $model->payment_info = json_encode($customerMetadata);
                
                if (!$model->save()) {
                    Log::error('Failed to save customer ID to database', [
                        'user_id' => $request->user_id,
                        'user_type' => $request->user_type,
                        'customer_id' => $customerResult['id']
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'error' => 'データベースへの保存に失敗しました',
                    ], 500);
                }
                
                Log::info('Customer created and saved to database', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'customer_id' => $customerResult['id']
                ]);
            }

            // Add card to customer
            try {
                $cardResult = $this->payJPService->addCardToCustomer(
                    $model->payjp_customer_id,
                    $request->token
                );
            } catch (\Exception $e) {
                Log::error('Failed to add card to customer', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'customer_id' => $model->payjp_customer_id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'カードの追加に失敗しました: ' . $e->getMessage(),
                ], 400);
            }

            // Update payment_info with card information
            $paymentInfo = json_decode($model->payment_info, true) ?: [];
            $paymentInfo['last_card_token'] = $request->token;
            $paymentInfo['last_card_added'] = now()->toISOString();
            $paymentInfo['card_count'] = ($paymentInfo['card_count'] ?? 0) + 1;
            
            $model->payment_info = json_encode($paymentInfo);
            
            if (!$model->save()) {
                Log::error('Failed to update payment info in database', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'customer_id' => $model->payjp_customer_id
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'カード情報の保存に失敗しました',
                ], 500);
            }

            Log::info('Card successfully attached to customer', [
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'customer_id' => $model->payjp_customer_id,
                'card_id' => $cardResult['id'] ?? 'unknown'
            ]);

            return response()->json([
                'success' => true,
                'customer_id' => $model->payjp_customer_id,
                'card' => $cardResult,
                'message' => 'カードが正常に登録されました',
            ]);

        } catch (\Exception $e) {
            Log::error('Card registration failed: ' . $e->getMessage(), [
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'カード登録中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * Store payment info (card token) for a user
     */
    public function storePaymentInfo(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:guest,cast',
            'payment_info' => 'required|string',
        ]);

        try {
            $model = $request->user_type === 'guest' 
                ? \App\Models\Guest::find($request->user_id)
                : \App\Models\Cast::find($request->user_id);

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'error' => 'ユーザーが見つかりません',
                ], 404);
            }

            // Store the payment info (token) in the user model
            $model->payment_info = $request->payment_info;
            $model->save();

            return response()->json([
                'success' => true,
                'message' => '支払い情報を保存しました',
            ]);

        } catch (\Exception $e) {
            Log::error('Payment info storage failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => '支払い情報の保存中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * Get payment info for a user
     */
    public function getPaymentInfo($userType, $userId)
    {
        try {
            $result = $this->customerService->getCustomerInfo($userType, $userId);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 404);
            }

            $customerInfo = $result['customer_info'];

            return response()->json([
                'success' => true,
                'payment_info' => $customerInfo['payment_info'],
                'payjp_customer_id' => $customerInfo['payjp_customer_id'],
                'has_registered_cards' => $customerInfo['has_registered_cards'],
                'cards' => $customerInfo['cards'],
            ]);

        } catch (\Exception $e) {
            Log::error('Payment info retrieval failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'user_type' => $userType,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => '支払い情報の取得中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * Delete payment info for a user
     */
    public function deletePaymentInfo($userType, $userId, $cardId)
    {
        try {
            $model = $userType === 'guest' 
                ? \App\Models\Guest::find($userId)
                : \App\Models\Cast::find($userId);

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'error' => 'ユーザーが見つかりません',
                ], 404);
            }

            // If user has a customer ID, try to delete the card from PAY.JP
            if ($model->payjp_customer_id) {
                try {
                    $result = $this->payJPService->deleteCardFromCustomer($model->payjp_customer_id, $cardId);
                    
                    // Check if this was the last card by getting remaining cards
                    $cardsResult = $this->payJPService->getCustomerCards($model->payjp_customer_id);
                    if ($cardsResult && isset($cardsResult['data']) && count($cardsResult['data']) === 0) {
                        // No more cards, clear the customer ID and payment info
                        $model->payjp_customer_id = null;
                        $model->payment_info = null;
                        $model->save();
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to delete card from PAY.JP: ' . $e->getMessage());
                    // Continue with local cleanup even if PAY.JP deletion fails
                }
            }

            // Clear the payment info locally
            $model->payment_info = null;
            $model->save();

            return response()->json([
                'success' => true,
                'message' => 'カードを削除しました',
            ]);

        } catch (\Exception $e) {
            Log::error('Payment info deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'カードの削除中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats($userType, $userId)
    {
        try {
            $result = $this->customerService->getCustomerStats($userType, $userId);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'stats' => $result['stats'],
            ]);

        } catch (\Exception $e) {
            Log::error('Customer stats retrieval failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'user_type' => $userType,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => '顧客統計の取得中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * Get point transaction history for a user
     */
    public function getPointTransactions($userType, $userId)
    {
        try {
            $pointService = app(PointTransactionService::class);
            $transactions = $pointService->getTransactionHistory($userId, $userType);
            
            return response()->json([
                'success' => true,
                'transactions' => $transactions,
            ]);

        } catch (\Exception $e) {
            Log::error('Point transaction history retrieval failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'user_type' => $userType,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'ポイント取引履歴の取得中にエラーが発生しました',
            ], 500);
        }
    }
}
