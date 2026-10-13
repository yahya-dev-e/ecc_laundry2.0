@extends('layouts.auth')
@section('title', 'Réinitialiser le mot de passe')
@section('content')
<h1 class="text-2xl font-bold text-slate-900 mb-6">Nouveau mot de passe</h1>
<form method="POST" action="{{ route('password.update') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true" @pageshow.window="submitting = false">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <x-form-field name="email" label="Adresse e-mail" type="email" :value="$email" autocomplete="email" autocapitalize="none" />
    <x-form-field name="password" label="Nouveau mot de passe (8 caractères minimum)" type="password" autocomplete="new-password" minlength="8" />
    <x-form-field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" minlength="8" />
    <button type="submit" class="btn-primary w-full" :disabled="submitting">Réinitialiser le mot de passe</button>
</form>
<a href="{{ route('password.request') }}" class="block mt-6 underline text-[#00796b] text-sm">Demander un nouveau lien</a>
@endsection
