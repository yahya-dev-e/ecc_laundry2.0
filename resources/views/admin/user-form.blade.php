@extends('layouts.app')
@section('content')
@php($person = $user ?? null)
<div class="max-w-xl mx-auto bg-white p-5 sm:p-8 rounded-xl border border-slate-200">
    <h1 class="text-xl font-bold mb-6">{{ $person ? 'Modifier le compte' : 'Ajouter un utilisateur' }}</h1>
    <form method="POST" action="{{ $person ? route('admin.users.update') : route('admin.users.store') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        @if($person)<input type="hidden" name="id" value="{{ $person->id }}">@endif
        <x-form-field name="name" label="Nom complet" :value="$person?->name ?? ''" autocomplete="name" maxlength="255" />
        <x-form-field name="email" label="Adresse e-mail" type="email" :value="$person?->email ?? ''" autocomplete="email" autocapitalize="none" maxlength="255" />
        <x-form-field name="student_id" label="Numéro étudiant" :value="$person?->student_id ?? ''" maxlength="50" />
        <x-form-field name="room_number" label="Chambre" :value="$person?->room_number ?? ''" maxlength="50" />
        <div><label for="role" class="block font-semibold text-sm mb-1">Rôle</label>
            <select id="role" name="role" class="border rounded-lg p-2 w-full">
                @foreach(['student' => 'Étudiant', 'admin' => 'Administrateur'] as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $person?->role ?? 'student') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('role')<p class="text-rose-700 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <x-form-field name="weeklyLimit" label="Quota hebdomadaire (heures)" type="number" :value="$person?->weeklyLimit() ?? config('laundry.weekly_hours_limit', 8)" min="0" max="200" step="0.5" />
        @if($person)<p class="text-sm text-slate-600">{{ $person->weeklyUsedMinutes() / 60 }}h utilisées cette semaine. La réinitialisation du quota se fait depuis la liste.</p>@endif
        <x-form-field name="password" :label="$person ? 'Nouveau mot de passe (facultatif)' : 'Mot de passe (8 caractères minimum)'" type="password" autocomplete="new-password" :required="!$person" minlength="8" />
        <x-form-field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" :required="!$person" minlength="8" />
        <div class="flex flex-wrap gap-3 pt-3">
            <button type="submit" :disabled="submitting" class="btn-primary">Enregistrer les modifications</button>
            <a href="{{ route('admin.users') }}" class="px-4 py-2 underline">Annuler</a>
        </div>
    </form>
</div>
@endsection
