@extends('layouts.auth')
@section('title', 'Créer un compte')
@section('content')
<h1 class="text-2xl font-bold text-slate-900">Créer un compte étudiant</h1>
<p class="mt-2 mb-6 text-sm text-slate-600">{{ config('laundry.weekly_hours_limit', 8) }} heures de réservation par semaine.</p>
<form method="POST" action="{{ route('register') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
    @csrf
    <x-form-field name="name" label="Nom complet" autocomplete="name" maxlength="255" />
    <x-form-field name="email" label="Adresse e-mail" type="email" autocomplete="email" autocapitalize="none" spellcheck="false" maxlength="255" />
    <x-form-field name="student_id" label="Numéro étudiant" maxlength="50" />
    <x-form-field name="room_number" label="Chambre" maxlength="50" />
    <x-form-field name="password" label="Mot de passe (8 caractères minimum)" type="password" autocomplete="new-password" minlength="8" />
    <x-form-field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" minlength="8" />
    <button type="submit" :disabled="submitting" class="btn-primary w-full" x-text="submitting ? 'Création en cours…' : 'Créer mon compte'">Créer mon compte</button>
</form>
<p class="mt-6 text-sm text-slate-600">Déjà inscrit ? <a href="{{ route('login') }}" class="text-[#00796b] font-semibold underline">Se connecter</a></p>
@endsection
