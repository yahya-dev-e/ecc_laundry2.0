<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion - Laundry Centrale Casablanca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen login-bg flex items-center justify-center p-4">

    <!-- Split Login Card -->
    <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row min-h-[520px]">
        
        <!-- Left Side: Form (White) -->
        <div class="w-full md:w-1/2 p-8 md:p-12 flex flex-col justify-center items-center">
            
            <!-- Centrale Casablanca Logo -->
            <div class="flex flex-col items-center mb-8">
                <div class="w-14 h-10 relative flex items-center justify-center">
                    <!-- Stylized C loop wave logo -->
                    <svg aria-hidden="true" viewBox="0 0 100 70" class="w-14 h-10 text-[#00897b]" fill="currentColor">
                        <path d="M 50 10 C 25 10 15 25 15 40 C 15 55 30 65 60 65 C 75 65 85 58 85 58 L 80 50 C 80 50 72 55 60 55 C 38 55 27 47 27 38 C 27 28 35 20 50 20 C 65 20 78 27 82 32 L 88 24 C 82 17 68 10 50 10 Z"/>
                        <path d="M 45 4 C 65 4 80 14 85 20 L 78 26 C 74 21 62 13 45 13 Z" fill="#2e7d32"/>
                    </svg>
                </div>
                <div class="text-center mt-1">
                    <span class="text-base font-bold text-slate-700 tracking-tight block">Centrale</span>
                    <span class="text-[9px] uppercase tracking-widest text-slate-500 font-semibold block -mt-1">Casablanca</span>
                </div>
            </div>

            <!-- Title -->
            <h1 class="text-2xl font-extrabold text-slate-900 mb-6 text-center tracking-tight">
                Se connecter
            </h1>

            @if (session('status'))
                <div class="w-full max-w-xs p-3 mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-start space-x-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('success'))
                <div class="w-full max-w-xs p-3 mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-start space-x-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <!-- Form -->
            <form method="POST" action="{{ route('login') }}" class="w-full max-w-xs space-y-4">
                @csrf

                <div>
                    <input type="email" id="email" name="email" value="{{ old('email', 'admin@fecc.ma') }}" placeholder="Email" autocomplete="email" aria-label="Adresse email" required autofocus
                           class="w-full px-4 py-3 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00b4a7]/40 transition-all">
                </div>

                <div class="relative" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" id="password" name="password" value="admin123" placeholder="Mot de passe" autocomplete="current-password" aria-label="Mot de passe" required
                           class="w-full px-4 py-3 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00b4a7]/40 transition-all pr-10">
                    <button type="button" @click="show = !show" aria-label="Afficher ou masquer le mot de passe" class="absolute right-3 top-3.5 text-slate-400 hover:text-slate-600 focus-visible:ring-2 focus-visible:ring-[#00b4a7]/40 focus-visible:outline-none rounded">
                        <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>

                <div class="text-center pt-1">
                    <a href="{{ route('password.request') }}" class="text-[11px] text-slate-500 hover:text-[#00897b] transition-colors">
                        Mot de passe oublié ?
                    </a>
                </div>

                <button type="submit" class="w-full py-2.5 rounded-full bg-[#00b4a7] hover:bg-[#009b8f] text-white font-bold text-xs uppercase tracking-wider shadow-md hover:shadow-lg transition-all active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-[#00b4a7]/50 focus-visible:outline-none">
                    Se connecter
                </button>
            </form>
        </div>

        <!-- Right Side: Welcome Banner (Green to Teal Gradient) -->
        <div class="w-full md:w-1/2 p-8 md:p-12 bg-gradient-to-br from-[#2e7d32] via-[#00897b] to-[#00695c] flex flex-col items-center justify-center text-white text-center">
            
            <h2 class="text-3xl font-extrabold mb-6 tracking-tight">
                Bienvenue !
            </h2>

            <!-- Washing machine graphic matching screenshot -->
            <div class="w-32 h-36 bg-white rounded-2xl shadow-xl p-3 flex flex-col justify-between mb-6 relative">
                <!-- Top controls -->
                <div class="flex items-center justify-between border-b border-slate-200 pb-1.5 px-1">
                    <div class="flex space-x-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                    </div>
                    <span class="w-3.5 h-3.5 rounded-full bg-slate-400"></span>
                </div>

                <!-- Circular door / window -->
                <div class="w-20 h-20 mx-auto rounded-full bg-slate-200 p-1 flex items-center justify-center shadow-inner">
                    <div class="w-full h-full rounded-full bg-gradient-to-tr from-[#0288d1] via-[#29b6f6] to-[#039be5] relative overflow-hidden flex items-center justify-center border-2 border-slate-300">
                        <!-- Reflections / glass lines -->
                        <div class="absolute inset-0 bg-slate-800/30"></div>
                        <div class="w-6 h-6 rounded-full bg-white/20"></div>
                        <div class="absolute -top-2 -left-2 w-10 h-10 bg-white/30 rounded-full blur-sm"></div>
                    </div>
                </div>

                <!-- Bottom feet -->
                <div class="flex justify-between px-2">
                    <span class="w-2 h-1 bg-slate-400 rounded-b"></span>
                    <span class="w-2 h-1 bg-slate-400 rounded-b"></span>
                </div>
            </div>

            <p class="text-xs text-white/90 mb-5 font-medium">
                Inscrivez-vous et réservez !
            </p>

            <a href="{{ route('register') }}" class="px-8 py-2 rounded-full border border-white text-white hover:bg-white/10 font-bold text-xs uppercase tracking-wider transition-all">
                S'inscrire
            </a>

        </div>

    </div>

</body>
</html>
