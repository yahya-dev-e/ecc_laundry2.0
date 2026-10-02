@extends('layouts.app')

@section('content')
@php
    $isAdmin = auth()->check() ? auth()->user()->isAdmin() : false;
    $weeklyLimit = auth()->check() ? auth()->user()->weeklyLimit() : ($isAdmin ? 100 : 8);
    $remainingHours = auth()->check() ? auth()->user()->weeklyRemainingLimit() : $weeklyLimit;
    $usedHours = max(0, $weeklyLimit - $remainingHours);
    $userName = auth()->check() ? auth()->user()->name : 'Étudiant';
@endphp

<div class="space-y-6 max-w-6xl mx-auto">
    <!-- 1. Welcome Message Banner -->
    <div style="background: linear-gradient(135deg, #004d40 0%, #00695c 50%, #00796b 100%);" class="rounded-xl p-6 text-white shadow-md flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 mb-1.5">
                <span class="px-2 py-0.5 rounded bg-white/20 text-[10px] font-bold tracking-wider uppercase">Tableau de bord</span>
                <span class="text-emerald-200 text-xs font-medium">Buanderie Centrale Casablanca</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight" style="text-wrap: balance;">Bonjour, {{ $userName }} !</h1>
            <p class="text-xs text-emerald-100/90 mt-1 max-w-xl leading-relaxed" style="text-wrap: pretty;">
                Bienvenue sur votre espace buanderie. Consultez ci-dessous vos crédits disponibles ainsi que l'historique complet de vos créneaux.
            </p>
        </div>
        <div class="flex items-center space-x-3 shrink-0">
            <a href="{{ route('bookings.create') }}" class="px-4 py-2.5 bg-white hover:bg-emerald-50 text-[#00695c] rounded-lg text-xs font-bold transition-all shadow-sm flex items-center space-x-1.5 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none">
                <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Réserver une machine</span>
            </a>
            <a href="{{ route('calendrier') }}" class="px-4 py-2.5 bg-white/10 hover:bg-white/20 border border-white/30 text-white rounded-lg text-xs font-bold transition-all focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none">
                <span>Voir le calendrier</span>
            </a>
        </div>
    </div>

    <!-- 2. Quota & Information Cards Grid (No total reservations count, no machine count/names) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Quota Remaining Card (Highlight) -->
        <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Heures réservables</span>
                    <span class="px-2.5 py-0.5 rounded text-[11px] font-mono font-bold {{ $isAdmin ? 'bg-amber-100 text-amber-800' : ($remainingHours > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800') }}">
                        {{ $isAdmin ? 'Admin (100 crédits / sem)' : 'Étudiant (8 crédits / sem)' }}
                    </span>
                </div>

                <div class="mt-4 flex items-baseline space-x-2">
                    <span class="text-3xl font-extrabold {{ $isAdmin ? 'text-amber-700' : ($remainingHours > 0 ? 'text-emerald-600' : 'text-rose-600') }} font-mono">
                        {{ $remainingHours }}h
                    </span>
                    <span class="text-xs text-slate-500 font-medium">restantes sur {{ $weeklyLimit }}h cette semaine</span>
                </div>

                <!-- Visual Progress Bar -->
                <div class="mt-3.5">
                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                        <div class="h-2.5 rounded-full transition-all duration-500 {{ $remainingHours === 0 ? 'bg-rose-500' : ($isAdmin ? 'bg-amber-500' : 'bg-[#00897b]') }}"
                             style="width: {{ min(100, round(($remainingHours / $weeklyLimit) * 100)) }}%"></div>
                    </div>
                    <div class="flex justify-between items-center text-[10px] text-slate-400 mt-1.5 font-medium">
                        <span>{{ $usedHours }}h utilisées sur {{ $weeklyLimit }}h</span>
                        <span>{{ $remainingHours }}h disponibles</span>
                    </div>
                </div>
            </div>

            <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">1 heure = 1 crédit</span>
                <a href="{{ route('bookings.create') }}" class="text-[#00897b] font-bold hover:underline flex items-center space-x-1">
                    <span>Réserver un créneau</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <!-- Rules & Quota Policy Card (Clean guidance, no machine counts or reservation totals) -->
        <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Règles & Utilisation des crédits</span>
                    <span class="px-2.5 py-0.5 rounded text-[11px] font-mono font-bold bg-slate-100 text-slate-700">Campus ECC</span>
                </div>

                <div class="mt-4 space-y-2.5 text-xs text-slate-600">
                    <div class="flex items-start space-x-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#00897b] mt-1.5 shrink-0"></span>
                        <span><strong>Renouvellement hebdomadaire :</strong> Vos crédits se réinitialisent chaque lundi à 00h00.</span>
                    </div>
                    <div class="flex items-start space-x-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#00897b] mt-1.5 shrink-0"></span>
                        <span><strong>Disponibilité 24h/24 :</strong> Choisissez librement n'importe quel créneau ouvert sur toute la journée.</span>
                    </div>
                    <div class="flex items-start space-x-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#00897b] mt-1.5 shrink-0"></span>
                        <span><strong>Réservation multi-créneaux :</strong> Vous pouvez sélectionner plusieurs heures en une seule étape.</span>
                    </div>
                </div>
            </div>

            <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-400">Campus Centrale Casablanca</span>
                <a href="{{ route('calendrier') }}" class="text-[#00897b] font-bold hover:underline flex items-center space-x-1">
                    <span>Ouvrir le calendrier</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 3. Reservation History Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/80 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800">Historique de vos réservations</h2>
                <p class="text-[11px] text-slate-500">Liste complète de vos créneaux réservés et validés</p>
            </div>
            <a href="{{ route('bookings.create') }}" class="px-3.5 py-1.5 bg-[#00897b] hover:bg-[#00796b] text-white rounded text-xs font-bold shadow-xs flex items-center space-x-1">
                <span>+</span>
                <span>Nouveau créneau</span>
            </a>
        </div>

        @if(!isset($userReservations) || $userReservations->isEmpty())
            <div class="p-8 text-center">
                <svg aria-hidden="true" class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-xs font-bold text-slate-600">Aucune réservation pour le moment</p>
                <p class="text-[11px] text-slate-400 mt-1">Vous n'avez pas encore réservé de créneau cette semaine.</p>
                <a href="{{ route('bookings.create') }}" class="inline-block mt-3 px-4 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded focus-visible:ring-2 focus-visible:ring-[#00897b]/40 focus-visible:outline-none">
                    Réserver votre premier créneau
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100/75 text-slate-700 font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4">Machine</th>
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Créneau horaire</th>
                            <th class="py-3 px-4">Durée & Crédits</th>
                            <th class="py-3 px-4">Statut</th>
                            <th class="py-3 px-4 text-right">Calendrier</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($userReservations as $res)
                            @php
                                $machine = $res->machine;
                                $durationHours = max(1, round(($res->end_time->diffInMinutes($res->start_time)) / 60));
                                $machineColor = $machine?->color ?? '#4338ca';
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-4">
                                    <span style="background-color: {{ $machineColor }};" class="px-2.5 py-1 rounded text-xs font-mono font-bold text-white shadow-xs inline-block">
                                        {{ $machine?->name ?? 'Machine' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-700 font-medium">
                                    {{ $res->start_time->format('d/m/Y') }}
                                </td>
                                <td class="py-3 px-4 font-mono font-bold text-slate-800">
                                    {{ $res->start_time->format('H:i') }} - {{ $res->end_time->format('H:i') }}
                                </td>
                                <td class="py-3 px-4 text-slate-600 font-semibold">
                                    {{ $durationHours }} h ({{ $durationHours }} crédit{{ $durationHours > 1 ? 's' : '' }})
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] inline-flex items-center space-x-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                        <span>Confirmé</span>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('calendrier') }}?date={{ $res->start_time->toDateString() }}&machine_id={{ $res->machine_id }}" class="px-2.5 py-1 text-[11px] font-semibold text-[#00897b] hover:bg-emerald-50 rounded border border-emerald-200 transition-colors inline-block">
                                        Voir au calendrier &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
