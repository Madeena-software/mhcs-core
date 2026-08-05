<?php

use App\Http\Controllers\Member\AuthenticationController;
use App\Http\Controllers\Member\DashboardController;
use App\Http\Controllers\Member\Mvp03BookingController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Operator\PortalController as OperatorPortalController;
use App\Http\Middleware\EnsureMemberPortalAccess;
use App\Http\Middleware\EnsureOperatorPortalAccess;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', [AuthenticationController::class, 'showLogin'])
    ->middleware('guest')
    ->name('login');
Route::post('/login', [AuthenticationController::class, 'store'])
    ->middleware('guest')
    ->name('login.store');

Route::get('/password/change-required', [AuthenticationController::class, 'showPasswordChange'])
    ->middleware('auth')
    ->name('password.change-required');
Route::post('/password/change-required', [AuthenticationController::class, 'updatePassword'])
    ->middleware('auth')
    ->name('password.change-required.update');

Route::middleware(['auth', EnsureMemberPortalAccess::class])->group(function (): void {
    Route::get('/member/profile', [ProfileController::class, 'edit'])->name('member.profile');
    Route::patch('/member/profile', [ProfileController::class, 'update'])->name('member.profile.update');
    Route::get('/member/dashboard', [DashboardController::class, 'show'])->name('member.dashboard');
    Route::get('/member/services', [Mvp03BookingController::class, 'services'])->name('member.services');
    Route::get('/member/services/{service}', [Mvp03BookingController::class, 'service'])->name('member.services.show');
    Route::get('/member/schedules', [Mvp03BookingController::class, 'schedules'])->name('member.schedules');
    Route::post('/member/bookings', [Mvp03BookingController::class, 'store'])->name('member.bookings.store');
    Route::get('/member/bookings', [Mvp03BookingController::class, 'index'])->name('member.bookings');
    Route::get('/member/bookings/{booking}', [Mvp03BookingController::class, 'show'])->name('member.bookings.show');
});

Route::middleware(['auth', EnsureOperatorPortalAccess::class])->group(function (): void {
    Route::get('/operator', [OperatorPortalController::class, 'dashboard'])->name('operator.dashboard');
    Route::get('/operator/site', [OperatorPortalController::class, 'site'])->name('operator.site');
    Route::post('/operator/site', [OperatorPortalController::class, 'selectSite'])->name('operator.site.select');
    Route::get('/operator/eligible-shifts', [OperatorPortalController::class, 'eligible'])->name('operator.eligible-shifts');
    Route::get('/operator/attendance/{schedule}', [OperatorPortalController::class, 'attendance'])->name('operator.attendance');
    Route::post('/operator/arrivals', [OperatorPortalController::class, 'recordArrival'])->name('operator.arrivals.store');
    Route::get('/operator/verification-worklist', [OperatorPortalController::class, 'worklist'])->name('operator.verification-worklist');
});

Route::post('/logout', [AuthenticationController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');
