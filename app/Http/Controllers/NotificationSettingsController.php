<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationSettingsController extends Controller
{
    /**
     * Get notification settings for a user
     */
    public function getSettings(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|in:guest,cast',
        ]);

        $settings = NotificationSetting::where('user_id', $request->user_id)
            ->where('user_type', $request->user_type)
            ->get()
            ->keyBy('setting_key');

        // Define default settings
        $defaultSettings = [
            'footprints' => true,
            'likes' => true,
            'messages' => true,
            'concierge_messages' => true,
            'meetup_dissolution' => true,
            'auto_extension' => true,
            'tweet_likes' => true,
            'admin_notices' => true,
            'app_messages' => true,
        ];

        // Merge with saved settings
        $result = [];
        foreach ($defaultSettings as $key => $defaultValue) {
            $result[$key] = $settings->get($key, new NotificationSetting(['enabled' => $defaultValue]))->enabled;
        }

        return response()->json([
            'settings' => $result
        ]);
    }

    /**
     * Update notification settings for a user
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|in:guest,cast',
            'settings' => 'required|array',
            'settings.*' => 'boolean',
        ]);

        $userId = $request->user_id;
        $userType = $request->user_type;
        $settings = $request->settings;

        foreach ($settings as $key => $enabled) {
            NotificationSetting::updateOrCreate(
                [
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'setting_key' => $key,
                ],
                [
                    'enabled' => $enabled,
                ]
            );
        }

        return response()->json([
            'message' => 'Notification settings updated successfully',
            'settings' => $settings
        ]);
    }

    /**
     * Check if a specific notification type is enabled for a user
     */
    public function isEnabled(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|in:guest,cast',
            'setting_key' => 'required|string',
        ]);

        $setting = NotificationSetting::where('user_id', $request->user_id)
            ->where('user_type', $request->user_type)
            ->where('setting_key', $request->setting_key)
            ->first();

        $enabled = $setting ? $setting->enabled : true; // Default to true if not set

        return response()->json([
            'enabled' => $enabled
        ]);
    }
} 