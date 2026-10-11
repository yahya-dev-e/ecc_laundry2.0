<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buanderie — Centrale Casablanca</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ mobileMenuOpen: false }" class="min-h-screen bg-[#f4f7f6] text-slate-800 flex font-sans">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 z-[100] bg-white px-4 py-3">Aller au contenu</a>
    @include('layouts.navigation')
    <div class="flex-1 flex flex-col min-w-0 w-full">
        <header class="bg-white border-b px-3 sm:px-6 py-3 flex justify-between items-center gap-3 sticky top-0 z-30">
            <button type="button" @click="$el.focus(); mobileMenuOpen = true" aria-label="Ouvrir le menu de navigation" :aria-expanded="mobileMenuOpen" aria-controls="mobile-navigation" class="lg:hidden min-h-[44px] min-w-[44px] p-2 border rounded-lg">☰</button>
            <span class="text-sm font-semibold truncate">{{ auth()->user()?->name ?? 'Buanderie' }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="px-3 py-2 border rounded-lg text-sm">Se déconnecter</button></form>
        </header>
        <main id="main-content" class="p-3 sm:p-6 flex-1" tabindex="-1">
            <noscript><nav aria-label="Navigation sans JavaScript" class="flex flex-wrap gap-4 mb-5">
                <a href="{{ route('dashboard') }}">Tableau de bord</a>
                <a href="{{ route('calendrier') }}">Calendrier</a>
                <a href="{{ route('bookings.create') }}">Réserver</a>
                @if(auth()->user()?->isAdmin())<a href="{{ route('admin.users') }}">Utilisateurs</a><a href="{{ route('machines.index') }}">Machines</a>@endif
            </nav></noscript>
            @include('components.form-feedback')
            @yield('content')
        </main>
    </div>
</body>
</html>
