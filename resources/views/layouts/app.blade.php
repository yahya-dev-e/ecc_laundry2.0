<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Plateforme de gestion et réservation de la buanderie étudiante de l'École Centrale Casablanca.">

    <title>PanneauAdmin - Centrale Casablanca Laundry</title>

    <!-- Google Fonts Optimized (Non-blocking with system font fallback) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@500;600;700&display=swap" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@500;600;700&display=swap">
    </noscript>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ mobileMenuOpen: false }" class="min-h-screen bg-[#f4f7f6] text-slate-800 flex font-sans antialiased">

    <!-- Sidebar Navigation (Desktop sidebar & Mobile slide-over drawer) -->
    @include('layouts.navigation')

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto w-full">
        
        <!-- Top Navbar -->
        <header class="h-14 bg-white border-b border-slate-200 px-3 sm:px-6 flex items-center justify-between shrink-0 sticky top-0 z-30 shadow-xs">
            <div class="flex items-center space-x-2 sm:space-x-4">
                <!-- Mobile Hamburger Toggle Button -->
                <button type="button" 
                        @click="mobileMenuOpen = true" 
                        class="lg:hidden p-1.5 -ml-1 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#00796b]" 
                        aria-label="Ouvrir le menu de navigation">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <span class="text-xs text-slate-400 font-medium hidden xl:inline">laundry.fecc.ma</span>
                <span class="text-slate-300 text-xs hidden xl:inline">•</span>

                <!-- Server Time Indicator (Adaptive on Mobile) -->
                <div id="server-time-indicator"
                     x-data="serverClock('{{ now()->toIso8601String() }}', '{{ config('app.timezone', 'UTC') }}')" 
                     class="flex items-center space-x-1.5 sm:space-x-2 px-2.5 sm:px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-xs font-medium text-slate-700 shadow-2xs select-none shrink-0"
                     title="Heure actuelle du serveur ({{ config('app.timezone', 'UTC') }})">
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-[11px] font-semibold text-slate-500 whitespace-nowrap hidden sm:inline">Serveur :</span>
                    <span class="font-mono font-bold text-slate-800 tracking-tight whitespace-nowrap" x-text="timeFormatted">
                        {{ now()->format('H:i:s') }}
                    </span>
                    <span class="text-[9px] font-bold text-slate-500 bg-white px-1.5 py-0.5 rounded border border-slate-200 hidden md:inline">
                        {{ config('app.timezone', 'UTC') }}
                    </span>
                </div>
            </div>

            <div class="flex items-center space-x-2 sm:space-x-4">
                @auth
                    @php
                        $navIsAdmin = auth()->user()->isAdmin();
                        $navLimit = auth()->user()->weeklyLimit();
                        $navRemaining = auth()->user()->weeklyRemainingLimit();
                        $navUsed = max(0, $navLimit - $navRemaining);
                    @endphp
                    <!-- Quota Indicator (Adaptive on Mobile) -->
                    <div class="flex items-center space-x-1.5 sm:space-x-2 px-2.5 sm:px-3 py-1 rounded-full {{ $navIsAdmin ? 'bg-amber-50 text-amber-900 border border-amber-200' : ($navRemaining > 0 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200') }} text-xs font-semibold">
                        <span class="w-2 h-2 rounded-full shrink-0 {{ $navIsAdmin ? 'bg-amber-500' : ($navRemaining > 0 ? 'bg-emerald-500' : 'bg-rose-500') }}"></span>
                        <span class="hidden sm:inline">
                            {{ $navIsAdmin ? "Quota Admin : {$navUsed} / 100 crédits ({$navRemaining} restants)" : "Quota : {$navUsed} / 8 crédits ({$navRemaining} restants)" }}
                        </span>
                        <span class="inline sm:hidden font-mono font-bold">
                            {{ $navIsAdmin ? "{$navRemaining}/100 cr." : "{$navRemaining}/8 cr." }}
                        </span>
                    </div>
                @endauth

                <!-- French Flag Badge (no emoji) -->
                <div class="hidden xs:flex items-center space-x-1 cursor-pointer">
                    <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">FR</span>
                </div>

                <!-- User Profile & Disconnect -->
                <div class="flex items-center space-x-2">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-slate-200 border border-slate-300 flex items-center justify-center overflow-hidden shrink-0">
                        <svg aria-hidden="true" class="w-4 h-4 sm:w-5 sm:h-5 text-slate-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 hidden md:inline truncate max-w-[100px]">
                        {{ auth()->check() ? auth()->user()->name : 'R. Omari' }}
                    </span>
                </div>

                <!-- Disconnect Button in Header -->
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="flex items-center space-x-1 px-2.5 py-1 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 text-xs font-medium transition-all shadow-2xs" title="Se déconnecter">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span class="hidden sm:inline">Déconnexion</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Alerts -->
        @if (session('success'))
            <div role="status" aria-live="polite" class="mx-3 sm:mx-6 mt-3 sm:mt-4 p-3.5 rounded bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-xs flex justify-between items-center shadow-xs">
                <span>{{ session('success') }}</span>
                <button type="button" aria-label="Fermer la notification" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 font-bold focus-visible:ring-2 focus-visible:ring-emerald-500 rounded p-1">&times;</button>
            </div>
        @endif

        @if (session('error'))
            <div role="alert" aria-live="assertive" class="mx-3 sm:mx-6 mt-3 sm:mt-4 p-3.5 rounded bg-rose-50 border-l-4 border-rose-500 text-rose-800 text-xs flex justify-between items-center shadow-xs">
                <span>{{ session('error') }}</span>
                <button type="button" aria-label="Fermer la notification" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 font-bold focus-visible:ring-2 focus-visible:ring-rose-500 rounded p-1">&times;</button>
            </div>
        @endif

        <!-- Page View Body -->
        <main class="p-3 sm:p-6 md:p-8 flex-1 w-full max-w-full overflow-x-hidden">
            @yield('content')
        </main>

    </div>

    @stack('scripts')
</body>
</html>
