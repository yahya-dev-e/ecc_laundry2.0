@php
    $isAdmin = auth()->check() ? auth()->user()->isAdmin() : true;
    $userName = auth()->check() ? auth()->user()->name : 'R. Omari';
@endphp

<!-- Desktop Sidebar Navigation (visible on lg screens and up) -->
<aside class="hidden lg:flex w-64 admin-sidebar min-h-screen flex-col justify-between shrink-0 shadow-lg select-none">
    <div>
        <!-- Brand / Header -->
        <div class="px-5 py-5 flex items-center space-x-3 border-b border-[#00695c]">
            <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center text-[#00796b] shadow font-black text-sm">
                @if ($isAdmin)
                    <svg class="w-5 h-5 text-[#00796b]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                @else
                    <svg class="w-5 h-5 text-[#00796b]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                @endif
            </div>
            <div>
                <span class="text-xs font-black tracking-wider uppercase text-white block">
                    {{ $isAdmin ? 'ADMIN PANNEAU' : 'ESPACE ÉTUDIANT' }}
                </span>
                <span class="text-[10px] text-emerald-200/70 font-semibold block">
                    Centrale Casablanca
                </span>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="mt-4 space-y-0.5" aria-label="Navigation principale">
            <a href="{{ route('dashboard') }}" 
               class="sidebar-link focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none {{ request()->is('dashboard') && !request()->is('calendrier*') ? 'active' : '' }}">
                <svg aria-hidden="true" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Tableau de bord</span>
            </a>

            <a href="{{ route('calendrier') }}" 
               class="sidebar-link focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none {{ request()->is('calendrier*') ? 'active' : '' }}">
                <svg aria-hidden="true" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Calendrier des réservations</span>
            </a>

            <a href="{{ route('bookings.create') }}" 
               class="sidebar-link focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none {{ request()->is('bookings/create') || request()->is('reserver*') ? 'active' : '' }}">
                <svg aria-hidden="true" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Réserver une machine</span>
            </a>

            @if ($isAdmin)
                <a href="{{ route('admin.users') }}" 
                   class="sidebar-link focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none {{ request()->is('utilisateurs*') ? 'active' : '' }}">
                    <svg aria-hidden="true" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span>Gestion des utilisateurs</span>
                </a>
            @endif
        </nav>
    </div>

    <!-- Bottom Bar with Disconnect -->
    <div class="p-4 border-t border-[#00695c] flex items-center justify-between">
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="flex items-center space-x-2 text-xs text-emerald-200/80 hover:text-white transition-colors cursor-pointer group focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-white rounded py-1 px-1.5" title="Se déconnecter de votre session">
                <svg class="w-4 h-4 text-emerald-300 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span class="font-medium">Déconnexion</span>
            </button>
        </form>
        <span class="text-[10px] text-emerald-200/50">v2.0 FECC</span>
    </div>
</aside>

<!-- Mobile Slide-over Drawer (visible on screens < lg when toggled) -->
<div x-show="mobileMenuOpen" class="fixed inset-0 z-50 lg:hidden" style="display: none;" x-cloak>
    <!-- Dark Backdrop -->
    <div @click="mobileMenuOpen = false" 
         x-show="mobileMenuOpen" 
         x-transition:enter="transition-opacity ease-out duration-200" 
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100" 
         x-transition:leave="transition-opacity ease-in duration-150" 
         x-transition:leave-start="opacity-100" 
         x-transition:leave-end="opacity-0" 
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"></div>

    <!-- Mobile Navigation Drawer -->
    <aside x-show="mobileMenuOpen" 
           x-transition:enter="transition ease-out duration-200 transform" 
           x-transition:enter-start="-translate-x-full" 
           x-transition:enter-end="translate-x-0" 
           x-transition:leave="transition ease-in duration-150 transform" 
           x-transition:leave-start="translate-x-0" 
           x-transition:leave-end="-translate-x-full" 
           class="relative w-72 max-w-[85vw] admin-sidebar h-full min-h-screen flex flex-col justify-between shadow-2xl z-50">
        <div>
            <!-- Header with Close Button -->
            <div class="px-5 py-4 flex items-center justify-between border-b border-[#00695c]">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center text-[#00796b] shadow font-black text-sm">
                        @if ($isAdmin)
                            <svg class="w-5 h-5 text-[#00796b]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-[#00796b]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                            </svg>
                        @endif
                    </div>
                    <div>
                        <span class="text-xs font-black tracking-wider uppercase text-white block">
                            {{ $isAdmin ? 'ADMIN PANNEAU' : 'ESPACE ÉTUDIANT' }}
                        </span>
                        <span class="text-[10px] text-emerald-200/70 font-semibold block">
                            Centrale Casablanca
                        </span>
                    </div>
                </div>
                <button type="button" @click="mobileMenuOpen = false" class="p-1.5 text-white/80 hover:text-white rounded-lg hover:bg-[#00695c]" aria-label="Fermer le menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="mt-3 space-y-0.5 px-2" aria-label="Navigation mobile">
                <a href="{{ route('dashboard') }}" 
                   @click="mobileMenuOpen = false"
                   class="sidebar-link rounded-lg focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none {{ request()->is('dashboard') && !request()->is('calendrier*') ? 'active' : '' }}">
                    <svg aria-hidden="true" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span>Tableau de bord</span>
                </a>

                <a href="{{ route('calendrier') }}" 
                   @click="mobileMenuOpen = false"
                   class="sidebar-link rounded-lg focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none {{ request()->is('calendrier*') ? 'active' : '' }}">
                    <svg aria-hidden="true" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>Calendrier des réservations</span>
                </a>

                <a href="{{ route('bookings.create') }}" 
                   @click="mobileMenuOpen = false"
                   class="sidebar-link rounded-lg focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none {{ request()->is('bookings/create') || request()->is('reserver*') ? 'active' : '' }}">
                    <svg aria-hidden="true" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Réserver une machine</span>
                </a>

                @if ($isAdmin)
                    <a href="{{ route('admin.users') }}" 
                       @click="mobileMenuOpen = false"
                       class="sidebar-link rounded-lg focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none {{ request()->is('utilisateurs*') ? 'active' : '' }}">
                        <svg aria-hidden="true" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span>Gestion des utilisateurs</span>
                    </a>
                @endif
            </nav>
        </div>

        <div class="p-4 border-t border-[#00695c] flex items-center justify-between">
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="flex items-center space-x-2 text-xs text-emerald-200/80 hover:text-white transition-colors cursor-pointer group focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-white rounded py-1 px-1.5" title="Se déconnecter">
                    <svg class="w-4 h-4 text-emerald-300 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span class="font-medium">Déconnexion</span>
                </button>
            </form>
            <span class="text-[10px] text-emerald-200/50">v2.0 FECC</span>
        </div>
    </aside>
</div>
