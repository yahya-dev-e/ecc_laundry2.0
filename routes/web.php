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

    Route::post('/admin/users/update', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'id' => ['required'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'student_id' => ['required', 'string', 'max:50'],
            'room_number' => ['required', 'string', 'max:50'],
            'role' => ['required', 'in:admin,student'],
            'weeklyLimit' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = \App\Models\User::find($validated['id']);
        if ($user) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'student_id' => $validated['student_id'],
                'room_number' => $validated['room_number'],
                'role' => $validated['role'],
            ]);
            if (isset($validated['weeklyLimit']) && \App\Models\User::hasCreditsColumn()) {
                $user->credits = $validated['weeklyLimit'];
                $user->save();
            }
        }

        return redirect()->route('admin.users')->with('success', "L'utilisateur {$request->name} a été mis à jour avec succès.");
    })->name('admin.users.update');

    Route::get('/reset-user-quota', function (\Illuminate\Http\Request $request) {
        $user = \App\Models\User::find($request->query('id'));
        if ($user && \App\Models\User::hasCreditsColumn()) {
            $user->credits = $user->weeklyLimit();
            $user->save();
        }
        return redirect()->route('admin.users')->with('success', "Le quota a été réinitialisé avec succès.");
    });

    // Sections retirées (Réservations, Machines, Réclamations, Paramètres) -> Redirection vers Tableau de bord
    Route::get('/reservations', fn() => redirect()->route('dashboard'))->name('bookings.index');
    Route::get('/bookings', fn() => redirect()->route('dashboard'));
    Route::get('/machines', fn() => redirect()->route('dashboard'))->name('machines.index');
    Route::get('/reclamations', fn() => redirect()->route('dashboard'))->name('complaints.index');
    Route::get('/parametres', fn() => redirect()->route('dashboard'))->name('admin.settings');
});
