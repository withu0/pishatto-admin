<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\PointTransaction;
use App\Models\Cast;
use App\Services\PayJPService;
use App\Services\CustomerService;
use App\Services\PointTransactionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
                    // Convert yen to points using config rate (1 point = 1.2 yen)
                    $yenPerPoint = (float) config('points.yen_per_point', 1.2);
                    $pointsToCredit = (int) floor(((float) $request->amount) / max(0.0001, $yenPerPoint));

                    $currentPoints = $model->points ?? 0;
                    $newPoints = $currentPoints + $pointsToCredit;
                    $model->points = $newPoints;
                    $model->save();
                    // Grade updates are handled via quarterly evaluation & admin approval

                    // Create point transaction record for the direct charge
                    $pointTransactionData = [
                        'type' => 'buy',
                        'amount' => $pointsToCredit,
                        'description' => "Direct charge point purchase - {$pointsToCredit} points",
                    ];

                    // Set the appropriate user ID based on user type
                    if ($request->user_type === 'guest') {
                        $pointTransactionData['guest_id'] = $request->user_id;
                        $pointTransactionData['cast_id'] = null;
                    } else {
                        $pointTransactionData['cast_id'] = $request->user_id;
                        $pointTransactionData['guest_id'] = null;
                    }

                    PointTransaction::create($pointTransactionData);

                    $response['points_added'] = $pointsToCredit;
                    $response['total_points'] = $newPoints;
                    $response['user'] = $model->fresh();

                    Log::info('Points added to user after direct charge', [
                        'user_id' => $request->user_id,
                        'user_type' => $request->user_type,
                        'amount_yen' => $request->amount,
                        'credited_points' => $pointsToCredit,
                        'previous_points' => $currentPoints,
                        'new_points' => $newPoints,
                        'point_transaction_created' => true
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

            $yenPerPoint = (float) config('points.yen_per_point', 1.2);
            $intendedPoints = (int) floor(((float) $request->amount) / max(0.0001, $yenPerPoint));

            $paymentData = [
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'amount' => $request->amount, // yen
                'payment_method' => $request->payment_method ?? 'card',
                'description' => $request->description ?? "{$intendedPoints}ポイント購入",
                'metadata' => [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'yen_per_point' => $yenPerPoint,
                    'intended_points' => $intendedPoints,
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

            $paymentData['amount'] = (int) $request->amount; // amount is in yen for PAY.JP

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

            // Convert yen to points using config rate (1 point = 1.2 yen)
            $yenPerPoint = (float) config('points.yen_per_point', 1.2);
            $pointsToCredit = (int) floor(((float) $request->amount) / max(0.0001, $yenPerPoint));

            // Add points to user after successful payment
            $currentPoints = $model->points ?? 0;
            $newPoints = $currentPoints + $pointsToCredit;
            $model->points = $newPoints;
            $model->save();

            // Update grade based on new balance
            try {
                $gradeService = app(\App\Services\GradeService::class);
                if ($request->user_type === 'guest') {
                    $gradeService->calculateAndUpdateGrade($model);
                } else {
                    $gradeService->calculateAndUpdateCastGrade($model);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to update grade after purchase', [
                    'user_type' => $request->user_type,
                    'user_id' => $request->user_id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Create point transaction record for the purchase
            $pointTransactionData = [
                'type' => 'buy',
                'amount' => $pointsToCredit,
                'description' => "ポイント購入 - {$pointsToCredit} points",
            ];

            // Set the appropriate user ID based on user type
            if ($request->user_type === 'guest') {
                $pointTransactionData['guest_id'] = $request->user_id;
                $pointTransactionData['cast_id'] = null;
            } else {
                $pointTransactionData['cast_id'] = $request->user_id;
                $pointTransactionData['guest_id'] = null;
            }

            PointTransaction::create($pointTransactionData);

            // Log successful payment and points update
            Log::info('Payment processed successfully and points added', [
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'amount_yen' => $request->amount,
                'credited_points' => $pointsToCredit,
                'payment_id' => $result['payment']->id ?? 'unknown',
                'charge_id' => $result['charge']['id'] ?? 'unknown',
                'customer_id' => $model->payjp_customer_id,
                'previous_points' => $currentPoints,
                'new_points' => $newPoints,
                'point_transaction_created' => true
            ]);

            return response()->json([
                'success' => true,
                'payment' => $result['payment'],
                'charge' => $result['charge'],
                'points_added' => $pointsToCredit,
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
        // Validate user type
        if (!in_array($userType, ['guest', 'cast'])) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid user type'
            ], 400);
        }

        // Validate user ID
        if (!is_numeric($userId) || $userId <= 0) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid user ID'
            ], 400);
        }

        try {
            $payments = Payment::where('user_type', $userType)
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'payments' => $payments
            ]);
        } catch (\Exception $e) {
            Log::error('Payment history retrieval failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => '支払い履歴の取得に失敗しました'
            ], 500);
        }
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
     * Create a new receipt
     */
    public function createReceipt(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:guest,cast',
            'user_id' => 'required|integer',
            'payment_id' => 'nullable|integer|exists:payments,id',
            'recipient_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'purpose' => 'required|string|max:255',
            'transaction_created_at' => 'nullable|date',
        ]);

        try {
            // Generate unique receipt number
            $receiptNumber = 'R' . date('Ymd') . str_pad(Receipt::whereDate('created_at', today())->count() + 1, 6, '0', STR_PAD_LEFT);
            
            $taxRate = 10.00; // 10% tax rate
            $taxAmount = $request->amount * ($taxRate / 100);
            $totalAmount = $request->amount + $taxAmount;

            $receipt = Receipt::create([
                'receipt_number' => $receiptNumber,
                'user_type' => $request->user_type,
                'user_id' => $request->user_id,
                'payment_id' => $request->payment_id,
                'recipient_name' => $request->recipient_name,
                'amount' => $request->amount,
                'tax_amount' => $taxAmount,
                'tax_rate' => $taxRate,
                'total_amount' => $totalAmount,
                'purpose' => $request->purpose,
                'transaction_created_at' => $request->transaction_created_at,
                'issued_at' => now(),
                // Required company fields (NOT NULL in schema)
                'company_name' => '株式会社キネカ',
                'company_address' => '〒106-0032 東京都港区六本木4丁目8-7六本木三河台ビル',
                'company_phone' => 'TEL: 03-5860-6178',
                'registration_number' => '登録番号:T3010401129426',
                // Default status
                'status' => 'issued',
                // Optional PDF URL (kept null unless generated elsewhere)
                'pdf_url' => null,
                'html_content' => $this->generateReceiptHtml($receiptNumber, $request->recipient_name, $request->amount, $taxAmount, $totalAmount, $request->purpose),
            ]);

            return response()->json([
                'success' => true,
                'receipt' => $receipt,
                'message' => '領収書が正常に作成されました'
            ]);

        } catch (\Exception $e) {
            Log::error('Receipt creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => '領収書の作成に失敗しました'
            ], 500);
        }
    }

    /**
     * Get a specific receipt
     */
    public function getReceipt($receiptId)
    {
        try {
            $receipt = Receipt::findOrFail($receiptId);
            
            return response()->json([
                'success' => true,
                'receipt' => $receipt
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => '領収書が見つかりません'
            ], 404);
        }
    }

    /**
     * Get a receipt by receipt number (public access)
     */
    public function getReceiptByNumber($receiptNumber)
    {
        try {
            $receipt = Receipt::where('receipt_number', $receiptNumber)->first();
            
            if (!$receipt) {
                return response()->json([
                    'success' => false,
                    'error' => '領収書が見つかりません'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'receipt' => $receipt
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => '領収書の取得に失敗しました'
            ], 500);
        }
    }

    /**
     * Generate receipt HTML content
     */
    private function generateReceiptHtml($receiptNumber, $recipientName, $amount, $taxAmount, $totalAmount, $purpose)
    {
        $issuedDate = now()->format('Y年m月d日');
        $formattedAmount = number_format($amount);
        $formattedTaxAmount = number_format($taxAmount);
        $formattedTotalAmount = number_format($totalAmount);

        return "
        <div style='font-family: Arial, sans-serif; max-width: 400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <div style='text-align: center; font-size: 24px; font-weight: bold; margin-bottom: 20px;'>領収書</div>
            
            <div style='text-align: right; font-size: 12px; margin-bottom: 20px;'>
                <div>No. {$receiptNumber}</div>
                <div>{$issuedDate}</div>
            </div>
            
            <div style='margin-bottom: 20px;'>
                <div style='font-size: 16px; margin-bottom: 10px;'>{$recipientName} 様</div>
                <div style='border-bottom: 1px solid #ccc; height: 30px;'></div>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <div style='border: 2px solid #000; padding: 20px; font-size: 28px; font-weight: bold;'>
                    ¥{$formattedTotalAmount}-
                </div>
            </div>
            
            <div style='text-align: center; margin-bottom: 30px;'>
                <div style='font-size: 14px;'>但し {$purpose} として</div>
            </div>
            
            <div style='text-align: center; margin-bottom: 30px;'>
                <div style='font-size: 14px;'>上記正に、領収致しました。</div>
            </div>
            
            <div style='display: flex; justify-content: space-between;'>
                <div style='flex: 1;'>
                    <div style='border: 1px dashed #ccc; padding: 10px; margin-bottom: 10px; font-size: 10px; text-align: center;'>
                        電子領収書につき印紙不要
                    </div>
                    <div style='font-size: 12px;'>
                        <div style='font-weight: bold; margin-bottom: 5px;'>内訳</div>
                        <div>税抜き金額 ¥{$formattedAmount}-</div>
                        <div>消費税額 ¥{$formattedTaxAmount}-</div>
                        <div>消費税率 10%</div>
                    </div>
                </div>
                
                <div style='flex: 1; text-align: right;'>
                    <div style='font-size: 12px;'>
                        <div style='font-weight: bold; margin-bottom: 5px;'>株式会社キネカ</div>
                        <div>〒106-0032</div>
                        <div>東京都港区六本木4丁目8-7</div>
                        <div>六本木三河台ビル</div>
                        <div>TEL: 03-5860-6178</div>
                        <div>登録番号:T3010401129426</div>
                    </div>
                </div>
            </div>
        </div>";
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

    /**
     * Create a new point transaction
     */
    public function createPointTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_type' => 'required|in:guest,cast',
            'user_id' => 'required|integer',
            'amount' => 'required|integer',
            'type' => 'required|in:buy,transfer,convert,gift,pending',
            'reservation_id' => 'nullable|integer|exists:reservations,id',
            'description' => 'nullable|string|max:255',
            'gift_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Prepare transaction data
            $transactionData = [
                'amount' => $request->amount,
                'type' => $request->type,
                'reservation_id' => $request->reservation_id,
                'description' => $request->description,
                'gift_type' => $request->gift_type,
            ];

            // Set the appropriate user ID field based on user type
            if ($request->user_type === 'guest') {
                $transactionData['guest_id'] = $request->user_id;
                $transactionData['cast_id'] = null;
            } else {
                $transactionData['cast_id'] = $request->user_id;
                $transactionData['guest_id'] = null;
            }

            // Use the PointTransactionService to create the transaction
            $pointService = app(PointTransactionService::class);
            $transaction = $pointService->createTransaction($transactionData);
            
            return response()->json([
                'success' => true,
                'transaction' => $transaction,
                'message' => 'Point transaction created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Point transaction creation failed: ' . $e->getMessage(), [
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'amount' => $request->amount,
                'type' => $request->type,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'ポイント取引の作成中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * Get cast payment data for admin management
     * This shows payments made TO casts (similar to sales but for cast payments)
     */
    public function getCastPayments(Request $request)
    {
        $query = Payment::with(['cast'])
            ->where('user_type', 'cast')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('search') && $request->search) {
            $query->whereHas('cast', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('nickname', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // Get paginated results
        $perPage = (int) $request->input('per_page', 10);
        $payments = $query->paginate($perPage);

        // Transform data for frontend
        $transformedPayments = $payments->getCollection()->map(function($payment) {
            return [
                'id' => $payment->id,
                'cast_id' => $payment->user_id,
                'cast_name' => $payment->cast ? $payment->cast->name : 'Unknown Cast',
                'amount' => $payment->amount,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'description' => $payment->description,
                'paid_at' => $payment->paid_at?->toISOString(),
                'created_at' => $payment->created_at->toISOString(),
                'updated_at' => $payment->updated_at->toISOString(),
                'payjp_charge_id' => $payment->payjp_charge_id,
                'metadata' => $payment->metadata,
            ];
        });

        // Calculate summary statistics
        $summary = [
            'total_amount' => Payment::where('user_type', 'cast')->sum('amount'),
            'paid_count' => Payment::where('user_type', 'cast')->where('status', 'paid')->count(),
            'pending_count' => Payment::where('user_type', 'cast')->where('status', 'pending')->count(),
            'failed_count' => Payment::where('user_type', 'cast')->where('status', 'failed')->count(),
            'refunded_count' => Payment::where('user_type', 'cast')->where('status', 'refunded')->count(),
            'unique_casts' => Payment::where('user_type', 'cast')->distinct('user_id')->count(),
        ];

        return response()->json([
            'payments' => $transformedPayments,
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Create a new cast payment
     */
    public function createCastPayment(Request $request)
    {
        $request->validate([
            'cast_id' => 'required|integer|exists:casts,id',
            'amount' => 'required|integer|min:1',
            'payment_method' => 'required|in:card,convenience_store,bank_transfer,linepay,other',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|in:pending,paid,failed,refunded',
        ]);

        $payment = Payment::create([
            'user_id' => $request->cast_id,
            'user_type' => 'cast',
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'description' => $request->description,
            'status' => $request->status ?? 'pending',
            'paid_at' => $request->status === 'paid' ? now() : null,
        ]);

        // Update cast points if payment is successful
        if ($payment->status === 'paid') {
            $cast = Cast::find($request->cast_id);
            if ($cast) {
                $cast->points = ($cast->points ?? 0) + $request->amount;
                $cast->save();
                // Grade updates are handled via quarterly evaluation & admin approval
            }
        }

        return response()->json([
            'success' => true,
            'payment' => $payment->load('cast'),
            'message' => 'Cast payment created successfully'
        ]);
    }

    /**
     * Update cast payment status
     */
    public function updateCastPayment(Request $request, $paymentId)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,failed,refunded',
            'description' => 'nullable|string|max:500',
        ]);

        $payment = Payment::where('user_type', 'cast')->findOrFail($paymentId);
        
        $oldStatus = $payment->status;
        $payment->status = $request->status;
        $payment->description = $request->description ?? $payment->description;

        // Update timestamps based on status
        if ($request->status === 'paid' && $oldStatus !== 'paid') {
            $payment->paid_at = now();
        } elseif ($request->status === 'failed' && $oldStatus !== 'failed') {
            $payment->failed_at = now();
        } elseif ($request->status === 'refunded' && $oldStatus !== 'refunded') {
            $payment->refunded_at = now();
        }

        $payment->save();

        // Update cast points if status changed to paid
        if ($request->status === 'paid' && $oldStatus !== 'paid') {
            $cast = Cast::find($payment->user_id);
            if ($cast) {
                $cast->points = ($cast->points ?? 0) + $payment->amount;
                $cast->save();
                // Grade updates are handled via quarterly evaluation & admin approval
            }
        }

        return response()->json([
            'success' => true,
            'payment' => $payment->load('cast'),
            'message' => 'Cast payment updated successfully'
        ]);
    }

    /**
     * Delete cast payment
     */
    public function deleteCastPayment($paymentId)
    {
        $payment = Payment::where('user_type', 'cast')->findOrFail($paymentId);
        
        // Update cast points if payment was successful
        if ($payment->status === 'paid') {
            $cast = Cast::find($payment->user_id);
            if ($cast) {
                $cast->points = max(0, ($cast->points ?? 0) - $payment->amount);
                $cast->save();
                // Grade updates are handled via quarterly evaluation & admin approval
            }
        }

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cast payment deleted successfully'
        ]);
    }
}
