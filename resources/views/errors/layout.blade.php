@php
    $status = $exception->getStatusCode();
    [$heading, $description] = match ($status) {
        401 => ['Connexion nécessaire', 'Connectez-vous pour accéder à cette page.'],
        403 => ['Accès non autorisé', 'Votre compte ou votre connexion ne permet pas d’accéder à cette page.'],
        404 => ['Page introuvable', 'Cette page ou cette réservation n’existe plus.'],
        405 => ['Action non disponible', 'Ouvrez la page du site avant de recommencer cette action.'],
        419 => ['Session expirée', 'Votre session a expiré. Rechargez le formulaire et réessayez. Cette action n’a pas été envoyée.'],
        429 => ['Trop de tentatives', 'Patientez quelques instants avant de réessayer.'],
        503 => ['Service temporairement indisponible', 'Le site est en maintenance. Réessayez un peu plus tard.'],
        default => ['Une erreur est survenue', 'Nous n’avons pas pu terminer cette demande. Consultez vos réservations avant de recommencer pour éviter un doublon.'],
    };
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $heading }} — Centrale Casablanca</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:#f4f7f6;color:#1e293b;font:16px/1.6 system-ui,sans-serif;padding:24px}
        main{max-width:560px;margin:8vh auto;background:white;border-top:6px solid #00897b;border-radius:16px;padding:32px;box-shadow:0 8px 32px #003c3312}
        .brand{color:#00796b;font-weight:700}.code{color:#64748b;font-size:14px}h1{line-height:1.25;font-size:26px}
        nav{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}a{display:inline-flex;align-items:center;min-height:44px;padding:10px 16px;border-radius:8px;color:#00796b}
        .primary{background:#00796b;color:white;text-decoration:none}a:focus-visible{outline:3px solid #00897b;outline-offset:3px}
    </style>
</head>
<body><main aria-labelledby="error-title">
    <p class="brand">Centrale Casablanca · Buanderie</p>
    <p class="code">Erreur {{ $status }}</p>
    <h1 id="error-title">{{ $heading }}</h1>
    <p>{{ $description }}</p>
    <nav aria-label="Revenir au site">
        <a class="primary" href="/">Revenir au site</a>
        <a href="/login">Se connecter</a>
    </nav>
</main></body>
</html>
