@extends('layouts.app')

@section('content')
@php
    $usersList = $users ?? (\class_exists(\App\Models\User::class) ? \App\Models\User::orderBy('name')->get() : collect([]));
    $avatarColors = ['#00897b', '#2563eb', '#9333ea', '#d97706', '#059669', '#4f46e5', '#e11d48', '#0d9488'];
@endphp

<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <h1 class="text-xl font-bold text-slate-800 tracking-tight">Gestion des Utilisateurs</h1>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Admin Uniquement</span>
            </div>
            <p class="text-xs text-slate-500">Supervision des comptes réels de la base de données et suivi des quotas de réservation par semaine</p>
        </div>
        <button type="button" class="px-4 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded shadow-xs transition-colors">
            + Ajouter un utilisateur
        </button>
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
                    $studentId = $user->student_id ?? ($isAdminUser ? 'ADM-' . str_pad($user->id ?? 1, 3, '0', STR_PAD_LEFT) : 'STU-' . str_pad($user->id ?? 1, 5, '0', STR_PAD_LEFT));
                    $room = $user->room_number ?? ($isAdminUser ? 'Direction Campus' : 'Chambre');
                    $weeklyLimit = method_exists($user, 'weeklyLimit') ? $user->weeklyLimit() : ($isAdminUser ? 100 : 8);
                    $remaining = method_exists($user, 'weeklyRemainingLimit') ? $user->weeklyRemainingLimit() : $weeklyLimit;
                    $used = max(0, $weeklyLimit - $remaining);
                @endphp
                <div class="bg-slate-50/70 border border-slate-200 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2.5">
                            <span class="w-8 h-8 rounded-full text-white flex items-center justify-center font-bold text-xs shadow-xs" style="background-color: {{ $avatarBg }};">
                                {{ $initial }}
                            </span>
                            <div>
                                <span class="font-bold text-sm text-slate-800 block">{{ $user->name }}</span>
                                <span class="text-[11px] text-slate-500 font-mono">{{ $studentId }} ({{ $room }})</span>
                            </div>
                        </div>
                        @if ($isAdminUser)
                            <span class="px-2.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px]">Admin</span>
                        @else
                            <span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">Étudiant</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200/60">
                        <span class="text-slate-500 font-mono">{{ $user->email }}</span>
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
                            <button type="button" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-[#00897b] border border-emerald-200 text-xs font-semibold rounded-lg shadow-2xs transition-colors">
                                Réinitialiser quota
                            </button>
                        @endif
                        <button type="button" class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg shadow-2xs transition-colors">
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
                        <th class="p-3.5 font-bold">Identifiant / Chambre</th>
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
                            $studentId = $user->student_id ?? ($isAdminUser ? 'ADM-' . str_pad($user->id ?? 1, 3, '0', STR_PAD_LEFT) : 'STU-' . str_pad($user->id ?? 1, 5, '0', STR_PAD_LEFT));
                            $room = $user->room_number ?? ($isAdminUser ? 'Direction Campus' : 'Chambre');
                            $weeklyLimit = method_exists($user, 'weeklyLimit') ? $user->weeklyLimit() : ($isAdminUser ? 100 : 8);
                            $remaining = method_exists($user, 'weeklyRemainingLimit') ? $user->weeklyRemainingLimit() : $weeklyLimit;
                            $used = max(0, $weeklyLimit - $remaining);
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-3.5 font-bold text-slate-800 flex items-center space-x-2">
                                <span class="w-7 h-7 rounded-full text-white flex items-center justify-center font-bold text-[11px] shadow-xs" style="background-color: {{ $avatarBg }};">
                                    {{ $initial }}
                                </span>
                                <span>{{ $user->name }}</span>
                            </td>
                            <td class="p-3.5 text-slate-600 font-mono">{{ $user->email }}</td>
                            <td class="p-3.5 text-slate-500 font-mono">{{ $studentId }} ({{ $room }})</td>
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
                                    <button type="button" class="text-[#00897b] hover:underline font-semibold cursor-pointer">
                                        Réinitialiser quota
                                    </button>
                                @endif
                                <button type="button" class="text-slate-400 hover:text-slate-600 font-semibold cursor-pointer">
                                    Modifier
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500 text-xs">
                                Aucun utilisateur trouvé dans la base de données.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
