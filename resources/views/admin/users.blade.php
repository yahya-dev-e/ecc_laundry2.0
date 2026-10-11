@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-wrap gap-3 items-center justify-between">
        <h1 class="text-xl font-bold text-slate-800">Gestion des utilisateurs</h1>
        <a href="{{ route('admin.users.create') }}" class="btn-primary">Ajouter un utilisateur</a>
    </div>
    <form method="GET" class="flex flex-wrap gap-3 items-end bg-white rounded-xl p-4 border border-slate-200">
        <div class="flex-1 min-w-0"><label for="user-search" class="block text-sm font-medium mb-1">Nom, e-mail ou numéro étudiant</label>
        <input id="user-search" name="q" value="{{ $search }}" class="w-full border rounded-lg px-3 py-2" maxlength="255"></div>
        <button class="btn-primary" type="submit">Rechercher</button>
        <a href="{{ route('admin.users') }}" class="px-3 py-2 underline">Effacer</a>
    </form>
    <div class="space-y-3">
        @forelse($users as $person)
        <article class="bg-white rounded-xl p-4 border border-slate-200 flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-bold break-words">{{ $person->name }}</h2>
                <p class="text-sm text-slate-600 break-all">{{ $person->email }}</p>
                <p class="text-sm mt-1">{{ $person->role === 'admin' ? 'Administrateur' : 'Étudiant' }} · {{ $person->weeklyRemainingLimit() }}h disponibles / {{ $person->weeklyLimit() }}h</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a class="btn-primary" href="{{ route('admin.users.edit', $person) }}">Modifier <span class="sr-only">{{ $person->name }}</span></a>
                <form method="POST" action="{{ route('admin.users.reset-quota', $person) }}" onsubmit="return confirm('Réinitialiser le quota disponible cette semaine ?')">
                    @csrf<button class="px-3 py-2 border rounded-lg" type="submit">Réinitialiser le quota <span class="sr-only">{{ $person->name }}</span></button>
                </form>
            </div>
        </article>
        @empty<p class="p-4 bg-white rounded-lg">Aucun utilisateur trouvé.</p>@endforelse
    </div>
    {{ $users->links() }}
</div>
@endsection
