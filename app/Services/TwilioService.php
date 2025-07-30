<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TwilioService
{
    protected $client;
    protected $fromNumber;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.account_sid'),
            config('services.twilio.auth_token')
        );
        $this->fromNumber = config('services.twilio.from_number');
    }

    /**
     * Send verification code via SMS
     */
    public function sendVerificationCode($phoneNumber)
    {
        // Test mode: if phone starts with +081, use fixed code 012012
        // if (str_starts_with($phoneNumber, '+810')) {
        //     $verificationCode = '012012';
        // } else {
        //     // Generate a 6-digit verification code
        //     $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // }
        
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store the verification code in cache for 10 minutes
        $cacheKey = "verification_code_{$phoneNumber}";
        Cache::put($cacheKey, $verificationCode, 600); // 10 minutes
        
        // Development mode: bypass Twilio for testing
        if (app()->environment('local') || app()->environment('development') || config('app.debug')) {
            \Log::info("Development mode: SMS verification code for {$phoneNumber}: {$verificationCode}");
            return [
                'success' => true,
                'message' => 'Verification code sent successfully (development mode)',
                'code' => $verificationCode // Always include code in development
            ];
        }
        
        // Send SMS via Twilio
        try {
            $message = $this->client->messages->create(
                $phoneNumber,
                [
                    'from' => $this->fromNumber,
                    'body' => "Your verification code is: {$verificationCode}. Valid for 10 minutes."
                ]
            );
            
            return [
                'success' => true,
                'message' => 'Verification code sent successfully',
                'sid' => $message->sid
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send verification code: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify the code entered by user
     */
    public function verifyCode($phoneNumber, $code)
    {
        $cacheKey = "verification_code_{$phoneNumber}";
        $storedCode = Cache::get($cacheKey);
        
        error_log("Stored code: " . $storedCode);
        error_log("Code: " . $code);
        error_log("Cache key: " . $cacheKey);
        error_log("Cache has: " . Cache::has($cacheKey));
        error_log("Cache get: " . Cache::get($cacheKey));
        
        if (!$storedCode) {
            return [
                'success' => false,
                'message' => 'Verification code expired or not found'
            ];
        }
        
        if ($storedCode !== $code) {
            return [
                'success' => false,
                'message' => 'Invalid verification code'
            ];
        }
        
        // Mark as verified but keep code in cache for a short time to allow registration
        $verifiedKey = "verified_code_{$phoneNumber}";
        Cache::put($verifiedKey, $code, 300); // Keep verified code for 5 minutes
        
        error_log("Cache forget: " . Cache::forget($cacheKey));
        error_log("Cache has: " . Cache::has($cacheKey));
        
        return [
            'success' => true,
            'message' => 'Verification successful'
        ];
    }

    /**
     * Check if phone number is verified
     */
    public function isPhoneVerified($phoneNumber)
    {
        $cacheKey = "phone_verified_{$phoneNumber}";
        return Cache::has($cacheKey);
    }

    /**
     * Mark phone number as verified
     */
    public function markPhoneAsVerified($phoneNumber)
    {
        $cacheKey = "phone_verified_{$phoneNumber}";
        Cache::put($cacheKey, true, 3600); // Verified for 1 hour
    }

    /**
     * Check if verification code has been verified (for registration)
     */
    public function isCodeVerified($phoneNumber, $code)
    {
        $verifiedKey = "verified_code_{$phoneNumber}";
        $verifiedCode = Cache::get($verifiedKey);
        
        if ($verifiedCode === $code) {
            // Remove the verified code after successful registration
            Cache::forget($verifiedKey);
            return true;
        }
        
        return false;
    }

    /**
     * Send verification code via SMS using Twilio Verify (recommended for production)
     */
    public function sendVerificationCodeWithVerify($phoneNumber)
    {
        try {
            // Use Twilio Verify service instead of direct SMS
            $verifyService = $this->client->verify->v2->services(config('services.twilio.verify_service_sid'));
            $verification = $verifyService->verifications->create($phoneNumber, 'sms');
            
            return [
                'success' => true,
                'message' => 'Verification code sent successfully',
                'sid' => $verification->sid
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send verification code: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify the code using Twilio Verify service
     */
    public function verifyCodeWithVerify($phoneNumber, $code)
    {
        try {
            $verifyService = $this->client->verify->v2->services(config('services.twilio.verify_service_sid'));
            $verificationCheck = $verifyService->verificationChecks->create($code, ['to' => $phoneNumber]);
            
            if ($verificationCheck->status === 'approved') {
                return [
                    'success' => true,
                    'message' => 'Verification successful'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid verification code'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }
} 