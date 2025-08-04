<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cast;
use App\Models\PointTransaction;
use App\Models\Payment;
use App\Services\PayJPService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CastPaymentController extends Controller
{
    protected $payJPService;

    public function __construct(PayJPService $payJPService)
    {
        $this->payJPService = $payJPService;
    }

    /**
     * Get cast immediate payment data
     */
    public function getImmediatePaymentData($castId)
    {
        try {
            $cast = Cast::findOrFail($castId);
            
            // Calculate total points earned this month
            $currentMonth = now()->format('Y-m');
            $monthlyPoints = PointTransaction::where('cast_id', $castId)
                ->where('type', 'transfer')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('amount');

            // Calculate immediate points (50% of monthly points)
            $immediatePoints = floor($monthlyPoints * 0.5);
            
            // Calculate fee based on cast grade
            $feeRate = $this->getFeeRateByGrade($cast->grade ?? 'bronze');
            $fee = floor($immediatePoints * $feeRate);
            
            // Calculate final amount
            $amount = $immediatePoints - $fee;

            return response()->json([
                'total_points' => $monthlyPoints,
                'immediate_points' => $immediatePoints,
                'fee' => $fee,
                'amount' => $amount,
                'fee_rate' => $feeRate * 100, // Convert to percentage
                'cast_grade' => $cast->grade ?? 'bronze'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get immediate payment data', [
                'cast_id' => $castId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to get payment data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process immediate payment request
     */
    public function processImmediatePayment(Request $request, $castId)
    {
        $validator = $request->validate([
            'amount' => 'required|integer|min:1',
            'payjp_token' => 'required|string', // This can be either a token or customer_id
        ]);

        try {
            DB::beginTransaction();

            $cast = Cast::findOrFail($castId);
            
            // Verify the cast has enough points
            $currentMonth = now()->format('Y-m');
            $monthlyPoints = PointTransaction::where('cast_id', $castId)
                ->where('type', 'transfer')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('amount');

            $immediatePoints = floor($monthlyPoints * 0.5);
            
            if ($immediatePoints < $request->amount) {
                return response()->json([
                    'message' => 'Insufficient points for immediate payment'
                ], 400);
            }

            // Create payment record
            $payment = Payment::create([
                'user_id' => $castId,
                'user_type' => 'cast',
                'amount' => $request->amount,
                'status' => 'pending',
                'payment_method' => 'card',
                'payjp_token' => $request->payjp_token,
                'description' => 'Immediate payment request',
                'metadata' => [
                    'type' => 'immediate_payment',
                    'monthly_points' => $monthlyPoints,
                    'immediate_points' => $immediatePoints,
                    'fee_rate' => $this->getFeeRateByGrade($cast->grade ?? 'bronze') * 100
                ]
            ]);

            // Process payment through PayJP
            // Check if payjp_token is a customer_id (starts with 'cus_') or a card token
            $isCustomerId = str_starts_with($request->payjp_token, 'cus_');
            
            $chargeParams = [
                'amount' => $request->amount,
                'currency' => 'jpy',
                'description' => "Immediate payment for cast {$cast->nickname}",
                'metadata' => [
                    'cast_id' => $castId,
                    'payment_id' => $payment->id
                ]
            ];
            
            if ($isCustomerId) {
                // Use customer_id for existing registered card
                $chargeParams['customer'] = $request->payjp_token;
            } else {
                // Use card token for new card
                $chargeParams['card'] = $request->payjp_token;
            }
            
            $chargeResult = $this->payJPService->createCharge($chargeParams);

            if ($chargeResult['success']) {
                // Update payment record
                $payment->update([
                    'status' => 'paid',
                    'payjp_charge_id' => $chargeResult['charge_id'],
                    'paid_at' => now()
                ]);

                // Deduct points from cast
                $cast->points -= $request->amount;
                $cast->save();

                // Create point transaction record
                PointTransaction::create([
                    'cast_id' => $castId,
                    'type' => 'convert',
                    'amount' => -$request->amount, // Negative for deduction
                    'description' => 'Immediate payment withdrawal - converted points to money'
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Immediate payment processed successfully',
                    'payment' => $payment,
                    'charge_id' => $chargeResult['charge_id']
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment processing failed',
                    'error' => $chargeResult['error']
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process immediate payment', [
                'cast_id' => $castId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to process payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fee rate based on cast grade
     */
    private function getFeeRateByGrade($grade)
    {
        switch ($grade) {
            case 'platinum':
                return 0.00; // 0%
            case 'gold':
                return 0.02; // 2%
            case 'silver':
                return 0.04; // 4%
            default:
                return 0.05; // 5% for bronze/default
        }
    }
} 