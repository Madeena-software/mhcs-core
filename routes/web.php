<?php

use App\Http\Controllers\Member\AuthenticationController;
use App\Http\Controllers\Member\DashboardController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Middleware\EnsureMemberPortalAccess;
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
});

Route::post('/logout', [AuthenticationController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');
