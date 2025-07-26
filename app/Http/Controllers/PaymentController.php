<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\PayJPService;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $payJPService;

    public function __construct(PayJPService $payJPService)
    {
        $this->payJPService = $payJPService;
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
            'token' => 'required|string',
            'payment_method' => 'nullable|string|in:card,convenience_store,bank_transfer,linepay,other',
        ]);

        try {
            $paymentData = [
                'user_id' => $request->user_id,
                'user_type' => $request->user_type,
                'amount' => $request->amount,
                'token' => $request->token,
                'payment_method' => $request->payment_method ?? 'card',
                'description' => $request->description ?? "{$request->amount}ポイント購入",
                'metadata' => [
                    'user_id' => $request->user_id,
                    'user_type' => $request->user_type,
                    'points' => $request->amount,
                ],
            ];

            $result = $this->payJPService->processPayment($paymentData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'payment' => $result['payment'],
                'charge' => $result['charge'],
            ]);

        } catch (\Exception $e) {
            Log::error('Payment purchase failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => '決済処理中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * Create a token for card information
     */
    public function createToken(Request $request)
    {
        $request->validate([
            'number' => 'required|string',
            'cvc' => 'required|string',
            'exp_month' => 'required|integer|between:1,12',
            'exp_year' => 'required|integer|min:' . date('Y'),
        ]);

        try {
            $result = $this->payJPService->createToken($request->all());

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'token' => $result['token_id'],
            ]);

        } catch (\Exception $e) {
            Log::error('Token creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'カード情報の処理中にエラーが発生しました',
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
}
