<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nouveau mot de passe - Laundry Centrale Casablanca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen login-bg flex items-center justify-center p-4">

    <!-- Card -->
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8 md:p-10 flex flex-col items-center">
        
        <!-- Centrale Casablanca Logo -->
        <div class="flex flex-col items-center mb-6">
            <div class="w-14 h-10 relative flex items-center justify-center">
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

        <h1 class="text-xl font-extrabold text-slate-900 mb-2 text-center tracking-tight">
            Nouveau mot de passe
        </h1>
        <p class="text-xs text-slate-500 text-center mb-6 max-w-xs leading-relaxed">
            Veuillez définir un nouveau mot de passe sécurisé pour votre compte étudiant / personnel.
        </p>

        <!-- Error Alerts -->
        @if ($errors->any())
            <div class="w-full p-3 mb-5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                @foreach ($errors->all() as $error)
                    <p class="flex items-center space-x-1.5">
                        <span class="text-rose-500 font-bold">•</span>
                        <span>{{ $error }}</span>
                    </p>
                @endforeach
            </div>
        @endif

        <!-- Form -->
        <form method="POST" action="{{ route('password.update') }}" class="w-full space-y-4">
            @csrf

            <!-- Hidden Token -->
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="block text-[11px] font-bold uppercase text-slate-600 mb-1.5">Adresse email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" placeholder="nom.prenom@fecc.ma" autocomplete="email" required
                       class="w-full px-4 py-3 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00b4a7]/40 transition-all">
            </div>

            <div class="relative" x-data="{ show: false }">
                <label for="password" class="block text-[11px] font-bold uppercase text-slate-600 mb-1.5">Nouveau mot de passe</label>
                <div class="relative">
                    <input :type="show ? 'text' : 'password'" id="password" name="password" placeholder="Minimum 8 caractères" autocomplete="new-password" required
                           class="w-full px-4 py-3 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00b4a7]/40 transition-all pr-10">
                    <button type="button" @click="show = !show" aria-label="Afficher ou masquer" class="absolute right-3 top-3.5 text-slate-400 hover:text-slate-600 focus-visible:outline-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
            </div>

            <div class="relative" x-data="{ show: false }">
                <label for="password_confirmation" class="block text-[11px] font-bold uppercase text-slate-600 mb-1.5">Confirmer le mot de passe</label>
                <div class="relative">
                    <input :type="show ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" placeholder="Répétez le mot de passe" autocomplete="new-password" required
                           class="w-full px-4 py-3 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00b4a7]/40 transition-all pr-10">
                    <button type="button" @click="show = !show" aria-label="Afficher ou masquer" class="absolute right-3 top-3.5 text-slate-400 hover:text-slate-600 focus-visible:outline-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 rounded-full bg-[#00b4a7] hover:bg-[#009b8f] text-white font-bold text-xs uppercase tracking-wider shadow-md hover:shadow-lg transition-all active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-[#00b4a7]/50 focus-visible:outline-none mt-2">
                Réinitialiser le mot de passe
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-slate-100 w-full text-center">
            <a href="{{ route('login') }}" class="text-xs text-[#00897b] hover:text-[#00695c] font-semibold flex items-center justify-center space-x-1 transition-colors">
                <span>&larr;</span>
                <span>Retour à la page de connexion</span>
            </a>
        </div>

    </div>

</body>
</html>
