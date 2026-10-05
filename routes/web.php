<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MachineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Centrale Casablanca Laundry Portal
|--------------------------------------------------------------------------
*/

// Root redirects to login by default if unauthenticated
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Password Reset Routes
    Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

    // Tableau de bord & Calendrier des réservations
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/calendrier', [DashboardController::class, 'calendar'])->name('calendrier');
    Route::get('/admin/reservation/calendrier', [DashboardController::class, 'calendar']);

    // Réservations & Création
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::get('/reserver', [BookingController::class, 'create']);
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('/reserver', [BookingController::class, 'store']);
    Route::post('/bookings/{booking}/start', [BookingController::class, 'start'])->name('bookings.start');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    // Gestion des Utilisateurs (Admin)
    Route::get('/utilisateurs', function () {
        $users = \App\Models\User::orderBy('name')->get();
        return view('admin.users', compact('users'));
    })->name('admin.users');

    // Sections retirées (Réservations, Machines, Réclamations, Paramètres) -> Redirection vers Tableau de bord
    Route::get('/reservations', fn() => redirect()->route('dashboard'))->name('bookings.index');
    Route::get('/bookings', fn() => redirect()->route('dashboard'));
    Route::get('/machines', fn() => redirect()->route('dashboard'))->name('machines.index');
    Route::get('/reclamations', fn() => redirect()->route('dashboard'))->name('complaints.index');
    Route::get('/parametres', fn() => redirect()->route('dashboard'))->name('admin.settings');
});
