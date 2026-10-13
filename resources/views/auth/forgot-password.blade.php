@extends('layouts.auth')
@section('title', 'Mot de passe oublié')
@section('content')
<h1 class="text-2xl font-bold text-slate-900 mb-3">Mot de passe oublié ?</h1>
<p class="text-sm text-slate-600 mb-6">Saisissez votre adresse e-mail pour recevoir un lien valable 60 minutes.</p>
<form method="POST" action="{{ route('password.email') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true" @pageshow.window="submitting = false">
    @csrf
    <x-form-field name="email" label="Adresse e-mail" type="email" autocomplete="email" autocapitalize="none" spellcheck="false" />
    <button type="submit" class="btn-primary w-full" :disabled="submitting">Envoyer le lien</button>
</form>
<a href="{{ route('login') }}" class="block mt-6 underline text-[#00796b] text-sm">Retour à la connexion</a>
@endsection
