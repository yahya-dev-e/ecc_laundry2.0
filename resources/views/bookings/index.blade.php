@extends('layouts.app')

@section('title', 'My Laundry Bookings & Activity')

@section('content')
<div class="space-y-8">

    <!-- Page Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Mes Réservations & Historique</h1>
            <p class="text-xs text-slate-400">Suivi des cycles en cours, réservations à venir et relevé de vos crédits</p>
        </div>
        <a href="{{ route('bookings.create') }}" class="btn-primary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Réserver une machine
        </a>
    </div>

    <!-- Active & Upcoming Section -->
    <div class="space-y-4">
        <h2 class="text-base font-bold text-white flex items-center space-x-2">
            <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
            <span>Réservations Actives & À Venir</span>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse ($upcomingBookings->merge($activeBookings) as $booking)
                <div class="glass-card p-5 relative overflow-hidden flex flex-col justify-between">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 flex items-center justify-center font-black text-sm"
                                @if($booking->machine->color) style="border-color: {{ $booking->machine->color }}60;" @endif>
                                @if($booking->machine->color)
                                    <span class="w-3 h-3 rounded-full" style="background-color: {{ $booking->machine->color }};"></span>
                                @else
                                    👕
                                @endif
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white">{{ $booking->machine->name }}</h3>
                                <p class="text-xs text-slate-400">
                                    {{ $booking->machine->type instanceof \App\Enums\MachineType ? $booking->machine->type->label() : ucfirst($booking->machine->type) }}
                                </p>
                            </div>
                        </div>
                        <x-status-badge :status="$booking->status" />
                    </div>

                    <!-- Time & Details -->
                    <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800/80 my-3 text-xs space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Créneau prévu :</span>
                            <span class="font-medium text-slate-200">
                                {{ $booking->start_time->format('M d, H:i') }} - {{ $booking->end_time->format('H:i') }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Crédits déduits :</span>
                            <span class="font-bold text-amber-400">{{ $booking->credits_spent }} Crédit(s)</span>
                        </div>

                        @if ($booking->status === 'in_progress')
                            <div class="pt-2 border-t border-slate-800">
                                <x-countdown-timer :endsAt="$booking->end_time->toIso8601String()" :totalMinutes="45" />
                            </div>
                        @endif
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between">
                        @if ($booking->status === 'upcoming' || $booking->status === 'in_progress')
                            @if ($booking->canBeStarted() && $booking->status !== 'in_progress')
                                <form method="POST" action="{{ route('bookings.start', $booking) }}">
                                    @csrf
                                    <button type="submit" class="btn-success">
                                        Démarrer le cycle
                                    </button>
                                </form>
                            @elseif ($booking->status === 'in_progress')
                                <span class="text-xs text-amber-400 font-semibold flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                                    Cycle en cours
                                </span>
                            @endif
                        @endif

                        @if ($booking->status === 'upcoming')
                            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Annuler cette réservation ? Vos crédits vous seront remboursés.');">
                                @csrf
                                <button type="submit" class="btn-danger">
                                    Annuler
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full glass-card p-8 text-center text-slate-400">
                    <p class="text-sm font-medium">Vous n'avez aucune réservation active ou à venir pour le moment.</p>
                    <a href="{{ route('bookings.create') }}" class="btn-primary text-xs mt-3 inline-block">Réserver une machine</a>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Past Bookings History & Ledger Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Past Bookings Table (2 cols) -->
        <div class="lg:col-span-2 glass-card p-6">
            <h2 class="text-base font-bold text-white mb-4">Historique des Réservations Terminées</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider">
                            <th class="pb-3 font-semibold">Machine</th>
                            <th class="pb-3 font-semibold">Date & Heure</th>
                            <th class="pb-3 font-semibold">Statut</th>
                            <th class="pb-3 font-semibold text-right">Coût</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse ($pastBookings as $past)
                            <tr class="hover:bg-slate-800/20 transition-colors">
                                <td class="py-3 font-medium text-white">
                                    <div class="flex items-center space-x-2">
                                        @if($past->machine->color)
                                            <span class="w-2.5 h-2.5 rounded-full inline-block shrink-0" style="background-color: {{ $past->machine->color }};"></span>
                                        @endif
                                        <span class="font-bold text-cyan-400">{{ $past->machine->name }}</span>
                                    </div>
                                    <span class="text-slate-400 text-[11px] block mt-0.5">({{ $past->machine->type instanceof \App\Enums\MachineType ? $past->machine->type->label() : ucfirst($past->machine->type) }})</span>
                                </td>
                                <td class="py-3 text-slate-300">
                                    {{ $past->start_time->format('M d, Y • H:i') }}
                                </td>
                                <td class="py-3">
                                    <x-status-badge :status="$past->status" />
                                </td>
                                <td class="py-3 text-right font-mono text-slate-200">
                                    {{ $past->credits_spent }} cr
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-500">
                                    Aucun historique de réservation trouvé.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $pastBookings->links() }}
            </div>
        </div>

        <!-- Recent Credit Transactions Ledger (1 col) -->
        <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-white">Solde de Crédits</h2>
                <span class="text-xs px-2.5 py-1 rounded-full bg-slate-800 text-amber-400 font-bold">
                    {{ auth()->user()->credits }} cr disponibles
                </span>
            </div>

            <div class="space-y-3">
                @forelse ($recentTransactions as $txn)
                    <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between text-xs">
                        <div>
                            <p class="font-semibold text-slate-200">{{ $txn->description }}</p>
                            <p class="text-[10px] text-slate-500">{{ $txn->created_at->format('M d, H:i') }}</p>
                        </div>
                        <div class="font-mono font-bold {{ $txn->amount > 0 ? 'text-emerald-400' : 'text-slate-400' }}">
                            {{ $txn->amount > 0 ? '+'.$txn->amount : $txn->amount }} cr
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-500 text-center py-4">Aucune transaction récente enregistrée.</p>
                @endforelse
            </div>
        </div>

    </div>

</div>
@endsection
