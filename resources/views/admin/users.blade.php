@extends('layouts.app')

@section('content')
@php
    $usersList = $users ?? (\class_exists(\App\Models\User::class) ? \App\Models\User::orderBy('name')->get() : collect([]));
    $avatarColors = ['#00897b', '#2563eb', '#9333ea', '#d97706', '#059669', '#4f46e5', '#e11d48', '#0d9488'];
@endphp

<div class="space-y-6 max-w-6xl mx-auto" x-data="{
    searchQuery: '',
    editModalOpen: false,
    editingUser: {
        id: null,
        name: '',
        email: '',
        role: 'student',
        weeklyLimit: 8,
        weeklyUsed: 0
    },
    openEdit(u) {
        this.editingUser = { ...u };
        this.editModalOpen = true;
    },
    matchesSearch(name, email, role) {
        if (!this.searchQuery || !this.searchQuery.trim()) return true;
        const q = this.searchQuery.toLowerCase().trim();
        return (name && name.toLowerCase().includes(q)) ||
               (email && email.toLowerCase().includes(q)) ||
               (role && role.toLowerCase().includes(q));
    }
}">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <h1 class="text-xl font-bold text-slate-800 tracking-tight">Gestion des Utilisateurs</h1>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Admin Uniquement</span>
            </div>
            <p class="text-xs text-slate-500">Supervision des comptes de la base de données, recherche instantanée et édition des profils</p>
        </div>
        <a href="/register" class="px-4 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded shadow-xs transition-colors self-start sm:self-auto">
            + Ajouter un utilisateur
        </a>
    </div>

    <!-- Search Bar & Statistics -->
    <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="relative flex-1 max-w-md">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input type="text" x-model="searchQuery" placeholder="Rechercher par nom, email ou rôle..." 
                   class="w-full pl-9 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:bg-white focus:border-[#00897b] focus:ring-2 focus:ring-[#00897b]/20 transition-all">
            <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 font-bold text-sm">
                &times;
            </button>
        </div>
        <div class="text-xs text-slate-500 font-medium flex items-center space-x-1.5 self-end sm:self-auto">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>{{ count($usersList) }} utilisateur{{ count($usersList) > 1 ? 's' : '' }} enregistrés en base</span>
        </div>
    </div>

    <!-- Users Table & Mobile Cards -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        
        <!-- Mobile User Cards (Visible on screens < sm) -->
        <div class="sm:hidden p-3 space-y-3">
            @forelse ($usersList as $index => $user)
                @php
                    $initial = strtoupper(substr($user->name, 0, 1));
                    $avatarBg = $avatarColors[$index % count($avatarColors)];
                    $isAdminUser = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');
                    $weeklyLimit = method_exists($user, 'weeklyLimit') ? $user->weeklyLimit() : ($user->weeklyLimit ?? ($isAdminUser ? 100 : 8));
                    $remaining = method_exists($user, 'weeklyRemainingLimit') ? $user->weeklyRemainingLimit() : max(0, $weeklyLimit - ($user->weeklyUsed ?? 0));
                    $used = max(0, $weeklyLimit - $remaining);
                @endphp
                <div class="bg-slate-50/70 border border-slate-200 rounded-xl p-4 space-y-3"
                     x-show="matchesSearch('{{ addslashes($user->name) }}', '{{ addslashes($user->email) }}', '{{ $isAdminUser ? 'administrateur admin' : 'etudiant student' }}')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2.5">
                            <span class="w-8 h-8 rounded-full text-white flex items-center justify-center font-bold text-xs shadow-xs" style="background-color: {{ $avatarBg }};">
                                {{ $initial }}
                            </span>
                            <span class="font-bold text-sm text-slate-800">{{ $user->name }}</span>
                        </div>
                        @if ($isAdminUser)
                            <span class="px-2.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px]">Admin</span>
                        @else
                            <span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">Étudiant</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200/60 font-mono">
                        <span class="text-slate-500 truncate max-w-[180px]">{{ $user->email }}</span>
                        @if ($isAdminUser)
                            <span class="font-bold text-[#00897b]">Quota : Illimité</span>
                        @else
                            <span class="font-bold {{ $used > 2 ? 'text-amber-600' : 'text-emerald-600' }}">
                                {{ $used }}/{{ $weeklyLimit }}h ({{ $remaining }}h rest.)
                            </span>
                        @endif
                    </div>
                    <div class="pt-2 border-t border-slate-200/60 flex items-center justify-end space-x-2">
                        @if (!$isAdminUser)
                            <a href="/reset-user-quota?id={{ $user->id ?? $index + 1 }}" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-[#00897b] border border-emerald-200 text-xs font-semibold rounded-lg shadow-2xs transition-colors">
                                Réinitialiser quota
                            </a>
                        @endif
                        <button type="button" 
                                @click="openEdit({
                                    id: {{ $user->id ?? $index + 1 }},
                                    name: '{{ addslashes($user->name) }}',
                                    email: '{{ addslashes($user->email) }}',
                                    role: '{{ $isAdminUser ? 'admin' : 'student' }}',
                                    weeklyLimit: {{ $weeklyLimit }},
                                    weeklyUsed: {{ $used }}
                                })"
                                class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg shadow-2xs transition-colors cursor-pointer">
                            Modifier
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-slate-500 text-xs">
                    Aucun utilisateur trouvé dans la base de données.
                </div>
            @endforelse
        </div>

        <!-- Desktop Users Table (Visible on screens >= sm) -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[640px]">
                <thead class="bg-[#00897b] text-white">
                    <tr>
                        <th class="p-3.5 font-bold">Nom de l'utilisateur</th>
                        <th class="p-3.5 font-bold">Email Institutionnel</th>
                        <th class="p-3.5 font-bold">Rôle</th>
                        <th class="p-3.5 font-bold">Quota Hebdomadaire</th>
                        <th class="p-3.5 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($usersList as $index => $user)
                        @php
                            $initial = strtoupper(substr($user->name, 0, 1));
                            $avatarBg = $avatarColors[$index % count($avatarColors)];
                            $isAdminUser = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');
                            $weeklyLimit = method_exists($user, 'weeklyLimit') ? $user->weeklyLimit() : ($user->weeklyLimit ?? ($isAdminUser ? 100 : 8));
                            $remaining = method_exists($user, 'weeklyRemainingLimit') ? $user->weeklyRemainingLimit() : max(0, $weeklyLimit - ($user->weeklyUsed ?? 0));
                            $used = max(0, $weeklyLimit - $remaining);
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors"
                            x-show="matchesSearch('{{ addslashes($user->name) }}', '{{ addslashes($user->email) }}', '{{ $isAdminUser ? 'administrateur admin' : 'etudiant student' }}')">
                            <td class="p-3.5 font-bold text-slate-800 flex items-center space-x-2">
                                <span class="w-7 h-7 rounded-full text-white flex items-center justify-center font-bold text-[11px] shadow-xs" style="background-color: {{ $avatarBg }};">
                                    {{ $initial }}
                                </span>
                                <span>{{ $user->name }}</span>
                            </td>
                            <td class="p-3.5 text-slate-600 font-mono">{{ $user->email }}</td>
                            <td class="p-3.5">
                                @if ($isAdminUser)
                                    <span class="px-2.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px]">Administrateur</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">Étudiant</span>
                                @endif
                            </td>
                            <td class="p-3.5 font-bold">
                                @if ($isAdminUser)
                                    <span class="text-[#00897b]">Illimité (100 crédits)</span>
                                @else
                                    <span class="{{ $used > 2 ? 'text-amber-600' : 'text-emerald-600' }}">
                                        {{ $used }} / {{ $weeklyLimit }}h utilisées ({{ $remaining }}h restantes)
                                    </span>
                                @endif
                            </td>
                            <td class="p-3.5 text-right space-x-2">
                                @if (!$isAdminUser)
                                    <a href="/reset-user-quota?id={{ $user->id ?? $index + 1 }}" class="text-[#00897b] hover:underline font-semibold cursor-pointer">
                                        Réinitialiser quota
                                    </a>
                                @endif
                                <button type="button" 
                                        @click="openEdit({
                                            id: {{ $user->id ?? $index + 1 }},
                                            name: '{{ addslashes($user->name) }}',
                                            email: '{{ addslashes($user->email) }}',
                                            role: '{{ $isAdminUser ? 'admin' : 'student' }}',
                                            weeklyLimit: {{ $weeklyLimit }},
                                            weeklyUsed: {{ $used }}
                                        })"
                                        class="text-slate-500 hover:text-[#00897b] font-semibold cursor-pointer">
                                    Modifier
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-500 text-xs">
                                Aucun utilisateur trouvé dans la base de données.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div x-show="editModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
         @keydown.escape.window="editModalOpen = false">
        
        <div @click.away="editModalOpen = false" 
             x-show="editModalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200">
            
            <!-- Modal Header -->
            <div style="background: linear-gradient(135deg, #004d40 0%, #00796b 100%); color: white;" class="px-6 py-4 text-white flex items-center justify-between">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-full bg-white/15 flex items-center justify-center font-bold text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </div>
                    <div>
                        <h2 class="font-bold text-sm">Modifier l'utilisateur</h2>
                        <p class="text-[11px] text-emerald-100" x-text="editingUser.name"></p>
                    </div>
                </div>
                <button type="button" @click="editModalOpen = false" class="text-white/80 hover:text-white text-xl font-bold p-1">&times;</button>
            </div>

            <!-- Modal Form -->
            <form method="POST" action="/admin/users/update" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="id" :value="editingUser.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nom complet</label>
                    <input type="text" name="name" x-model="editingUser.name" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-[#00897b] focus:ring-2 focus:ring-[#00897b]/20">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email Institutionnel</label>
                        <input type="email" name="email" x-model="editingUser.email" required
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-[#00897b] focus:ring-2 focus:ring-[#00897b]/20">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Rôle</label>
                        <select name="role" x-model="editingUser.role" 
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-[#00897b] bg-white">
                            <option value="student">Étudiant (Quota standard)</option>
                            <option value="admin">Administrateur (Illimité)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Quota hebdomadaire (heures)</label>
                        <input type="number" name="weeklyLimit" x-model="editingUser.weeklyLimit" min="1" max="200" required
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-[#00897b] focus:ring-2 focus:ring-[#00897b]/20">
                        <span class="text-[10px] text-slate-400">Standard étudiant : 8h / sem</span>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Heures consommées</label>
                        <input type="number" name="weeklyUsed" x-model="editingUser.weeklyUsed" min="0" max="200" required
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-[#00897b] focus:ring-2 focus:ring-[#00897b]/20">
                        <span class="text-[10px] text-slate-400">Heures réservées cette semaine</span>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                    <button type="button" @click="editModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors cursor-pointer">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-5 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded-lg shadow-sm transition-all cursor-pointer">
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
