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

// Private chat channel (only allow users who can access the chat)
Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    // Replace with your own logic, e.g.:
    // return $user->chats()->where('id', $chatId)->exists();
    return true; // Allow all for now (for testing)
});

// Private user notification channel (only the user themselves)
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
