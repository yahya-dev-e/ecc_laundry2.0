<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SafePassword;
use App\Support\AccountInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private function writeAccount(\Closure $operation): mixed
    {
        try {
            return DB::transaction($operation, 5);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['email' => 'Cette adresse e-mail ou ce numéro étudiant est déjà utilisé. Vérifiez le compte existant.']);
        }
    }

    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) $request->query('q', ''));
        $users = User::withWeeklyUsage()->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
            $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('student_id', 'like', '%'.$search.'%');
        }))->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.users', compact('users', 'search'));
    }

    public function create()
    {
        return view('admin.user-form');
    }

    public function edit(User $user)
    {
        return view('admin.user-form', compact('user'));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        AccountInput::normalizeEmail($request);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'student_id' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($user)],
            'room_number' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in(['student', 'admin'])],
            'weeklyLimit' => ['required', 'numeric', 'min:0', 'max:200', 'multiple_of:0.5'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'confirmed', Password::min(8), new SafePassword],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->writeAccount(function () use ($data) {
            $user = User::create(collect($data)->only(['name', 'email', 'student_id', 'room_number', 'role', 'password'])->all());
            $user->forceFill(['weekly_limit_minutes' => (int) ($data['weeklyLimit'] * 60)])->save();
        });

        return redirect()->route('admin.users')->with('success', 'Le compte a été créé.');
    }

    public function update(Request $request)
    {
        $request->validate(['id' => ['required', 'integer', 'exists:users,id']]);
        $user = User::findOrFail($request->id);
        $data = $this->validated($request, $user);
        $this->writeAccount(function () use ($user, $data) {
            $administrators = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->isAdmin() && $data['role'] !== 'admin' && $administrators->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Conservez au moins un compte administrateur.']);
            }
            $identityChanged = $user->email !== $data['email'] || $user->role !== $data['role'] || ! empty($data['password']);
            $user->fill(collect($data)->only(['name', 'email', 'student_id', 'room_number', 'role'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            if ($identityChanged) {
                $user->session_version++;
                $user->remember_token = Str::random(60);
                $user->email_verified_at = null;
                DB::table('password_reset_links')->where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            }
            $user->weekly_limit_minutes = (int) ($data['weeklyLimit'] * 60);
            $user->save();
        });
        if ($request->user()->id === $user->id) {
            $request->session()->put('auth_version', $user->fresh()->session_version);
        }

        return redirect()->route('admin.users')->with('success', 'Le compte a été mis à jour avec succès.');
    }

    public function resetQuota(Request $request, User $user)
    {
        DB::transaction(function () use ($user, $request) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->reservations()->occupying()->where('start_time', '>=', now()->startOfWeek())
                ->where('start_time', '<', now()->startOfWeek()->addWeek())->update(['quota_waived' => true]);
            $user->transactions()->create(['amount' => 0, 'type' => 'quota_reset', 'description' => 'Quota réinitialisé par administrateur #'.$request->user()->id]);
        });

        return redirect()->route('admin.users')->with('success', 'Le quota disponible a été réinitialisé pour cette semaine.');
    }
}
