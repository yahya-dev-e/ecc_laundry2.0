<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Rules\SafePassword;
use App\Support\AccountInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function login(Request $request)
    {
        AccountInput::normalizeEmail($request);
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Les identifiants ne correspondent pas à nos données.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->put('auth_version', Auth::user()->session_version);

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request)
    {
        AccountInput::normalizeEmail($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8), new SafePassword],
        ], ['email.unique' => 'Un compte existe déjà avec cette adresse. Connectez-vous ou réinitialisez votre mot de passe.']);
        try {
            $user = DB::transaction(fn () => User::create([
                ...collect($validated)->only(['name', 'email', 'password'])->all(), 'role' => 'student',
            ]));
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'email' => 'Un compte existe déjà avec cette adresse. Connectez-vous ou réinitialisez votre mot de passe.',
            ]);
        }
        $user->refresh();
        try {
            $user->notify((new WelcomeNotification)->afterCommit());
        } catch (\Throwable $e) {
            report($e);
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_version', $user->session_version);

        return redirect()->route('dashboard')->with('success', 'Votre compte a été créé. Un message de bienvenue sera envoyé dès que possible.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
