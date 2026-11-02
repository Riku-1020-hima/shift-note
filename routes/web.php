<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TimeSlotController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

// スタッフ用(ログイン不要。一人ひとり専用のURL)
Route::get('/s/{token}', [PortalController::class, 'show'])->name('portal.show');
Route::post('/s/{token}', [PortalController::class, 'update'])->name('portal.update')->middleware('throttle:30,1');
Route::post('/s/{token}/leave', [PortalController::class, 'requestLeave'])->name('portal.leave')->middleware('throttle:20,1');
Route::post('/s/{token}/leave/{leave}/withdraw', [PortalController::class, 'withdrawLeave'])->name('portal.leave.withdraw')->middleware('throttle:20,1');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/table', [ShiftController::class, 'table'])->name('shifts.table');
    Route::post('/shifts/generate', [ShiftController::class, 'generate'])->name('shifts.generate');
    Route::post('/shifts/publish', [ShiftController::class, 'publish'])->name('shifts.publish');
    Route::put('/shifts/deadline', [ShiftController::class, 'deadline'])->name('shifts.deadline');
    Route::get('/shifts/day/{date}', [ShiftController::class, 'editDay'])->name('shifts.day');
    Route::put('/shifts/day/{date}', [ShiftController::class, 'updateDay'])->name('shifts.day.update');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');
    Route::post('/leaves/{leave}/revoke', [LeaveController::class, 'revoke'])->name('leaves.revoke');

    Route::post('/staff/{staff}/token', [StaffController::class, 'regenerateToken'])->name('staff.token');
    Route::resource('staff', StaffController::class)->except('show');
    Route::resource('slots', TimeSlotController::class)->except('show')->parameters(['slots' => 'slot']);
});
