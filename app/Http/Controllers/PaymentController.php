<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Receipt;

class PaymentController extends Controller
{
    // Initiate a point purchase
    public function purchase(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string',
            'amount' => 'required|integer',
        ]);
        $payment = Payment::create([
            'user_id' => $request->user_id,
            'user_type' => $request->user_type,
            'amount' => $request->amount,
            'status' => 'pending',
            'payment_info' => $request->payment_info ?? null,
        ]);
        // For demo, return a fake payment link
        $paymentLink = url('/payment/redirect/' . $payment->id);
        return response()->json(['payment' => $payment, 'payment_link' => $paymentLink]);
    }

    // List payment/point history
    public function history($userType, $userId)
    {
        $payments = Payment::where('user_type', $userType)->where('user_id', $userId)->orderBy('created_at', 'desc')->get();
        return response()->json(['payments' => $payments]);
    }

    // List receipts
    public function receipts($userType, $userId)
    {
        $receipts = Receipt::where('user_type', $userType)->where('user_id', $userId)->orderBy('created_at', 'desc')->get();
        return response()->json(['receipts' => $receipts]);
    }

    // Register/update payment info
    public function registerPaymentInfo(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string',
            'payment_info' => 'required|string',
        ]);
        // For demo, store payment info in Payment model (latest record)
        $payment = Payment::where('user_type', $request->user_type)->where('user_id', $request->user_id)->latest()->first();
        if ($payment) {
            $payment->payment_info = $request->payment_info;
            $payment->save();
        }
        return response()->json(['success' => true]);
    }

    // Fetch payment info
    public function getPaymentInfo($userType, $userId)
    {
        $payment = Payment::where('user_type', $userType)->where('user_id', $userId)->latest()->first();
        return response()->json(['payment_info' => $payment ? $payment->payment_info : null]);
    }

    // Cast payout request
    public function requestPayout(Request $request)
    {
        $request->validate([
            'cast_id' => 'required|integer',
            'amount' => 'required|integer',
        ]);
        $payment = Payment::create([
            'user_id' => $request->cast_id,
            'user_type' => 'cast',
            'amount' => $request->amount,
            'type' => 'payout',
            'status' => 'pending',
        ]);
        return response()->json(['payout' => $payment]);
    }
}
