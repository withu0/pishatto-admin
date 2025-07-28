<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GuestAuthController;
use App\Http\Controllers\Auth\CastAuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TweetController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/guest/register', [GuestAuthController::class, 'register']);
Route::post('/guest/login', [GuestAuthController::class, 'login']);
Route::get('/guest/profile/{phone}', [GuestAuthController::class, 'getProfile']);
Route::post('/guest/profile', [GuestAuthController::class, 'updateProfile']);
Route::post('/cast/login', [CastAuthController::class, 'login']);
Route::post('/cast/profile', [CastAuthController::class, 'updateProfile']);
Route::post('/cast/register', [CastAuthController::class, 'register']);
Route::post('/guest/reservation', [GuestAuthController::class, 'createReservation']);
Route::get('/cast/profile/{id}', [CastAuthController::class, 'getProfile']);
Route::get('/guest/reservations/{guest_id}', [GuestAuthController::class, 'listReservations']);
Route::get('/reservations/all', [CastAuthController::class, 'allReservations']);
Route::post('/reservation/match', [GuestAuthController::class, 'matchReservation']);
Route::get('/chats/{chatId}/messages', [ChatController::class, 'messages']);
Route::get('/chats/{userType}/{userId}', [GuestAuthController::class, 'getUserChats']);
Route::get('/chats/all', [GuestAuthController::class, 'allChats']);
Route::get('/chats', [ChatController::class, 'index']);
Route::post('/messages', [ChatController::class, 'store']);
Route::get('/reservations/{id}', [GuestAuthController::class, 'getReservationById']);
Route::put('/reservations/{id}', [GuestAuthController::class, 'updateReservation']);
Route::get('/guests/repeat', [GuestAuthController::class, 'repeatGuests']);
Route::get('/guest/profile/id/{id}', [GuestAuthController::class, 'getProfileById']);
Route::get('/casts', [CastAuthController::class, 'list']);
Route::get('/casts/profile/{id}', [CastAuthController::class, 'getCastProfile']);
Route::get('/casts/points/{id}', [CastAuthController::class, 'getCastPointsData']);
Route::get('/casts/passport/{id}', [CastAuthController::class, 'getCastPassportData']);
Route::post('/casts/like', [CastAuthController::class, 'like']);
Route::get('/casts/liked/{guestId}', [CastAuthController::class, 'likedCasts']);
Route::post('/guests/visit', [CastAuthController::class, 'recordGuestVisit']);
Route::get('/casts/visit-history/{guestId}', [CastAuthController::class, 'visitHistory']);
Route::get('/notifications/{userType}/{userId}', [GuestAuthController::class, 'getNotifications']);
Route::post('/notifications/read/{id}', [GuestAuthController::class, 'markNotificationRead']);
Route::post('/notifications/read-all/{userType}/{userId}', [GuestAuthController::class, 'markAllNotificationsRead']);

// Avatar serving route
Route::get('/avatars/{filename}', [GuestAuthController::class, 'getAvatar']);

// Payment routes
Route::post('/payments/token', [PaymentController::class, 'createToken']);
Route::post('/payments/purchase', [PaymentController::class, 'purchase']);
Route::post('/payments/register-card', [PaymentController::class, 'registerCard']);
Route::post('/payments/info', [PaymentController::class, 'storePaymentInfo']);
Route::get('/payments/info/{userType}/{userId}', [PaymentController::class, 'getPaymentInfo']);
Route::get('/payments/stats/{userType}/{userId}', [PaymentController::class, 'getCustomerStats']);
Route::delete('/payments/info/{userType}/{userId}/{cardId}', [PaymentController::class, 'deletePaymentInfo']);
Route::get('/payments/history/{userType}/{userId}', [PaymentController::class, 'history']);
Route::get('/payments/status/{paymentId}', [PaymentController::class, 'getPaymentStatus']);
Route::post('/payments/{paymentId}/refund', [PaymentController::class, 'refund']);
Route::post('/payments/payout', [PaymentController::class, 'requestPayout']);
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

// Receipt routes
Route::get('/receipts/{userType}/{userId}', [PaymentController::class, 'receipts']);

// Payout routes
Route::post('/payouts/request', [PaymentController::class, 'requestPayout']);

// Tweet routes
Route::get('/tweets/{tweetId}/like-count', [TweetController::class, 'likeCount']);
Route::get('/tweets/{tweetId}/like-status', [TweetController::class, 'likeStatus']);
Route::get('/tweets', [TweetController::class, 'index']);
Route::get('/tweets/{userType}/{userId}', [TweetController::class, 'userTweets']);
Route::post('/tweets', [TweetController::class, 'store']);
Route::delete('/tweets/{id}', [TweetController::class, 'destroy']);
// Tweet like endpoints
Route::post('/tweets/like', [TweetController::class, 'like']);

Route::get('/guests/phones', [GuestAuthController::class, 'allPhones']);
Route::get('/gifts', [ChatController::class, 'gifts']);
// Gift box: received gifts for cast
Route::get('/cast/{castId}/received-gifts', [ChatController::class, 'receivedGifts']);
Route::post('/cast/avatar-upload', [CastAuthController::class, 'uploadAvatar']);
Route::post('/guests/like', [GuestAuthController::class, 'likeGuest']);
Route::post('/chats/create', [ChatController::class, 'createChat']);
Route::get('/guests/like-status/{cast_id}/{guest_id}', [GuestAuthController::class, 'likeStatus']);
Route::get('/ranking', [RankingController::class, 'getRanking']);
Route::post('/ranking/clear-cache', [RankingController::class, 'clearRankingCache']);
Route::get('/chats/{chatId}', [ChatController::class, 'show']);
Route::post('/reservation/start', [CastAuthController::class, 'startReservation']);
Route::post('/reservation/stop', [CastAuthController::class, 'stopReservation']);
Route::post('/casts/favorite', [CastAuthController::class, 'favorite']);
Route::post('/casts/unfavorite', [CastAuthController::class, 'unfavorite']);
Route::get('/casts/favorites/{guestId}', [CastAuthController::class, 'favoriteCasts']);
Route::get('/badges', [GuestAuthController::class, 'getAllBadges']); 