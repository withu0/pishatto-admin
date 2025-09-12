<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfobipService
{
    protected $apiKey;
    protected $baseUrl;
    protected $fromNumber;

    public function __construct()
    {
        $this->apiKey = config('services.infobip.api_key');
        $this->baseUrl = config('services.infobip.base_url');
        $this->fromNumber = config('services.infobip.from_number', 'ServiceSMS');
    }

    /**
     * Send verification code via SMS using Infobip
     */
    public function sendVerificationCode($phoneNumber)
    {
        // Generate a 6-digit verification code
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store the verification code in cache for 10 minutes
        $cacheKey = "verification_code_{$phoneNumber}";
        Cache::put($cacheKey, $verificationCode, 600); // 10 minutes
        
        // Development mode: bypass Infobip for testing
        if (app()->environment('local') || app()->environment('development') || config('app.debug')) {
            Log::info("Development mode: SMS verification code for {$phoneNumber}: {$verificationCode}");
            return [
                'success' => true,
                'message' => 'Verification code sent successfully (development mode)',
                'code' => $verificationCode // Always include code in development
            ];
        }
        
        // Format phone number for Infobip (ensure it has country code)
        $formattedPhone = $this->formatPhoneNumberForInfobip($phoneNumber);
        
        // Send SMS via Infobip
        try {
            $response = Http::withHeaders([
                'Authorization' => 'App ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/sms/2/text/advanced', [
                'messages' => [
                    [
                        'destinations' => [
                            ['to' => $formattedPhone]
                        ],
                        'from' => $this->fromNumber,
                        'text' => "あなたのPishatto確認コード：{$verificationCode}\nこのコードを他人と共有しないでください"
                    ]
                ]
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'message' => 'Verification code sent successfully',
                    'message_id' => $responseData['messages'][0]['messageId'] ?? null
                ];
            } else {
                Log::error('Infobip SMS failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [
                    'success' => false,
                    'message' => 'Failed to send verification code: ' . $response->body()
                ];
            }
        } catch (\Exception $e) {
            Log::error('Infobip SMS exception', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber
            ]);
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
        
        Log::info("Verification attempt", [
            'phone' => $phoneNumber,
            'stored_code' => $storedCode,
            'provided_code' => $code,
            'cache_has' => Cache::has($cacheKey)
        ]);
        
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
        
        // Remove the original verification code
        Cache::forget($cacheKey);
        
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
     * Format phone number for Infobip
     * Convert Japanese phone number format to international format
     */
    private function formatPhoneNumberForInfobip($phoneNumber)
    {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If it starts with 0, remove it and add 81
        if (str_starts_with($phoneNumber, '0')) {
            $phoneNumber = '81' . ltrim($phoneNumber, '0');
        }
        
        // If it doesn't start with country code, add 81
        if (!str_starts_with($phoneNumber, '81')) {
            $phoneNumber = '81' . $phoneNumber;
        }
        
        return $phoneNumber;
    }

    /**
     * Send SMS with custom message
     */
    public function sendSms($phoneNumber, $message)
    {
        // Format phone number for Infobip
        $formattedPhone = $this->formatPhoneNumberForInfobip($phoneNumber);
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'App ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/sms/2/text/advanced', [
                'messages' => [
                    [
                        'destinations' => [
                            ['to' => $formattedPhone]
                        ],
                        'from' => $this->fromNumber,
                        'text' => $message
                    ]
                ]
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'message_id' => $responseData['messages'][0]['messageId'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send SMS: ' . $response->body()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get delivery status of a message
     */
    public function getDeliveryStatus($messageId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'App ' . $this->apiKey,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/sms/1/logs', [
                'messageId' => $messageId
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to get delivery status: ' . $response->body()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get delivery status: ' . $e->getMessage()
            ];
        }
    }
}
