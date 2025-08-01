<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\GuestAuthController;
use App\Http\Controllers\Auth\CastAuthController;
use App\Http\Controllers\Admin\CastController;
use App\Http\Controllers\Admin\GuestController;
use App\Http\Controllers\Admin\BadgeController;
use App\Http\Controllers\Admin\GiftController;
use App\Http\Controllers\Admin\AdminNewsController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\MessagesController;
use App\Http\Controllers\Admin\ChatController;

use App\Http\Controllers\AdminController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Guest CRUD routes
    Route::resource('admin/guests', GuestController::class, ['as' => 'admin']);

    // Cast CRUD routes
    Route::resource('admin/casts', CastController::class, ['as' => 'admin']);
    Route::post('admin/casts/upload-avatar', [CastController::class, 'uploadAvatar'])->name('admin.casts.upload-avatar');
    Route::delete('admin/casts/{cast}/avatar', [CastController::class, 'deleteAvatar'])->name('admin.casts.delete-avatar');

    // Badge CRUD routes
    Route::resource('admin/badges', BadgeController::class, ['as' => 'admin']);

    // Gift CRUD routes
    Route::resource('admin/gifts', GiftController::class, ['as' => 'admin']);

    // AdminNews CRUD routes
    Route::resource('admin/news', AdminNewsController::class, ['as' => 'admin']);
    Route::post('admin/news/{news}/publish', [AdminNewsController::class, 'publish'])->name('admin.news.publish');

    // Location CRUD routes
    Route::resource('admin/locations', LocationController::class, ['as' => 'admin']);
    Route::get('admin/locations-api/active', [LocationController::class, 'getActiveLocations'])->name('admin.locations.active');


    Route::get('admin/matching-manage', [ChatController::class, 'index'])->name('admin.matching-manage');
    Route::get('admin/chats/{id}', [ChatController::class, 'show'])->name('admin.chats.show');
    Route::put('admin/chats/{id}', [ChatController::class, 'update'])->name('admin.chats.update');
    Route::delete('admin/chats/{id}', [ChatController::class, 'destroy'])->name('admin.chats.destroy');
    Route::delete('admin/chats/{chatId}/messages/{messageId}', [ChatController::class, 'deleteMessage'])->name('admin.chats.messages.destroy');
    Route::get('admin/messages', [MessagesController::class, 'index'])->name('admin.messages');
    Route::get('test-messages', [MessagesController::class, 'getMessagesData'])->name('test.messages');
    Route::post('admin/messages', [MessagesController::class, 'store'])->name('admin.messages.store');
    Route::get('admin/messages/{id}', [MessagesController::class, 'show'])->name('admin.messages.show');
    Route::put('admin/messages/{id}', [MessagesController::class, 'update'])->name('admin.messages.update');
    Route::post('admin/messages/{id}', [MessagesController::class, 'update'])->name('admin.messages.update.post');
    Route::delete('admin/messages/{id}', [MessagesController::class, 'destroy'])->name('admin.messages.destroy');
    Route::get('admin/ranking', fn() => Inertia::render('admin/ranking'))->name('admin.ranking');
    Route::resource('admin/tweets', App\Http\Controllers\Admin\TweetsController::class, ['as' => 'admin']);
    Route::get('admin/sales', fn() => Inertia::render('admin/sales'))->name('admin.sales');
    Route::get('admin/receipts', fn() => Inertia::render('admin/receipts'))->name('admin.receipts');
    Route::get('admin/payments', fn() => Inertia::render('admin/payments'))->name('admin.payments');
    Route::get('admin/payment-details', fn() => Inertia::render('admin/payment-details'))->name('admin.payment-details');
    Route::get('admin/notifications', [App\Http\Controllers\Admin\AdminNewsController::class, 'index'])->name('admin.notifications');
    
    // Reservation Applications Management
    Route::get('admin/reservation-applications', [AdminController::class, 'reservationApplications'])->name('admin.reservation-applications');
    Route::post('admin/reservation-applications/{applicationId}/approve', [AdminController::class, 'approveApplication'])->name('admin.reservation-applications.approve');
    Route::post('admin/reservation-applications/{applicationId}/reject', [AdminController::class, 'rejectApplication'])->name('admin.reservation-applications.reject');
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
