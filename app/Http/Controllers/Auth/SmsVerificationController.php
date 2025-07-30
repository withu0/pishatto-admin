<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SmsVerificationController extends Controller
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    /**
     * Send verification code via SMS
     */
    public function sendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $request->phone;
        // Ensure phone number has country code
        if (!str_starts_with($phoneNumber, '+')) {
            $phoneNumber = '+81' . ltrim($phoneNumber, '0'); // Default to Japan (+81)
        }

        $result = $this->twilioService->sendVerificationCode($phoneNumber);
        if ($result['success']) {
            $response = [
                'success' => true,
                'message' => 'Verification code sent successfully'
            ];
            
            // Always include the code in response for testing purposes
            if (isset($result['code'])) {
                $response['code'] = $result['code'];
            }
            
            return response()->json($response);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 500);
        }
    }

    /**
     * Verify the SMS code
     */
    public function verifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $request->phone;
        
        // Ensure phone number has country code
        if (!str_starts_with($phoneNumber, '+')) {
            $phoneNumber = '+81' . ltrim($phoneNumber, '0'); // Default to Japan (+81)
        }

        $result = $this->twilioService->verifyCode($phoneNumber, $request->code);

        if ($result['success']) {
            // Mark phone as verified
            $this->twilioService->markPhoneAsVerified($phoneNumber);
            
            return response()->json([
                'success' => true,
                'message' => 'Phone number verified successfully'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }
    }

    /**
     * Check if phone number is verified
     */
    public function checkVerificationStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $request->phone;
        
        // Ensure phone number has country code
        if (!str_starts_with($phoneNumber, '+')) {
            $phoneNumber = '+81' . ltrim($phoneNumber, '0'); // Default to Japan (+81)
        }

        $isVerified = $this->twilioService->isPhoneVerified($phoneNumber);

        return response()->json([
            'success' => true,
            'verified' => $isVerified
        ]);
    }
} 