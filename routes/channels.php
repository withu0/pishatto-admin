<?php

use Illuminate\Support\Facades\Broadcast;

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
    // $chat = \App\Models\Chat::find($chatId);
    // if (!$chat) return false;

    // // If user is a Guest
    // if ($user instanceof \App\Models\Guest && $chat->guest_id === $user->id) {
    //     return true;
    // }
    // // If user is a Cast
    // if ($user instanceof \App\Models\Cast && $chat->cast_id === $user->id) {
    //     return true;
    // }
    // return false;
    return true;
});


Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
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
