<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendResetLink;
use App\Models\User;
use App\Rules\SafePassword;
use App\Support\AccountInput;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        AccountInput::normalizeEmail($request);
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        $user = User::where('email', $request->email)->first();
        if ($user) {
            try {
                DB::transaction(function () use ($user) {
                    $token = Str::random(64);
                    $id = DB::table('password_reset_links')->insertGetId([
                        'user_id' => $user->id, 'token_hash' => hash('sha256', $token),
                        'created_at' => now(), 'expires_at' => now()->addMinutes(60),
                    ]);
                    SendResetLink::dispatch($user->id, $id, $token)->afterCommit();
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'Si cette adresse correspond à un compte, vous recevrez un lien de réinitialisation. Vérifiez également vos courriers indésirables.');
    }

    public function showResetForm(Request $request, string $token)
    {
        $request->validate(['email' => ['nullable', 'string', 'email', 'max:255']]);

        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function reset(Request $request)
    {
        AccountInput::normalizeEmail($request);
        $data = $request->validate([
            'email' => ['required', 'email'], 'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'confirmed', Password::min(8), new SafePassword],
        ]);
        $changed = DB::transaction(function () use ($data) {
            $user = User::where('email', $data['email'])->lockForUpdate()->first();
            if (! $user) {
                return false;
            }
            $link = DB::table('password_reset_links')->where('user_id', $user->id)
                ->where('token_hash', hash('sha256', $data['token']))
                ->whereNull('consumed_at')->where('expires_at', '>', now())->lockForUpdate()->first();
            if (! $link) {
                return false;
            }
            $user->forceFill([
                'password' => Hash::make($data['password']), 'remember_token' => Str::random(60),
                'session_version' => $user->session_version + 1,
            ])->save();
            DB::table('password_reset_links')->where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            DB::afterCommit(fn () => event(new PasswordReset($user)));

            return true;
        }, 5);
        if (! $changed) {
            return back()->withErrors(['email' => 'Ce lien est invalide, expiré ou déjà utilisé. Veuillez refaire une demande.']);
        }

        return redirect()->route('login')->with('success', 'Mot de passe réinitialisé. Veuillez vous reconnecter sur vos appareils.');
    }
}
