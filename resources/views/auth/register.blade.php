@extends('layouts.app')

@section('title', 'Student Registration')

@section('content')
<div class="max-w-md mx-auto my-8">
    <div class="glass-card p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-black text-white tracking-tight">Create Student Account</h1>
            <p class="text-xs text-slate-400 mt-1">Get 10 free starting laundry credits on signup</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                    Full Name
                </label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required
                       class="glass-input w-full text-sm" placeholder="e.g. Jordan Miller">
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                    Campus Email
                </label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                       class="glass-input w-full text-sm" placeholder="student@ecc.edu">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="student_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                        Student ID
                    </label>
                    <input type="text" id="student_id" name="student_id" value="{{ old('student_id') }}" required
                           class="glass-input w-full text-sm" placeholder="STU-12345">
                </div>
                <div>
                    <label for="room_number" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                        Dorm Room
                    </label>
                    <input type="text" id="room_number" name="room_number" value="{{ old('room_number') }}" required
                           class="glass-input w-full text-sm" placeholder="Hall 2, 204">
                </div>
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                    Password
                </label>
                <input type="password" id="password" name="password" required
                       class="glass-input w-full text-sm" placeholder="Min. 8 characters">
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">
                    Confirm Password
                </label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       class="glass-input w-full text-sm" placeholder="Re-type password">
            </div>

            <button type="submit" class="btn-primary w-full text-sm !py-3">
                Create Account & Claim Credits
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-800 text-center text-xs text-slate-400">
            Already registered? 
            <a href="{{ route('login') }}" class="text-cyan-400 font-semibold hover:underline">
                Sign In
            </a>
        </div>
    </div>
</div>
@endsection
