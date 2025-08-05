<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationSetting;

class NotificationService
{
    /**
     * Check if a notification type is enabled for a user
     */
    public static function isNotificationEnabled(int $userId, string $userType, string $settingKey): bool
    {
        $setting = NotificationSetting::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('setting_key', $settingKey)
            ->first();

        return $setting ? $setting->enabled : true; // Default to true if not set
    }

    /**
     * Send a notification if the user has enabled that type
     */
    public static function sendNotificationIfEnabled(
        int $userId, 
        string $userType, 
        string $settingKey, 
        string $type, 
        string $message, 
        ?int $reservationId = null, 
        ?int $castId = null
    ): ?Notification {
        if (!self::isNotificationEnabled($userId, $userType, $settingKey)) {
            return null;
        }

        return Notification::create([
            'user_id' => $userId,
            'user_type' => $userType,
            'type' => $type,
            'message' => $message,
            'reservation_id' => $reservationId,
            'cast_id' => $castId,
            'read' => false,
        ]);
    }

    /**
     * Send footprint notification
     */
    public static function sendFootprintNotification(int $guestId, int $castId, string $castName): ?Notification
    {
        return self::sendNotificationIfEnabled(
            $guestId,
            'guest',
            'footprints',
            'footprint',
            "{$castName}さんがあなたのプロフィールを見ました",
            null,
            $castId
        );
    }

    /**
     * Send like notification
     */
    public static function sendLikeNotification(int $guestId, int $castId, string $castName): ?Notification
    {
        return self::sendNotificationIfEnabled(
            $guestId,
            'guest',
            'likes',
            'like',
            "{$castName}さんがあなたをいいねしました",
            null,
            $castId
        );
    }

    /**
     * Send message notification
     */
    public static function sendMessageNotification(int $guestId, int $castId, string $castName): ?Notification
    {
        return self::sendNotificationIfEnabled(
            $guestId,
            'guest',
            'messages',
            'message',
            "{$castName}さんからメッセージが届きました",
            null,
            $castId
        );
    }

    /**
     * Send concierge message notification
     */
    public static function sendConciergeMessageNotification(int $userId, string $userType): ?Notification
    {
        return self::sendNotificationIfEnabled(
            $userId,
            $userType,
            'concierge_messages',
            'concierge_message',
            'コンシェルジュからメッセージが届きました'
        );
    }

    /**
     * Send meetup/dissolution notification
     */
    public static function sendMeetupDissolutionNotification(int $guestId, string $message, ?int $reservationId = null): ?Notification
    {
        return self::sendNotificationIfEnabled(
            $guestId,
            'guest',
            'meetup_dissolution',
            'meetup_dissolution',
            $message,
            $reservationId
        );
    }

    /**
     * Send auto extension notification
     */
    public static function sendAutoExtensionNotification(int $guestId, string $message, ?int $reservationId = null): ?Notification
    {
        return self::sendNotificationIfEnabled(
            $guestId,
            'guest',
            'auto_extension',
            'auto_extension',
            $message,
            $reservationId
        );
    }

    /**
     * Send tweet like notification
     */
    public static function sendTweetLikeNotification(int $userId, string $userType, string $likerName): ?Notification
    {
        return self::sendNotificationIfEnabled(
            $userId,
            $userType,
            'tweet_likes',
            'tweet_like',
            "{$likerName}さんがあなたのつぶやきをいいねしました"
        );
    }

    /**
     * Send admin notice notification
     */
    public static function sendAdminNoticeNotification(int $userId, string $userType, string $title): ?Notification
    {
        return self::sendNotificationIfEnabled(
            $userId,
            $userType,
            'admin_notices',
            'admin_notice',
            "運営からのお知らせ: {$title}"
        );
    }
} 