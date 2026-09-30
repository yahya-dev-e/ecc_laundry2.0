@props(['machine'])

@php
    $typeStr = $machine->type instanceof \App\Enums\MachineType ? $machine->type->value : (string)$machine->type;
    $isWasher = in_array($typeStr, ['washing-machine', 'washer']);
    $typeLabel = $machine->type instanceof \App\Enums\MachineType ? $machine->type->label() : ($isWasher ? 'Washing Machine' : 'Tumble Dryer');
@endphp

<div class="glass-card-hover p-5 flex flex-col justify-between relative overflow-hidden group">
    <!-- Top accent border line based on status or machine color -->
    <div class="absolute top-0 left-0 right-0 h-1 
        {{ $machine->isAvailable() ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : '' }}
        {{ $machine->isInUse() ? 'bg-gradient-to-r from-amber-500 to-orange-400' : '' }}
        {{ $machine->isReserved() ? 'bg-gradient-to-r from-sky-500 to-cyan-400' : '' }}
        {{ !$machine->status->isOperable() ? 'bg-rose-500' : '' }}
    "></div>

    <!-- Header: Icon, Machine Name & Type, Status Badge -->
    <div>
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 
                    {{ $isWasher ? 'bg-sky-500/10 text-cyan-400 border border-sky-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}"
                    @if($machine->color) style="border-color: {{ $machine->color }}40;" @endif>
                    @if ($isWasher)
                        <!-- Washing machine icon with spin animation when active -->
                        <svg class="w-6 h-6 {{ $machine->isInUse() ? 'animate-spin-slow' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="18" height="18" rx="4" stroke-width="2"/>
                            <circle cx="12" cy="13" r="5" stroke-width="2"/>
                            <path d="M12 10a3 3 0 0 1 3 3" stroke-width="2"/>
                            <circle cx="7" cy="6" r="1" fill="currentColor"/>
                            <circle cx="10" cy="6" r="1" fill="currentColor"/>
                        </svg>
                    @else
                        <!-- Tumble dryer icon -->
                        <svg class="w-6 h-6 {{ $machine->isInUse() ? 'animate-pulse-fast' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="18" height="18" rx="4" stroke-width="2"/>
                            <circle cx="12" cy="13" r="5.5" stroke-width="2" stroke-dasharray="3 3"/>
                            <path d="M9.5 13a2.5 2.5 0 0 1 5 0" stroke-width="2"/>
                            <circle cx="7" cy="6" r="1" fill="currentColor"/>
                        </svg>
                    @endif
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="font-extrabold text-base tracking-tight text-white">{{ $machine->name }}</span>
                        @if ($machine->color)
                            <span class="w-3 h-3 rounded-full inline-block border border-slate-600 shrink-0" style="background-color: {{ $machine->color }};" title="{{ $machine->color }}"></span>
                        @endif
                    </div>
                    <span class="text-xs text-slate-400 font-mono">{{ $typeLabel }}</span>
                </div>
            </div>
            
            <x-status-badge :status="$machine->status" />
        </div>

        <!-- Machine specs & details -->
        <div class="grid grid-cols-2 gap-2 my-3 text-xs bg-slate-950/40 p-2.5 rounded-xl border border-slate-800/60">
            <div class="flex items-center space-x-1.5 text-slate-400">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <span>Type: <strong class="text-slate-200">{{ $isWasher ? 'Lave-linge' : 'Sèche-linge' }}</strong></span>
            </div>
            <div class="flex items-center space-x-1.5 text-slate-400">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Cycle: <strong class="text-slate-200">{{ $machine->default_duration_minutes }}m</strong></span>
            </div>
            @if ($machine->color)
                <div class="col-span-2 flex items-center space-x-1.5 text-slate-400">
                    <span class="w-2.5 h-2.5 rounded-full inline-block border border-slate-500" style="background-color: {{ $machine->color }};"></span>
                    <span>Repère couleur : <strong class="text-slate-200 font-mono">{{ $machine->color }}</strong></span>
                </div>
            @endif
        </div>

        <!-- Live Cycle Countdown / Progress Bar if In Use -->
        @if ($machine->isInUse() && $machine->current_cycle_ends_at)
            <div class="mb-3 space-y-2">
                <x-countdown-timer :endsAt="$machine->current_cycle_ends_at->toIso8601String()" :totalMinutes="$machine->default_duration_minutes" />
                
                @php
                    $progress = $machine->cycleProgressPercentage();
                @endphp
                <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-amber-500 to-cyan-400 h-1.5 rounded-full transition-all duration-1000" style="width: {{ $progress }}%"></div>
                </div>
            </div>
        @endif
    </div>

    <!-- Actions / Booking Button -->
    <div class="pt-2 border-t border-slate-800/80 mt-2 flex items-center justify-between">
        <div class="text-xs">
            <span class="text-slate-400">Tarif:</span>
            <span class="font-bold text-amber-400 ml-1">{{ $machine->cost_per_cycle }} Crédit(s)</span>
        </div>

        <div>
            @if ($machine->isAvailable())
                <a href="{{ route('bookings.create', ['machine_id' => $machine->id]) }}" class="btn-primary text-xs !py-1.5 !px-3">
                    Réserver
                </a>
            @elseif ($machine->isReserved())
                <span class="text-xs text-sky-400 font-medium px-2 py-1 bg-sky-500/10 rounded-lg border border-sky-500/20">
                    Réservé
                </span>
            @elseif ($machine->isInUse())
                <span class="text-xs text-amber-400 font-medium px-2 py-1 bg-amber-500/10 rounded-lg border border-amber-500/20">
                    En cours
                </span>
            @else
                <span class="text-xs text-rose-400 font-medium px-2 py-1 bg-rose-500/10 rounded-lg border border-rose-500/20">
                    Indisponible
                </span>
            @endif
        </div>
    </div>
</div>
