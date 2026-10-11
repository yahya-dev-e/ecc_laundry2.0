<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Buanderie') — Centrale Casablanca</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-bg min-h-screen p-4 sm:p-8">
    <main class="w-full max-w-md mx-auto my-4 sm:my-10 rounded-2xl bg-white shadow-xl p-6 sm:p-8 relative z-10">
        <a href="{{ route('login') }}" class="block text-center font-bold text-[#00796b] mb-6">Centrale Casablanca · Buanderie</a>
        @include('components.form-feedback')
        @yield('content')
    </main>
</body>
</html>
