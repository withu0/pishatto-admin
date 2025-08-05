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
use App\Http\Controllers\Admin\IdentityVerificationController;
use App\Http\Controllers\Admin\ConciergeController;
use App\Http\Controllers\Admin\ReceiptsController;

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

    // Concierge Management routes
    Route::resource('admin/concierge', ConciergeController::class, ['as' => 'admin']);
    Route::put('admin/concierge/{concierge}/status', [ConciergeController::class, 'updateStatus'])->name('admin.concierge.update-status');
    Route::put('admin/concierge/{concierge}/assign', [ConciergeController::class, 'assignAdmin'])->name('admin.concierge.assign');
    Route::get('admin/concierge/statistics', [ConciergeController::class, 'statistics'])->name('admin.concierge.statistics');

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
    Route::get('admin/sales', [App\Http\Controllers\Admin\SalesController::class, 'index'])->name('admin.sales');
    Route::post('admin/sales', [App\Http\Controllers\Admin\SalesController::class, 'store'])->name('admin.sales.store');
    Route::put('admin/sales/{payment}', [App\Http\Controllers\Admin\SalesController::class, 'update'])->name('admin.sales.update');
    Route::delete('admin/sales/{payment}', [App\Http\Controllers\Admin\SalesController::class, 'destroy'])->name('admin.sales.destroy');
    Route::get('admin/sales/guests', [App\Http\Controllers\Admin\SalesController::class, 'getGuests'])->name('admin.sales.guests');
    Route::get('admin/receipts', [ReceiptsController::class, 'index'])->name('admin.receipts');
    Route::get('admin/payments', [AdminController::class, 'payments'])->name('admin.payments');
    Route::resource('admin/payment-details', App\Http\Controllers\Admin\PaymentDetailController::class, ['as' => 'admin']);
    Route::get('admin/notifications', [App\Http\Controllers\Admin\AdminNewsController::class, 'index'])->name('admin.notifications');
    
    // Reservation Applications Management
    Route::get('admin/reservation-applications', [AdminController::class, 'reservationApplications'])->name('admin.reservation-applications');
    Route::post('admin/reservation-applications/{applicationId}/approve', [AdminController::class, 'approveApplication'])->name('admin.reservation-applications.approve');
    Route::post('admin/reservation-applications/{applicationId}/reject', [AdminController::class, 'rejectApplication'])->name('admin.reservation-applications.reject');
    Route::post('admin/reservation-applications/multi-approve', [AdminController::class, 'approveMultipleApplications'])->name('admin.reservation-applications.multi-approve');
    
    // Identity Verification Management
    Route::get('admin/identity-verifications', [IdentityVerificationController::class, 'index'])->name('admin.identity-verifications');
    Route::post('admin/identity-verifications/{guestId}/approve', [IdentityVerificationController::class, 'approve'])->name('admin.identity-verifications.approve');
    Route::post('admin/identity-verifications/{guestId}/reject', [IdentityVerificationController::class, 'reject'])->name('admin.identity-verifications.reject');
    Route::get('admin/identity-verifications/stats', [IdentityVerificationController::class, 'stats'])->name('admin.identity-verifications.stats');
    Route::get('admin/identity-verifications/debug', [IdentityVerificationController::class, 'debug'])->name('admin.identity-verifications.debug');
    Route::get('admin/identity-verifications/test-storage', [IdentityVerificationController::class, 'testStorage'])->name('admin.identity-verifications.test-storage');
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
