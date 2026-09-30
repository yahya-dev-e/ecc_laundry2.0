@extends('layouts.app')

@section('title', 'Mes Réservations & Historique')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">

    <!-- Page Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Mes Réservations & Historique</h1>
            <p class="text-xs text-slate-500">Suivi des cycles en cours, réservations à venir et relevé de vos crédits</p>
        </div>
        <a href="{{ route('bookings.create') }}" class="px-4 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded shadow-xs flex items-center space-x-1.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Réserver une machine</span>
        </a>
    </div>

    <!-- Active & Upcoming Section -->
    <div class="space-y-3">
        <h2 class="text-sm font-bold text-slate-800 flex items-center space-x-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
            <span>Réservations Actives & À Venir</span>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($upcomingBookings->merge($activeBookings) as $booking)
                <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-5 flex flex-col justify-between relative overflow-hidden">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <span style="background-color: {{ $booking->machine->color ?? '#00897b' }};" 
                                  class="w-9 h-9 rounded text-white font-black text-xs flex items-center justify-center shrink-0 shadow-xs">
                                👕
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">{{ $booking->machine->name }}</h3>
                                <p class="text-xs text-slate-500">
                                    {{ $booking->machine->type instanceof \App\Enums\MachineType ? $booking->machine->type->label() : ucfirst($booking->machine->type) }}
                                </p>
                            </div>
                        </div>
                        <x-status-badge :status="$booking->status" />
                    </div>

                    <!-- Time & Details -->
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200/80 my-2 text-xs space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Créneau prévu :</span>
                            <span class="font-bold text-slate-800">
                                {{ $booking->start_time->format('M d, H:i') }} - {{ $booking->end_time->format('H:i') }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Décompte quota :</span>
                            <span class="font-bold text-[#00897b]">{{ $booking->credits_spent }} h / crédit</span>
                        </div>

                        @if ($booking->status === 'in_progress')
                            <div class="pt-2 border-t border-slate-200">
                                <x-countdown-timer :endsAt="$booking->end_time->toIso8601String()" :totalMinutes="45" />
                            </div>
                        @endif
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        @if ($booking->status === 'upcoming' || $booking->status === 'in_progress')
                            @if ($booking->canBeStarted() && $booking->status !== 'in_progress')
                                <form method="POST" action="{{ route('bookings.start', $booking) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded shadow-xs transition-colors">
                                        Démarrer le cycle
                                    </button>
                                </form>
                            @elseif ($booking->status === 'in_progress')
                                <span class="text-xs text-amber-600 font-bold flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                    Cycle en cours
                                </span>
                            @endif
                        @endif

                        @if ($booking->status === 'upcoming')
                            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Annuler cette réservation ? Votre quota vous sera restitué.');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded border border-rose-200 transition-colors">
                                    Annuler
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white rounded-lg shadow-sm border border-slate-200 p-8 text-center text-slate-500">
                    <p class="text-sm font-medium">Vous n'avez aucune réservation active ou à venir pour le moment.</p>
                    <a href="{{ route('bookings.create') }}" class="mt-3 inline-block px-4 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded shadow-xs">
                        Réserver une machine
                    </a>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Past Bookings History & Ledger Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Past Bookings Table (2 cols) -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-800">Historique des Réservations Terminées</h2>
                <span class="text-xs text-slate-500">{{ $pastBookings->total() ?? $pastBookings->count() }} réservations</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#00897b] text-white font-bold">
                        <tr>
                            <th class="p-3.5">Machine</th>
                            <th class="p-3.5">Date & Heure</th>
                            <th class="p-3.5">Statut</th>
                            <th class="p-3.5 text-right">Quota Décompté</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($pastBookings as $past)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="p-3.5 font-bold text-slate-800">
                                    <div class="flex items-center space-x-2">
                                        <span style="background-color: {{ $past->machine->color ?? '#00897b' }};" 
                                              class="w-3 h-3 rounded-full inline-block shrink-0 border border-slate-300"></span>
                                        <span class="font-bold text-slate-800">{{ $past->machine->name }}</span>
                                    </div>
                                    <span class="text-slate-400 text-[11px] block mt-0.5 ml-5">
                                        ({{ $past->machine->type instanceof \App\Enums\MachineType ? $past->machine->type->label() : ucfirst($past->machine->type) }})
                                    </span>
                                </td>
                                <td class="p-3.5 text-slate-600 font-medium">
                                    {{ $past->start_time->format('M d, Y • H:i') }}
                                </td>
                                <td class="p-3.5">
                                    <x-status-badge :status="$past->status" />
                                </td>
                                <td class="p-3.5 text-right font-mono font-bold text-slate-700">
                                    {{ $past->credits_spent }} h
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-400">
                                    Aucun historique de réservation trouvé.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($pastBookings, 'links'))
                <div class="p-3 border-t border-slate-200 bg-slate-50">
                    {{ $pastBookings->links() }}
                </div>
            @endif
        </div>

        <!-- Recent Credit Transactions / Quota Ledger (1 col) -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-sm font-bold text-slate-800">Surveillance Quota</h2>
                <span class="text-xs px-2.5 py-1 rounded bg-emerald-50 text-[#00897b] font-bold border border-emerald-200">
                    {{ auth()->check() ? auth()->user()->weeklyRemainingLimit() : 8 }}h / 8h
                </span>
            </div>

            <div class="space-y-2.5">
                @forelse ($recentTransactions as $txn)
                    <div class="p-3 rounded-lg bg-slate-50 border border-slate-200/80 flex items-center justify-between text-xs">
                        <div>
                            <p class="font-bold text-slate-800">{{ $txn->description }}</p>
                            <p class="text-[10px] text-slate-400">{{ $txn->created_at->format('M d, H:i') }}</p>
                        </div>
                        <div class="font-mono font-bold {{ $txn->amount > 0 ? 'text-emerald-600' : 'text-slate-600' }}">
                            {{ $txn->amount > 0 ? '+'.$txn->amount : $txn->amount }} cr
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 text-center py-6">Aucune activité récente enregistrée.</p>
                @endforelse
            </div>
        </div>

    </div>

</div>
@endsection
