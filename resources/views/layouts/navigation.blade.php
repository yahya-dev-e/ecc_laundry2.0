@php($isAdmin = auth()->user()?->isAdmin() ?? false)
@php($links = [
    ['dashboard', 'Tableau de bord'],
    ['calendrier', 'Calendrier des réservations'],
    ['bookings.create', 'Réserver une machine'],
])
<aside class="hidden lg:flex w-64 bg-[#00796b] text-white flex-col shrink-0 min-h-screen">
    <p class="px-5 py-6 font-bold">Centrale Casablanca<br><span class="text-sm font-normal">Buanderie</span></p>
    <nav aria-label="Navigation principale" class="space-y-1">
        @foreach($links as [$route, $label])<a href="{{ route($route) }}" class="sidebar-link" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>@endforeach
        @if($isAdmin)
        <a href="{{ route('admin.users') }}" class="sidebar-link">Gestion des utilisateurs</a>
        <a href="{{ route('machines.index') }}" class="sidebar-link">État des machines</a>
        @endif
    </nav>
</aside>
<div x-show="mobileMenuOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" @keydown.escape.window="mobileMenuOpen = false">
    <div class="absolute inset-0 bg-black/50" @click="mobileMenuOpen = false" aria-hidden="true"></div>
    <section id="mobile-navigation" role="dialog" aria-modal="true" aria-label="Menu de navigation"
        x-trap.inert.noscroll="mobileMenuOpen" class="relative h-full w-72 max-w-[85vw] bg-[#00796b] text-white p-4 overflow-y-auto">
        <button type="button" @click="mobileMenuOpen = false" aria-label="Fermer le menu de navigation" class="p-3 mb-4 rounded-lg border border-white/50">Fermer ×</button>
        <nav aria-label="Navigation mobile" class="space-y-1">
            @foreach($links as [$route, $label])<a href="{{ route($route) }}" class="sidebar-link" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>@endforeach
            @if($isAdmin)
            <a href="{{ route('admin.users') }}" class="sidebar-link">Gestion des utilisateurs</a>
            <a href="{{ route('machines.index') }}" class="sidebar-link">État des machines</a>
            @endif
        </nav>
    </section>
</div>
