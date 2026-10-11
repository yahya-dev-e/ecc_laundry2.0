<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MachineController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:signup');
    Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->middleware('throttle:reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:reset')->name('password.update');
});

Route::middleware(['auth', 'session.version'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/calendrier', [DashboardController::class, 'calendar'])->name('calendrier');
    Route::get('/calendar-revision', [DashboardController::class, 'revision'])->name('calendar.revision');
    Route::get('/admin/reservation/calendrier', [DashboardController::class, 'calendar'])->middleware('admin');
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::get('/reserver', [BookingController::class, 'create']);
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('/reserver', [BookingController::class, 'store']);
    Route::post('/bookings/{booking}/start', [BookingController::class, 'start'])->name('bookings.start');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');
    Route::post('/bookings/{booking}/delete', [BookingController::class, 'destroy']);
    Route::get('/reservations', fn () => redirect()->route('dashboard'))->name('bookings.index');
    Route::get('/bookings', fn () => redirect()->route('dashboard'));
    Route::get('/machines/{machine}/slots', [MachineController::class, 'slots'])->name('machines.slots');
    Route::get('/machines', [MachineController::class, 'index'])->middleware('admin')->name('machines.index');
    Route::patch('/machines/{machine}/status', [MachineController::class, 'updateStatus'])->middleware('admin')->name('machines.update-status');

    Route::middleware('admin')->group(function () {
        Route::get('/utilisateurs', [UserController::class, 'index'])->name('admin.users');
        Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::post('/admin/users/update', [UserController::class, 'update'])->name('admin.users.update');
        Route::post('/admin/users/{user}/reset-quota', [UserController::class, 'resetQuota'])->name('admin.users.reset-quota');
    });
});
