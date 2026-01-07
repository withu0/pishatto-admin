<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\PointTransaction;
use App\Models\Cast;
use App\Services\StripeService;
use App\Services\CustomerService;
use App\Services\PointTransactionService;
use App\Services\AutomaticPaymentService;
use App\Services\AutomaticPaymentAuditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    protected $stripeService;
    protected $customerService;

    public function __construct(StripeService $stripeService, CustomerService $customerService)
    {
        $this->stripeService = $stripeService;
        $this->customerService = $customerService;
    }

    /**
     * Create a payment method
     */
    public function createPaymentMethod(Request $request)
    {
        $request->validate([
            'number' => 'required|string',
            'exp_month' => 'required|integer|min:1|max:12',
            'exp_year' => 'required|integer|min:' . date('Y'),
            'cvc' => 'required|string|min:3|max:4',
        ]);

        try {
            $paymentMethod = $this->stripeService->createPaymentMethod([
                'number' => $request->number,
                'exp_month' => $request->exp_month,
                'exp_year' => $request->exp_year,
                'cvc' => $request->cvc,
            ]);

            return response()->json([
                'success' => true,
                'payment_method_id' => $paymentMethod['id'],
            ]);

        } catch (\Exception $e) {
            Log::error('Payment method creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'カード情報の処理中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete payment intent after 3DS authentication
     */
    public function completePaymentIntent(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        try {
            $result = $this->stripeService->completePaymentIntent($request->payment_intent_id);

            $isPaymentSuccessful = $result['status'] === 'succeeded';

            return response()->json([
                'success' => true,
                'payment_intent' => $result,
                'payment_successful' => $isPaymentSuccessful,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment intent completion failed: ' . $e->getMessage(), [
                'payment_intent_id' => $request->payment_intent_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => '決済完了処理中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Debug Stripe response structure
     */
    public function debugStripeResponse(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string',
            'amount' => 'required|integer|min:100',
        ]);

        try {
            $paymentIntentData = [
                'amount' => $request->amount,
                'currency' => 'jpy',
                'payment_method' => $request->payment_method,
                'confirmation_method' => 'manual',
                'confirm' => true,
            ];

            $paymentIntent = $this->stripeService->createPaymentIntent($paymentIntentData);

            return response()->json([
                'success' => true,
                'payment_intent' => $paymentIntent,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Create a payment intent using direct Stripe approach
     */
    public function createPaymentIntentDirect(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string',
            'amount' => 'required|integer|min:100',
            'currency' => 'nullable|string|in:jpy',
            'user_id' => 'nullable|integer', // Optional: for adding points to user
            'user_type' => 'nullable|string|in:guest,cast', // Optional: for adding points to user
        ]);

        try {
            $paymentIntentData = [
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'jpy',
                'payment_method' => $request->payment_method,
                'confirm' => true,
                'capture_method' => 'automatic',
                'off_session' => true, // Indicates this is an off-session payment
                'return_url' => config('app.url') . '/payment/return', // Add return URL for redirect-based payments
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never' // Disable redirect-based payment methods
                ],
            ];

            $result = $this->stripeService->createPaymentIntent($paymentIntentData);

            // Validate the result
            if (!is_array($result) || !isset($result['id'])) {
                Log::error('Invalid payment intent result structure', [
                    'result' => $result,
                    'payment_method' => $request->payment_method,
                    'amount' => $request->amount
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Invalid payment intent result structure',
                    'debug' => $result
                ], 500);
            }

            // Check if payment was successful or requires action
            $isPaymentSuccessful = $result['status'] === 'succeeded';
            $requiresAction = $result['status'] === 'requires_action';

            $response = [
                'success' => true,
                'payment_intent' => $result,
                'payment_successful' => $isPaymentSuccessful,
                'requires_action' => $requiresAction,
            ];

            // If payment requires 3DS authentication, return the client_secret
            if ($requiresAction && isset($result['client_secret'])) {
                $response['client_secret'] = $result['client_secret'];
                $response['next_action'] = $result['next_action'] ?? null;
            }

            // Add points to user if user_id and user_type are provided AND payment was successful
            if ($request->user_id && $request->user_type && $isPaymentSuccessful) {
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
                        'description' => "Direct payment intent point purchase - {$pointsToCredit} points",
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

                    // Create Payment record to track the payment status
                    Payment::create([
                        'user_id' => $request->user_id,
                        'user_type' => $request->user_type,
                        'amount' => $request->amount,
                        'status' => 'paid', // Payment is successful
                        'payment_method' => 'card',
                        'stripe_payment_intent_id' => $result['id'],
                        'description' => "Point purchase - {$pointsToCredit} points",
                        'paid_at' => now(),
                    ]);

                    $response['points_added'] = $pointsToCredit;
                    $response['total_points'] = $newPoints;
                    $response['user'] = $model->fresh();

                    Log::info('Points added to user after direct payment intent', [
                        'user_id' => $request->user_id,
                        'user_type' => $request->user_type,
                        'amount_yen' => $request->amount,
                        'credited_points' => $pointsToCredit,
                        'previous_points' => $currentPoints,
                        'new_points' => $newPoints,
                        'point_transaction_created' => true,
                        'payment_record_created' => true,
                        'payment_status' => 'paid'
                    ]);
                }
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Direct payment intent creation failed: ' . $e->getMessage(), [
                'payment_method' => $request->payment_method,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => '決済処理中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Initiate a point purchase with Stripe
     */
    public function purchase(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:guest,cast',
            'amount' => 'required|integer|min:100',
            'payment_method' => 'nullable|string', // Stripe payment method ID
            'payment_method_type' => 'nullable|string|in:card,convenience_store,bank_transfer,linepay,other',
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

            // Apply consumption tax (1.1 multiplier)
            $amountWithTax = (int) ceil($request->amount * 1.1);

            $paymentData = [
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'amount' => $amountWithTax, // amount with consumption tax for Stripe
                'payment_method' => $request->payment_method,
                'payment_method_type' => $request->payment_method_type ?? 'card',
                'description' => $request->description ?? "{$intendedPoints}ポイント購入",
                'metadata' => [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'yen_per_point' => $yenPerPoint,
                    'intended_points' => $intendedPoints,
                    'customer_id' => $model->stripe_customer_id,
                    'payment_type' => $request->payment_method ? 'payment_method' : 'customer',
                    'original_amount' => $request->amount,
                    'tax_amount' => $amountWithTax - $request->amount,
                    'consumption_tax_applied' => true
                ],
            ];

            // If user has a customer ID, use it for payment
            if ($model->stripe_customer_id) {
                $paymentData['customer_id'] = $model->stripe_customer_id;

                Log::info('Processing payment with registered customer', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'customer_id' => $model->stripe_customer_id,
                    'original_amount' => $request->amount,
                    'amount_with_tax' => $amountWithTax,
                    'tax_amount' => $amountWithTax - $request->amount
                ]);
            } else if ($request->payment_method) {
                // Use payment method if no customer ID
                $paymentData['payment_method'] = $request->payment_method;

                Log::info('Processing payment with payment method (no registered customer)', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'original_amount' => $request->amount,
                    'amount_with_tax' => $amountWithTax,
                    'tax_amount' => $amountWithTax - $request->amount
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'カード情報が必要です。カードを登録してください。',
                ], 404);
            }

            // Create Payment record with pending status before calling Stripe
            // This ensures webhook can find and update the payment record
            $paymentRecord = \App\Models\Payment::create([
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'amount' => $amountWithTax,
                'payment_method' => $paymentData['payment_method_type'] ?? 'card',
                'status' => 'pending',
                'description' => $paymentData['description'] ?? "{$intendedPoints}ポイント購入",
                'stripe_customer_id' => $model->stripe_customer_id ?? null,
                'metadata' => $paymentData['metadata'] ?? [],
                'paid_at' => null,
            ]);

            Log::info('Payment record created with pending status', [
                'payment_id' => $paymentRecord->id,
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'amount' => $amountWithTax
            ]);

            // Pass payment record ID to processPayment so it can update the existing record
            $paymentData['payment_id'] = $paymentRecord->id;
            $result = $this->stripeService->processPayment($paymentData);

            if (!$result['success']) {
                Log::error('Payment processing failed', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'original_amount' => $request->amount,
                    'amount_with_tax' => $amountWithTax,
                    'error' => $result['error'],
                    'requires_on_session' => $result['requires_on_session'] ?? false
                ]);

                // Check if this is an on-session error that requires returning payment intent details
                if (isset($result['requires_on_session']) && $result['requires_on_session']) {
                    return response()->json([
                        'success' => false,
                        'error' => $result['error'],
                        'requires_on_session' => true,
                        'client_secret' => $result['client_secret'] ?? null,
                        'payment_intent_id' => $result['payment_intent_id'] ?? null,
                        'payment_intent' => $result['payment_intent'] ?? null,
                        'requires_authentication' => true,
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }

            // Convert yen to points using config rate (1 point = 1.2 yen)
            $yenPerPoint = (float) config('points.yen_per_point', 1.2);
            $pointsToCredit = (int) floor(((float) $request->amount) / max(0.0001, $yenPerPoint));

            // Add points to user after successful payment (idempotent - check metadata first)
            $paymentMetadata = is_string($result['payment']->metadata)
                ? json_decode($result['payment']->metadata, true)
                : ($result['payment']->metadata ?? []);
            $pointsCredited = $paymentMetadata['points_credited'] ?? false;

            if (!$pointsCredited) {
                $currentPoints = $model->points ?? 0;
                $newPoints = $currentPoints + $pointsToCredit;
                $model->points = $newPoints;
                $model->save();

                // Mark points as credited in payment metadata to prevent double crediting
                $paymentMetadata['points_credited'] = true;
                $paymentMetadata['points_credited_at'] = now()->toISOString();
                $paymentMetadata['points_credited_by'] = 'immediate_response';
                $result['payment']->metadata = json_encode($paymentMetadata);
                $result['payment']->save();
            } else {
                Log::info('Points already credited for this payment, skipping immediate fulfillment', [
                    'payment_id' => $result['payment']->id,
                    'payment_intent_id' => $result['payment_intent']['id'] ?? 'unknown'
                ]);
                // Get current points for response
                $newPoints = $model->points ?? 0;
            }

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
                'payment_intent_id' => $result['payment_intent']['id'] ?? 'unknown',
                'customer_id' => $model->stripe_customer_id,
                'previous_points' => $currentPoints,
                'new_points' => $newPoints,
                'point_transaction_created' => true
            ]);

            return response()->json([
                'success' => true,
                'payment' => $result['payment'],
                'payment_intent' => $result['payment_intent'],
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
                'company_address' => '〒107-0052 東京都港区六本木4丁目8-7六本木三河台ビル',
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
                        <div>〒107-0052</div>
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
            $result = $this->stripeService->createRefund(
                $payment->stripe_payment_intent_id,
                $request->amount
            );

            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'payment' => $payment,
                'refund' => $result,
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
     * Handle Stripe webhook
     */
    public function webhook(Request $request)
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('Stripe-Signature');

            $result = $this->stripeService->handleWebhook($payload, $signature);

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
     * Create SetupIntent for card registration
     * Returns client_secret for frontend to use with confirmCardSetup()
     */
    public function createSetupIntent(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:guest,cast',
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

            // If user doesn't have a Stripe customer ID, or if the customer doesn't exist in Stripe, create one
            $customerId = $model->stripe_customer_id;
            $needsCustomerCreation = false;

            if (!$customerId) {
                $needsCustomerCreation = true;
            } else {
                // Verify customer exists in Stripe
                try {
                    \Stripe\Customer::retrieve($customerId);
                    Log::info('Customer verified in Stripe', [
                        'customer_id' => $customerId
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Customer does not exist in Stripe, will create new one', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage()
                    ]);
                    // Clear invalid customer ID from database
                    $model->stripe_customer_id = null;
                    $model->save();
                    $needsCustomerCreation = true;
                    $customerId = null; // Reset so we create a new one
                }
            }

            if ($needsCustomerCreation) {
                try {
                    $customerResult = $this->stripeService->createCustomer([
                        'email' => $model->email ?? null,
                        'name' => $model->nickname ?? null,
                        'description' => "{$request->user_type}_{$request->user_id}",
                        'metadata' => [
                            'user_id' => $request->user_id,
                            'user_type' => $request->user_type,
                            'nickname' => $model->nickname ?? '',
                            'phone' => $model->phone ?? '',
                        ],
                    ]);

                    // Save customer ID to user model
                    $model->stripe_customer_id = $customerResult['id'];
                    $customerId = $customerResult['id'];

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
            }

            // Ensure we have a valid customer ID at this point
            if (!$customerId) {
                Log::error('No valid customer ID available for SetupIntent creation', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'model_customer_id' => $model->stripe_customer_id
                ]);

                return response()->json([
                    'success' => false,
                    'error' => '顧客IDが無効です。もう一度お試しください。',
                ], 400);
            }

            // Refresh model to ensure we have the latest customer ID
            $model->refresh();
            $customerId = $model->stripe_customer_id ?? $customerId;

            // Final verification that customer exists before creating SetupIntent
            try {
                $verifyCustomer = \Stripe\Customer::retrieve($customerId);
                Log::info('Customer verified before SetupIntent creation', [
                    'customer_id' => $customerId,
                    'customer_email' => $verifyCustomer->email ?? null
                ]);
            } catch (\Exception $e) {
                Log::error('Customer verification failed before SetupIntent creation', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => '顧客の確認に失敗しました: ' . $e->getMessage(),
                ], 400);
            }

            // Create SetupIntent for card registration
            try {
                $setupIntentResult = $this->stripeService->createSetupIntentForCardRegistration(
                    $customerId
                );

                Log::info('SetupIntent created for card registration', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'customer_id' => $customerId,
                    'setup_intent_id' => $setupIntentResult['setup_intent']['id'] ?? null
                ]);

                return response()->json([
                    'success' => true,
                    'client_secret' => $setupIntentResult['setup_intent']['client_secret'],
                    'setup_intent_id' => $setupIntentResult['setup_intent']['id'],
                ]);
            } catch (\Exception $e) {
                // Check if error is due to customer not existing
                $errorMessage = $e->getMessage();
                $isCustomerNotFound = stripos($errorMessage, 'No such customer') !== false ||
                    stripos($errorMessage, 'No such Customer') !== false;

                if ($isCustomerNotFound) {
                    Log::warning('Customer not found when creating SetupIntent, creating new customer', [
                        'user_id' => $request->user_id,
                        'user_type' => $request->user_type,
                        'old_customer_id' => $customerId,
                        'error' => $e->getMessage()
                    ]);

                    // Clear invalid customer ID and create new customer
                    $model->stripe_customer_id = null;
                    $model->save();

                    try {
                        $customerResult = $this->stripeService->createCustomer([
                            'email' => $model->email ?? null,
                            'name' => $model->nickname ?? null,
                            'description' => "{$request->user_type}_{$request->user_id}",
                            'metadata' => [
                                'user_id' => $request->user_id,
                                'user_type' => $request->user_type,
                                'nickname' => $model->nickname ?? '',
                                'phone' => $model->phone ?? '',
                            ],
                        ]);

                        // Save new customer ID
                        $model->stripe_customer_id = $customerResult['id'];
                        $customerId = $customerResult['id'];

                        // Store additional customer metadata
                        $customerMetadata = [
                            'customer_id' => $customerResult['id'],
                            'created_at' => now()->toISOString(),
                            'user_type' => $request->user_type,
                            'user_id' => $request->user_id,
                        ];

                        $model->payment_info = json_encode($customerMetadata);
                        $model->save();

                        Log::info('New customer created after SetupIntent failure', [
                            'user_id' => $request->user_id,
                            'user_type' => $request->user_type,
                            'new_customer_id' => $customerId
                        ]);

                        // Retry SetupIntent creation with new customer
                        $setupIntentResult = $this->stripeService->createSetupIntentForCardRegistration(
                            $customerId
                        );

                        return response()->json([
                            'success' => true,
                            'client_secret' => $setupIntentResult['setup_intent']['client_secret'],
                            'setup_intent_id' => $setupIntentResult['setup_intent']['id'],
                        ]);
                    } catch (\Exception $retryException) {
                        Log::error('Failed to create new customer and SetupIntent after retry', [
                            'user_id' => $request->user_id,
                            'user_type' => $request->user_type,
                            'error' => $retryException->getMessage()
                        ]);

                        return response()->json([
                            'success' => false,
                            'error' => '顧客の作成に失敗しました: ' . $retryException->getMessage(),
                        ], 400);
                    }
                }

                Log::error('Failed to create SetupIntent', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'SetupIntentの作成に失敗しました: ' . $e->getMessage(),
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Create SetupIntent failed: ' . $e->getMessage(), [
                'user_id' => $request->user_id ?? 'unknown',
                'user_type' => $request->user_type ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'SetupIntent作成中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * Register a card for a user
     * Accepts setup_intent_id from frontend after confirmCardSetup() is completed
     */
    public function registerCard(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:guest,cast',
            'setup_intent_id' => 'required|string', // Stripe SetupIntent ID
        ]);

        // Log Stripe key information for debugging (first 10 chars only for security)
        $secretKey = config('services.stripe.secret_key', env('STRIPE_SECRET_KEY'));
        $publicKey = config('services.stripe.public_key', env('STRIPE_PUBLIC_KEY'));
        Log::info('Stripe key configuration check', [
            'secret_key_prefix' => $secretKey ? substr($secretKey, 0, 10) . '...' : 'NOT SET',
            'public_key_prefix' => $publicKey ? substr($publicKey, 0, 10) . '...' : 'NOT SET',
            'secret_key_mode' => $secretKey ? (strpos($secretKey, 'sk_test_') === 0 ? 'TEST' : (strpos($secretKey, 'sk_live_') === 0 ? 'LIVE' : 'UNKNOWN')) : 'NOT SET',
            'public_key_mode' => $publicKey ? (strpos($publicKey, 'pk_test_') === 0 ? 'TEST' : (strpos($publicKey, 'pk_live_') === 0 ? 'LIVE' : 'UNKNOWN')) : 'NOT SET',
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

            // If user doesn't have a Stripe customer ID, create one
            if (!$model->stripe_customer_id) {
                try {
                    $customerResult = $this->stripeService->createCustomer([
                        'email' => $model->email ?? null,
                        'name' => $model->nickname ?? null,
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
                $model->stripe_customer_id = $customerResult['id'];

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

            // Retrieve SetupIntent and extract payment method
            try {
                Log::info('Retrieving SetupIntent to extract payment method', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'customer_id' => $model->stripe_customer_id,
                    'setup_intent_id' => $request->setup_intent_id
                ]);

                $setupIntent = \Stripe\SetupIntent::retrieve($request->setup_intent_id, [
                    'expand' => ['payment_method']
                ]);

                // Verify SetupIntent belongs to this customer
                if ($setupIntent->customer !== $model->stripe_customer_id) {
                    Log::error('SetupIntent customer mismatch', [
                        'setup_intent_customer' => $setupIntent->customer,
                        'expected_customer' => $model->stripe_customer_id
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'SetupIntentがこの顧客に属していません',
                    ], 400);
                }

                // Check SetupIntent status
                if ($setupIntent->status !== 'succeeded') {
                    Log::warning('SetupIntent not succeeded', [
                        'setup_intent_id' => $request->setup_intent_id,
                        'status' => $setupIntent->status
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'SetupIntentが完了していません。ステータス: ' . $setupIntent->status,
                    ], 400);
                }

                // Extract payment method from SetupIntent
                $paymentMethodId = $setupIntent->payment_method;

                if (!$paymentMethodId) {
                    Log::error('SetupIntent has no payment method', [
                        'setup_intent_id' => $request->setup_intent_id,
                        'status' => $setupIntent->status
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'SetupIntentに支払い方法が含まれていません',
                    ], 400);
                }

                // Payment method is already attached to customer via SetupIntent
                // Just verify it's attached
                try {
                    $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);

                    if ($paymentMethod->customer !== $model->stripe_customer_id) {
                        // Attach if not already attached
                        $paymentMethod->attach(['customer' => $model->stripe_customer_id]);
                    }

                    Log::info('Payment method extracted from SetupIntent and verified', [
                        'user_id' => $request->user_id,
                        'user_type' => $request->user_type,
                        'customer_id' => $model->stripe_customer_id,
                        'payment_method_id' => $paymentMethodId,
                        'setup_intent_id' => $request->setup_intent_id
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to verify payment method from SetupIntent', [
                        'user_id' => $request->user_id,
                        'user_type' => $request->user_type,
                        'customer_id' => $model->stripe_customer_id,
                        'payment_method_id' => $paymentMethodId,
                        'error' => $e->getMessage()
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => '支払い方法の確認に失敗しました: ' . $e->getMessage(),
                    ], 400);
                }
            } catch (\Exception $e) {
                Log::error('Failed to retrieve SetupIntent', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'customer_id' => $model->stripe_customer_id,
                    'setup_intent_id' => $request->setup_intent_id,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Provide more specific error message
                $errorMessage = $e->getMessage();
                if (strpos($errorMessage, 'No such SetupIntent') !== false) {
                    $errorMessage = 'SetupIntentが見つかりませんでした。もう一度お試しください。';
                } else {
                    $errorMessage = 'カードの登録に失敗しました: ' . $errorMessage;
                }

                return response()->json([
                    'success' => false,
                    'error' => $errorMessage,
                ], 400);
            }

            // Update payment_info with card information (idempotent - check if SetupIntent already processed)
            $paymentInfo = json_decode($model->payment_info, true) ?: [];
            $processedSetupIntents = $paymentInfo['processed_setup_intents'] ?? [];

            if (!in_array($request->setup_intent_id, $processedSetupIntents)) {
                $paymentInfo['last_payment_method'] = $paymentMethodId;
                $paymentInfo['last_card_added'] = now()->toISOString();
                $paymentInfo['card_count'] = ($paymentInfo['card_count'] ?? 0) + 1;
                $paymentInfo['setup_intent_id'] = $request->setup_intent_id;

                // Track processed SetupIntents to prevent double counting
                if (!is_array($processedSetupIntents)) {
                    $processedSetupIntents = [];
                }
                $processedSetupIntents[] = $request->setup_intent_id;
                $paymentInfo['processed_setup_intents'] = $processedSetupIntents;

                $model->payment_info = json_encode($paymentInfo);
            } else {
                Log::info('SetupIntent already processed via API, skipping duplicate registration', [
                    'setup_intent_id' => $request->setup_intent_id,
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type
                ]);
            }

            if (!$model->save()) {
                Log::error('Failed to update payment info in database', [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'customer_id' => $model->stripe_customer_id
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'カード情報の保存に失敗しました',
                ], 500);
            }

            Log::info('Payment method successfully registered from SetupIntent', [
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'customer_id' => $model->stripe_customer_id,
                'payment_method_id' => $paymentMethodId,
                'setup_intent_id' => $request->setup_intent_id
            ]);

            return response()->json([
                'success' => true,
                'customer_id' => $model->stripe_customer_id,
                'payment_method_id' => $paymentMethodId,
                'setup_intent_id' => $request->setup_intent_id,
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
                'stripe_customer_id' => $customerInfo['stripe_customer_id'],
                'payjp_customer_id' => $customerInfo['payjp_customer_id'], // Keep for backward compatibility
                'has_registered_cards' => $customerInfo['has_registered_cards'],
                'card_count' => $customerInfo['card_count'],
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

            // Check if user has any active reservations before allowing card deletion
            $activeReservations = $this->checkActiveReservations($userId, $userType);
            if ($activeReservations['has_active']) {
                return response()->json([
                    'success' => false,
                    'error' => $activeReservations['message'],
                    'active_reservations' => $activeReservations['reservations']
                ], 400);
            }

            // If user has a Stripe customer ID, try to delete the card from Stripe
            if ($model->stripe_customer_id) {
                try {
                    $result = $this->stripeService->deletePaymentMethod($cardId);

                    // Check if this was the last card by getting remaining cards
                    $paymentMethods = $this->stripeService->getCustomerPaymentMethods($model->stripe_customer_id);
                    if ($paymentMethods && isset($paymentMethods['data']) && count($paymentMethods['data']) === 0) {
                        // No more cards, clear the customer ID and payment info
                        $model->stripe_customer_id = null;
                        $model->payment_info = null;
                        $model->save();
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to delete card from Stripe: ' . $e->getMessage());
                    // Continue with local cleanup even if Stripe deletion fails
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
            'type' => 'required|in:buy,transfer,convert,gift,pending,exceeded_pending',
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

    /**
     * Process automatic payment for insufficient points during exceeded time
     */
    public function processAutomaticPayment(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|integer|exists:guests,id',
            'required_points' => 'required|integer|min:1',
            'reservation_id' => 'nullable|integer|exists:reservations,id',
            'description' => 'nullable|string|max:255'
        ]);

        try {
            $automaticPaymentService = app(AutomaticPaymentService::class);

            $result = $automaticPaymentService->processAutomaticPaymentForInsufficientPoints(
                $request->guest_id,
                $request->required_points,
                $request->reservation_id ?? 0,
                $request->description ?? 'Automatic payment for insufficient points'
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Automatic payment processed successfully',
                    'payment_id' => $result['payment_id'],
                    'amount_yen' => $result['amount_yen'],
                    'points_added' => $result['points_added'],
                    'new_balance' => $result['new_balance']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'requires_card_registration' => $result['requires_card_registration'] ?? false
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Automatic payment API error', [
                'guest_id' => $request->guest_id,
                'required_points' => $request->required_points,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process automatic payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if guest has registered payment method for automatic payments
     */
    public function checkAutomaticPaymentEligibility(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|integer|exists:guests,id'
        ]);

        try {
            $automaticPaymentService = app(AutomaticPaymentService::class);

            $hasPaymentMethod = $automaticPaymentService->hasRegisteredPaymentMethod($request->guest_id);
            $paymentInfo = $automaticPaymentService->getGuestPaymentInfo($request->guest_id);

            return response()->json([
                'success' => true,
                'has_payment_method' => $hasPaymentMethod,
                'payment_info' => $paymentInfo,
                'eligible_for_automatic_payment' => $hasPaymentMethod
            ]);

        } catch (\Exception $e) {
            Log::error('Check automatic payment eligibility error', [
                'guest_id' => $request->guest_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment eligibility',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get automatic payment history for a guest
     */
    public function getAutomaticPaymentHistory(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|integer|exists:guests,id'
        ]);

        try {
            $auditService = app(AutomaticPaymentAuditService::class);

            $payments = $auditService->getGuestAutomaticPayments($request->guest_id);
            $summary = $auditService->getGuestAutomaticPaymentSummary($request->guest_id);

            return response()->json([
                'success' => true,
                'payments' => $payments,
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Get automatic payment history error', [
                'guest_id' => $request->guest_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get automatic payment audit trail
     */
    public function getAutomaticPaymentAuditTrail(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|integer|exists:guests,id',
            'reservation_id' => 'nullable|integer|exists:reservations,id'
        ]);

        try {
            $auditService = app(AutomaticPaymentAuditService::class);

            $auditTrail = $auditService->getAuditTrail(
                $request->guest_id,
                $request->reservation_id
            );

            return response()->json([
                'success' => true,
                'audit_trail' => $auditTrail
            ]);

        } catch (\Exception $e) {
            Log::error('Get automatic payment audit trail error', [
                'guest_id' => $request->guest_id,
                'reservation_id' => $request->reservation_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get audit trail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get automatic payments for a reservation
     */
    public function getReservationAutomaticPayments(Request $request, $reservationId)
    {
        try {
            $auditService = app(AutomaticPaymentAuditService::class);

            $payments = $auditService->getReservationAutomaticPayments($reservationId);

            return response()->json([
                'success' => true,
                'payments' => $payments
            ]);

        } catch (\Exception $e) {
            Log::error('Get reservation automatic payments error', [
                'reservation_id' => $reservationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get reservation payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user has any active reservations
     *
     * @param int $userId
     * @param string $userType
     * @return array
     */
    private function checkActiveReservations($userId, $userType)
    {
        try {
            $now = now();

            if ($userType === 'guest') {
                // Check for guest reservations that are truly active (started but not ended) or future
                $activeReservations = \App\Models\Reservation::where('guest_id', $userId)
                    ->where('active', true)
                    ->where(function ($query) use ($now) {
                        $query->where(function ($subQuery) {
                            // Started but not ended (truly active)
                            $subQuery->whereNotNull('started_at')
                                ->whereNull('ended_at');
                        })->orWhere(function ($subQuery) use ($now) {
                            // Future reservations (not started yet)
                            $subQuery->whereNull('started_at')
                                ->whereNull('ended_at')
                                ->where('scheduled_at', '>', $now);
                        });
                    })
                    ->get();

                if ($activeReservations->count() > 0) {
                    $reservationDetails = $activeReservations->map(function ($reservation) {
                        $status = '予約済み';
                        if ($reservation->started_at && !$reservation->ended_at) {
                            $status = '進行中';
                        } elseif ($reservation->scheduled_at && $reservation->scheduled_at > now()) {
                            $status = '予定済み';
                        }

                        return [
                            'id' => $reservation->id,
                            'type' => $reservation->type,
                            'scheduled_at' => $reservation->scheduled_at,
                            'started_at' => $reservation->started_at,
                            'ended_at' => $reservation->ended_at,
                            'status' => $status
                        ];
                    });

                    return [
                        'has_active' => true,
                        'message' => '進行中または予定されている予約があるため、カードを削除できません。予約が完了してから再度お試しください。',
                        'reservations' => $reservationDetails
                    ];
                }
            } else {
                // Check for cast reservations (as cast_id or in cast_ids array) that are truly active
                $activeReservations = \App\Models\Reservation::where(function ($query) use ($userId) {
                    $query->where('cast_id', $userId)
                        ->orWhereJsonContains('cast_ids', $userId);
                })
                ->where('active', true)
                ->where(function ($query) use ($now) {
                    $query->where(function ($subQuery) {
                        // Started but not ended (truly active)
                        $subQuery->whereNotNull('started_at')
                            ->whereNull('ended_at');
                    })->orWhere(function ($subQuery) use ($now) {
                        // Future reservations (not started yet)
                        $subQuery->whereNull('started_at')
                            ->whereNull('ended_at')
                            ->where('scheduled_at', '>', $now);
                    });
                })
                ->get();

                if ($activeReservations->count() > 0) {
                    $reservationDetails = $activeReservations->map(function ($reservation) {
                        $status = '予約済み';
                        if ($reservation->started_at && !$reservation->ended_at) {
                            $status = '進行中';
                        } elseif ($reservation->scheduled_at && $reservation->scheduled_at > now()) {
                            $status = '予定済み';
                        }

                        return [
                            'id' => $reservation->id,
                            'type' => $reservation->type,
                            'scheduled_at' => $reservation->scheduled_at,
                            'started_at' => $reservation->started_at,
                            'ended_at' => $reservation->ended_at,
                            'status' => $status
                        ];
                    });

                    return [
                        'has_active' => true,
                        'message' => '進行中または予定されている予約があるため、カードを削除できません。予約が完了してから再度お試しください。',
                        'reservations' => $reservationDetails
                    ];
                }
            }

            return [
                'has_active' => false,
                'message' => '',
                'reservations' => []
            ];

        } catch (\Exception $e) {
            Log::error('Failed to check active reservations', [
                'user_id' => $userId,
                'user_type' => $userType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // If we can't check reservations, allow deletion to avoid blocking users
            return [
                'has_active' => false,
                'message' => '',
                'reservations' => []
            ];
        }
    }

    /**
     * Process automatic payment with pending transaction
     */
    public function processAutomaticPaymentWithPending(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|integer|exists:guests,id',
            'required_points' => 'required|integer|min:1',
            'reservation_id' => 'required|integer|exists:reservations,id',
            'description' => 'nullable|string|max:255'
        ]);

        try {
            $automaticPaymentService = app(\App\Services\AutomaticPaymentWithPendingService::class);

            $result = $automaticPaymentService->processAutomaticPaymentWithPending(
                $request->guest_id,
                $request->required_points,
                $request->reservation_id,
                $request->description ?? 'Automatic payment with pending for insufficient points'
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Automatic payment with pending processed successfully',
                    'payment_id' => $result['payment_id'],
                    'point_transaction_id' => $result['point_transaction_id'],
                    'amount_yen' => $result['amount_yen'],
                    'points_added' => $result['points_added'],
                    'new_balance' => $result['new_balance'],
                    'expires_at' => $result['expires_at']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'requires_card_registration' => $result['requires_card_registration'] ?? false
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Automatic payment with pending API error', [
                'guest_id' => $request->guest_id,
                'required_points' => $request->required_points,
                'reservation_id' => $request->reservation_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process automatic payment with pending',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process pending automatic payments (for cron job)
     */
    public function processPendingAutomaticPayments()
    {
        try {
            $automaticPaymentService = app(\App\Services\AutomaticPaymentWithPendingService::class);
            $result = $automaticPaymentService->processPendingPaymentsForCapture();

            return response()->json([
                'success' => $result['success'],
                'processed_count' => $result['processed_count'] ?? 0,
                'failed_count' => $result['failed_count'] ?? 0,
                'total_found' => $result['total_found'] ?? 0,
                'error' => $result['error'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Process pending automatic payments API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process pending automatic payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment intent with payment method (for on-session confirmation)
     */
    public function updatePaymentIntent(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
            'payment_method' => 'required|string',
        ]);

        try {
            $paymentIntent = $this->stripeService->updatePaymentIntent(
                $request->payment_intent_id,
                $request->payment_method
            );

            return response()->json([
                'success' => true,
                'payment_intent' => $paymentIntent,
            ]);
        } catch (\Exception $e) {
            Log::error('Payment intent update failed: ' . $e->getMessage(), [
                'payment_intent_id' => $request->payment_intent_id,
                'payment_method' => $request->payment_method,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Purchase points with manual capture for insufficient points scenario
     * This creates a pending payment that will be captured after 2 days
     */
    public function purchaseWithPendingCapture(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:guest,cast',
            'amount' => 'required|integer|min:100',
            'required_points' => 'required|integer|min:1',
            'description' => 'nullable|string',
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

            // Check if user has a registered payment method
            if (!$model->stripe_customer_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'カード情報が必要です。カードを登録してください。',
                    'requires_card_registration' => true,
                ], 400);
            }

            // Use the existing AutomaticPaymentWithPendingService
            $automaticPaymentService = app(\App\Services\AutomaticPaymentWithPendingService::class);

            $result = $automaticPaymentService->processAutomaticPaymentWithPending(
                $request->user_id,
                $request->required_points,
                0, // No reservation ID for insufficient points modal (use 0 instead of null)
                $request->description ?? "ポイント不足時の自動支払い - {$request->required_points}ポイント"
            );

            if ($result['success']) {
                $response = [
                    'success' => true,
                    'payment_id' => $result['payment_id'],
                    'point_transaction_id' => $result['point_transaction_id'],
                    'amount_yen' => $result['amount_yen'],
                    'points_added' => $result['points_added'],
                    'new_balance' => $result['new_balance'],
                    'expires_at' => $result['expires_at'],
                    'payment_intent_id' => $result['payment_intent_id'],
                    'message' => 'ポイントが追加されました。2日後に自動的に支払いが完了します。'
                ];

                // Include authentication fields if authentication is required
                if (isset($result['requires_authentication']) && $result['requires_authentication']) {
                    $response['requires_authentication'] = true;
                    $response['client_secret'] = $result['client_secret'] ?? null;
                    $response['payment_intent_status'] = $result['payment_intent_status'] ?? null;
                }

                return response()->json($response);
            } else {
                // Check if this is an on-session error that requires returning payment intent details
                if (isset($result['requires_on_session']) && $result['requires_on_session']) {
                    return response()->json([
                        'success' => false,
                        'error' => $result['error'],
                        'requires_on_session' => true,
                        'client_secret' => $result['client_secret'] ?? null,
                        'payment_intent_id' => $result['payment_intent_id'] ?? null,
                        'payment_intent' => $result['payment_intent'] ?? null,
                        'requires_authentication' => true,
                        'requires_card_registration' => $result['requires_card_registration'] ?? false,
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'requires_card_registration' => $result['requires_card_registration'] ?? false,
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Purchase with pending capture failed: ' . $e->getMessage(), [
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
}
