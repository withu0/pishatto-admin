<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\GuestAuthController;
use App\Http\Controllers\Auth\CastAuthController;
use App\Http\Controllers\Admin\CastController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('admin/guests', fn() => Inertia::render('admin/guests'))->name('admin.guests');

    // Cast CRUD routes
    Route::resource('admin/casts', CastController::class, ['as' => 'admin']);
    Route::post('admin/casts/upload-avatar', [CastController::class, 'uploadAvatar'])->name('admin.casts.upload-avatar');
    Route::delete('admin/casts/{cast}/avatar', [CastController::class, 'deleteAvatar'])->name('admin.casts.delete-avatar');

    Route::get('admin/matching-select', fn() => Inertia::render('admin/matching-select'))->name('admin.matching-select');
    Route::get('admin/matching-manage', fn() => Inertia::render('admin/matching-manage'))->name('admin.matching-manage');
    Route::get('admin/messages', fn() => Inertia::render('admin/messages'))->name('admin.messages');
    Route::get('admin/gifts', fn() => Inertia::render('admin/gifts'))->name('admin.gifts');
    Route::get('admin/ranking', fn() => Inertia::render('admin/ranking'))->name('admin.ranking');
    Route::get('admin/tweets', fn() => Inertia::render('admin/tweets'))->name('admin.tweets');
    Route::get('admin/sales', fn() => Inertia::render('admin/sales'))->name('admin.sales');
    Route::get('admin/receipts', fn() => Inertia::render('admin/receipts'))->name('admin.receipts');
    Route::get('admin/payments', fn() => Inertia::render('admin/payments'))->name('admin.payments');
    Route::get('admin/payment-details', fn() => Inertia::render('admin/payment-details'))->name('admin.payment-details');
    Route::get('admin/notifications', fn() => Inertia::render('admin/notifications'))->name('admin.notifications');
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
