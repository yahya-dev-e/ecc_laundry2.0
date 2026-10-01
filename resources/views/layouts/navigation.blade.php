@php
    $isAdmin = auth()->check() ? auth()->user()->isAdmin() : true;
    $userName = auth()->check() ? auth()->user()->name : 'R. Omari';
@endphp

<!-- Sidebar Navigation -->
<aside class="w-64 admin-sidebar min-h-screen flex flex-col justify-between shrink-0 shadow-lg select-none">
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
        <nav class="mt-4 space-y-0.5">
            <!-- Tableau de bord -->
            <a href="{{ route('dashboard') }}" 
               class="sidebar-link {{ request()->is('dashboard') && !request()->is('calendrier*') ? 'active' : '' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Tableau de bord</span>
            </a>

            <!-- Réservations -->
            <a href="{{ route('bookings.index') }}" 
               class="sidebar-link {{ request()->is('reservations*') || request()->is('bookings*') ? 'active' : '' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <span>Réservations</span>
            </a>

            <!-- Calendrier des réservations -->
            <a href="{{ route('calendrier') }}" 
               class="sidebar-link {{ request()->is('calendrier*') ? 'active' : '' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Calendrier des réservations</span>
            </a>

            <!-- Machines -->
            <a href="{{ route('machines.index') }}" 
               class="sidebar-link {{ request()->is('machines*') ? 'active' : '' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18" rx="3" stroke-width="2"/>
                    <circle cx="12" cy="13" r="4" stroke-width="2"/>
                </svg>
                <span>Machines</span>
            </a>

            <!-- Gestion des utilisateurs -->
            <a href="{{ route('admin.users') }}" 
               class="sidebar-link {{ request()->is('utilisateurs*') ? 'active' : '' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <span>Gestion des utilisateurs</span>
            </a>

            <!-- Réclamations -->
            <a href="{{ route('complaints.index') }}" 
               class="sidebar-link {{ request()->is('reclamations*') ? 'active' : '' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                <span>Réclamations</span>
            </a>

            <!-- Paramètres -->
            <a href="{{ route('admin.settings') }}" 
               class="sidebar-link flex items-center justify-between {{ request()->is('parametres*') ? 'active' : '' }}">
                <div class="flex items-center space-x-3">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    </svg>
                    <span>Paramètres</span>
                </div>
                <span class="text-xs text-emerald-200/60">&rsaquo;</span>
            </a>
        </nav>
    </div>

    <!-- Collapse / Bottom indicator -->
    <div class="p-4 border-t border-[#00695c] flex items-center justify-between">
        <button class="w-8 h-8 rounded-full bg-[#00695c] hover:bg-[#004d40] flex items-center justify-center text-white text-xs transition-colors">
            <span>&lsaquo;</span>
        </button>
        <span class="text-[10px] text-emerald-200/50">v2.0 FECC</span>
    </div>
</aside>
