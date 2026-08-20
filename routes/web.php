<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RsvpLinkController;
use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use App\Http\Controllers\PublicRsvpController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'admin.dashboard' : 'admin.login');
});

Route::get('rsvp/{rsvpLink}', [PublicRsvpController::class, 'show'])
    ->middleware('throttle:public-rsvp')
    ->name('rsvp.show');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::middleware(['auth', 'active', 'secure.mutations', 'idempotent.admin'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('rsvp-links', [RsvpLinkController::class, 'store'])->name('rsvp-links.store');
        Route::match(['put', 'patch'], 'rsvp-links/{rsvpLink}', [RsvpLinkController::class, 'update'])->name('rsvp-links.update');
        Route::delete('rsvp-links/{rsvpLink}', [RsvpLinkController::class, 'destroy'])->name('rsvp-links.destroy');
        Route::delete('logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});
