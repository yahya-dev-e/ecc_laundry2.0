<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Plateforme de gestion et réservation de la buanderie étudiante de l'École Centrale Casablanca.">

    <title>PanneauAdmin - Centrale Casablanca Laundry</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f4f7f6] text-slate-800 flex font-sans antialiased">

    <!-- Sidebar Navigation -->
    @include('layouts.navigation')

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        
        <!-- Top Navbar -->
        <header class="h-14 bg-white border-b border-slate-200 px-6 flex items-center justify-between shrink-0 sticky top-0 z-30 shadow-xs">
            <div class="flex items-center space-x-3">
                <span class="text-xs text-slate-400 font-medium">laundry.fecc.ma</span>
            </div>

            <div class="flex items-center space-x-5">
                @auth
                    @php
                        $navIsAdmin = auth()->user()->isAdmin();
                        $navLimit = auth()->user()->weeklyLimit();
                        $navRemaining = auth()->user()->weeklyRemainingLimit();
                        $navUsed = max(0, $navLimit - $navRemaining);
                    @endphp
                    <div class="flex items-center space-x-2 px-3 py-1 rounded-full {{ $navIsAdmin ? 'bg-amber-50 text-amber-900 border border-amber-200' : ($navRemaining > 0 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200') }} text-xs font-semibold">
                        <span class="w-2 h-2 rounded-full {{ $navIsAdmin ? 'bg-amber-500' : ($navRemaining > 0 ? 'bg-emerald-500' : 'bg-rose-500') }}"></span>
                        <span>
                            {{ $navIsAdmin ? "Quota Admin : {$navUsed} / 100 crédits ({$navRemaining} restants)" : "Quota : {$navUsed} / 8 crédits ({$navRemaining} restants)" }}
                        </span>
                    </div>
                @endauth

                <!-- French Flag Badge (no emoji) -->
                <div class="flex items-center space-x-1 cursor-pointer">
                    <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">FR</span>
                </div>

                <!-- User Profile -->
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-full bg-slate-200 border border-slate-300 flex items-center justify-center overflow-hidden">
                        <svg aria-hidden="true" class="w-5 h-5 text-slate-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-slate-700">
                        {{ auth()->check() ? auth()->user()->name : 'R. Omari' }}
                    </span>
                </div>
            </div>
        </header>

        <!-- Alerts -->
        @if (session('success'))
            <div role="status" aria-live="polite" class="mx-6 mt-4 p-3.5 rounded bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-xs flex justify-between items-center shadow-xs">
                <span>{{ session('success') }}</span>
                <button type="button" aria-label="Fermer la notification" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 font-bold focus-visible:ring-2 focus-visible:ring-emerald-500 rounded p-1">&times;</button>
            </div>
        @endif

        <!-- Page View Body -->
        <main class="p-6 md:p-8 flex-1">
            @yield('content')
        </main>

    </div>

    @stack('scripts')
</body>
</html>
