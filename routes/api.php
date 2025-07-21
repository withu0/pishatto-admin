<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GuestAuthController;
use App\Http\Controllers\Auth\CastAuthController;
use App\Http\Controllers\ChatController;

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
Route::get('/chats/{userType}/{userId}', [GuestAuthController::class, 'getUserChats']);
Route::get('/chats/all', [GuestAuthController::class, 'allChats']);
Route::get('/chats', [ChatController::class, 'index']);
Route::post('/messages', [ChatController::class, 'store']);
Route::get('/reservations/{id}', [GuestAuthController::class, 'getReservationById']);
Route::get('/chats/{chatId}/messages', [ChatController::class, 'messages']);
Route::get('/guests/repeat', [GuestAuthController::class, 'repeatGuests']);
Route::get('/guest/profile/id/{id}', [GuestAuthController::class, 'getProfileById']);

// Avatar serving route
Route::get('/avatars/{filename}', [GuestAuthController::class, 'getAvatar']); 