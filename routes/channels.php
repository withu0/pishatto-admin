<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    Log::info('Chat channel authorization attempt', [
        'chat_id' => $chatId,
        'user_type' => get_class($user),
        'user_id' => $user->id ?? 'unknown',
        'timestamp' => now()->toISOString()
    ]);

    $chat = \App\Models\Chat::find($chatId);
    if (!$chat) {
        Log::warning('Chat channel authorization: Chat not found', ['chat_id' => $chatId]);
        return false;
    }

    // If user is a Guest
    if ($user instanceof \App\Models\Guest && $chat->guest_id === $user->id) {
        Log::info('Chat channel authorization: Guest authorized', [
            'chat_id' => $chatId,
            'guest_id' => $user->id
        ]);
        return true;
    }
    // If user is a Cast
    if ($user instanceof \App\Models\Cast && $chat->cast_id === $user->id) {
        Log::info('Chat channel authorization: Cast authorized', [
            'chat_id' => $chatId,
            'cast_id' => $user->id
        ]);
        return true;
    }

    Log::warning('Chat channel authorization: Access denied', [
        'chat_id' => $chatId,
        'user_id' => $user->id ?? 'unknown',
        'user_type' => get_class($user),
        'chat_guest_id' => $chat->guest_id,
        'chat_cast_id' => $chat->cast_id
    ]);
    return false;
});

Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    // Log the authorization attempt
    Log::info('Group channel authorization attempt', [
        'group_id' => $groupId,
        'user_type' => get_class($user),
        'user_id' => $user->id ?? 'unknown',
        'user_data' => $user->toArray() ?? 'no data'
    ]);

    // Check if user is part of this group
    $group = \App\Models\ChatGroup::find($groupId);
    if (!$group) {
        Log::warning('Group channel authorization: Group not found', ['group_id' => $groupId]);
        return false;
    }

    // Check if user is the guest or one of the casts in this group
    $chats = \App\Models\Chat::where('group_id', $groupId)->get();

    foreach ($chats as $chat) {
        if ($user instanceof \App\Models\Guest && $chat->guest_id === $user->id) {
            Log::info('Group channel authorization: Guest authorized', [
                'group_id' => $groupId,
                'guest_id' => $user->id,
                'chat_id' => $chat->id
            ]);
            return true;
        }
        if ($user instanceof \App\Models\Cast && $chat->cast_id === $user->id) {
            Log::info('Group channel authorization: Cast authorized', [
                'group_id' => $groupId,
                'cast_id' => $user->id,
                'chat_id' => $chat->id
            ]);
            return true;
        }
    }

    Log::warning('Group channel authorization: User not found in group', [
        'group_id' => $groupId,
        'user_type' => get_class($user),
        'user_id' => $user->id ?? 'unknown',
        'chats_in_group' => $chats->pluck('id')->toArray()
    ]);
    return false;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    Log::info('User channel authorization attempt', [
        'requested_user_id' => $userId,
        'authenticated_user_id' => $user->id ?? 'unknown',
        'user_type' => get_class($user),
        'authorized' => (int) $user->id === (int) $userId,
        'timestamp' => now()->toISOString()
    ]);

    $authorized = (int) $user->id === (int) $userId;

    if ($authorized) {
        Log::info('User channel authorization: Granted', [
            'user_id' => $userId
        ]);
    } else {
        Log::warning('User channel authorization: Denied', [
            'requested_user_id' => $userId,
            'authenticated_user_id' => $user->id ?? 'unknown'
        ]);
    }

    return $authorized;
});

// Private reservation channel (only allow users who can access the reservation)
Broadcast::channel('reservation.{reservationId}', function ($user, $reservationId) {
    // Replace with your own logic, e.g.:
    // return $user->reservations()->where('id', $reservationId)->exists();
    return true; // Allow all for now (for testing)
});

// Public tweets channel (no authentication required)
Broadcast::channel('tweets', function () {
    return true;
});

// Admin news channels
Broadcast::channel('admin-news', function () {
    return true;
});

Broadcast::channel('admin-news.guest', function () {
    return true;
});

Broadcast::channel('admin-news.cast', function () {
    return true;
});

// Guest channel authorization (strict)
Broadcast::channel('guest.{guestId}', function ($user, $guestId) {
    Log::info('Guest channel authorization attempt', [
        'requested_guest_id' => $guestId,
        'user_type' => get_class($user),
        'user_id' => $user->id ?? 'unknown',
        'is_guest' => $user instanceof \App\Models\Guest,
        'authorized' => $user instanceof \App\Models\Guest && (int) $user->id === (int) $guestId,
        'timestamp' => now()->toISOString()
    ]);

    $authorized = $user instanceof \App\Models\Guest && (int) $user->id === (int) $guestId;

    if ($authorized) {
        Log::info('Guest channel authorization: Granted', [
            'guest_id' => $guestId
        ]);
    } else {
        Log::warning('Guest channel authorization: Denied', [
            'requested_guest_id' => $guestId,
            'user_type' => get_class($user),
            'user_id' => $user->id ?? 'unknown'
        ]);
    }

    return $authorized;
});

// Cast channel authorization (strict)
Broadcast::channel('cast.{castId}', function ($user, $castId) {
    Log::info('Cast channel authorization attempt', [
        'requested_cast_id' => $castId,
        'user_type' => get_class($user),
        'user_id' => $user->id ?? 'unknown',
        'is_cast' => $user instanceof \App\Models\Cast,
        'authorized' => $user instanceof \App\Models\Cast && (int) $user->id === (int) $castId,
        'timestamp' => now()->toISOString()
    ]);

    $authorized = $user instanceof \App\Models\Cast && (int) $user->id === (int) $castId;

    if ($authorized) {
        Log::info('Cast channel authorization: Granted', [
            'cast_id' => $castId
        ]);
    } else {
        Log::warning('Cast channel authorization: Denied', [
            'requested_cast_id' => $castId,
            'user_type' => get_class($user),
            'user_id' => $user->id ?? 'unknown'
        ]);
    }

    return $authorized;
});
