<?php

use App\Http\Controllers\Auth\AuthController;
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
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Tableau de bord & Calendrier des réservations
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/calendrier', [DashboardController::class, 'index'])->name('calendrier');
    Route::get('/admin/reservation/calendrier', [DashboardController::class, 'index']);

    // Réservations
    Route::get('/reservations', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('/bookings/{booking}/start', [BookingController::class, 'start'])->name('bookings.start');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    // Machines
    Route::get('/machines', function () {
        return view('machines.index');
    })->name('machines.index');
    Route::get('/machines/{machine}', [MachineController::class, 'show'])->name('machines.show');
    Route::get('/machines/{machine}/slots', [MachineController::class, 'slots'])->name('machines.slots');
    Route::patch('/machines/{machine}/status', [MachineController::class, 'updateStatus'])->name('machines.update-status');

    // Réclamations (Available to both Admin and Students)
    Route::get('/reclamations', function () {
        return view('complaints.index');
    })->name('complaints.index');

    // ADMIN ONLY ROUTES
    Route::get('/utilisateurs', function () {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Accès réservé aux administrateurs.');
        }
        return view('admin.users');
    })->name('admin.users');

    Route::get('/parametres', function () {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Accès réservé aux administrateurs.');
        }
        return view('admin.settings');
    })->name('admin.settings');
});
