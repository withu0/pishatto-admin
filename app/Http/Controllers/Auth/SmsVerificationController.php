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
            'phone' => 'required|string|regex:/^0\d{9,10}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '電話番号は0で始まる10桁または11桁の数字で入力してください',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $request->phone;
        
        // Format phone number for Twilio - remove leading 0 and add 81
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
            'phone' => 'required|string|regex:/^0\d{9,10}$/',
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
        
        // Format phone number for Twilio - remove leading 0 and add 81
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
            'phone' => 'required|string|regex:/^0\d{9,10}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '電話番号は0で始まる10桁または11桁の数字で入力してください',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $request->phone;
        
        // Format phone number for Twilio - remove leading 0 and add 81
        $phoneNumber = $this->formatPhoneNumberForTwilio($phoneNumber);

        $isVerified = $this->twilioService->isPhoneVerified($phoneNumber);

        return response()->json([
            'success' => true,
            'verified' => $isVerified
        ]);
    }

    /**
     * Format phone number for Twilio
     * Remove leading 0 and add 81 for Japanese phone numbers
     */
    private function formatPhoneNumberForTwilio($phoneNumber)
    {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Ensure it starts with 0 (Japanese format)
        if (!str_starts_with($phoneNumber, '0')) {
            throw new \InvalidArgumentException('Phone number must start with 0');
        }
        
        // Remove leading 0 and add +81
        $formattedNumber = '+81' . ltrim($phoneNumber, '0');
        
        return $formattedNumber;
    }

    /**
     * Test method to verify phone number formatting
     * This can be called to test the formatting logic
     */
    public function testPhoneFormatting(Request $request)
    {
        $testNumbers = ['09012345678', '08012345678', '07012345678'];
        $results = [];
        
        foreach ($testNumbers as $number) {
            try {
                $formatted = $this->formatPhoneNumberForTwilio($number);
                $results[$number] = $formatted;
            } catch (\Exception $e) {
                $results[$number] = 'Error: ' . $e->getMessage();
            }
        }
        
        return response()->json([
            'success' => true,
            'test_results' => $results
        ]);
    }
} 