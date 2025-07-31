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
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $request->phone;
        
        // Format phone number for Twilio
        $phoneNumber = $this->formatPhoneNumberForTwilio($phoneNumber);

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
            'phone' => 'required|string',
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
        
        // Format phone number for Twilio
        $phoneNumber = $this->formatPhoneNumberForTwilio($phoneNumber);

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
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $request->phone;
        
        // Format phone number for Twilio
        $phoneNumber = $this->formatPhoneNumberForTwilio($phoneNumber);

        $isVerified = $this->twilioService->isPhoneVerified($phoneNumber);

        return response()->json([
            'success' => true,
            'verified' => $isVerified
        ]);
    }

    /**
     * Format phone number for Twilio
     * Handles cases where users input numbers like 70, 80, 90
     */
    private function formatPhoneNumberForTwilio($phoneNumber)
    {
        // Remove any non-digit characters except +
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // If it starts with +, it's already formatted
        if (str_starts_with($phoneNumber, '+')) {
            return $phoneNumber;
        }
        
        // Handle cases where user inputs 70, 80, 90, etc.
        if (strlen($phoneNumber) <= 2) {
            // Add +0 prefix for short numbers like 70, 80, 90
            return '+0' . $phoneNumber;
        }
        
        // Handle Japanese phone numbers (10-11 digits starting with 0)
        if (strlen($phoneNumber) >= 10 && str_starts_with($phoneNumber, '0')) {
            return '+81' . ltrim($phoneNumber, '0');
        }
        
        // Handle other cases - assume it's a Japanese number
        if (!str_starts_with($phoneNumber, '+')) {
            return '+81' . ltrim($phoneNumber, '0');
        }
        
        return $phoneNumber;
    }

    /**
     * Test method to verify phone number formatting
     * This can be called to test the formatting logic
     */
    public function testPhoneFormatting(Request $request)
    {
        $testNumbers = ['70', '80', '90', '07012345678', '+817012345678', '1234567890'];
        $results = [];
        
        foreach ($testNumbers as $number) {
            $formatted = $this->formatPhoneNumberForTwilio($number);
            $results[$number] = $formatted;
        }
        
        return response()->json([
            'success' => true,
            'test_results' => $results
        ]);
    }
} 