@props(['machine'])

@php
    $typeStr = $machine->type instanceof \App\Enums\MachineType ? $machine->type->value : (string)$machine->type;
    $isWasher = in_array($typeStr, ['washing-machine', 'washer']);
    $typeLabel = $machine->type instanceof \App\Enums\MachineType ? $machine->type->label() : ($isWasher ? 'Machine à laver' : 'Sèche-linge');
@endphp

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-all">
    <!-- Top accent border line based on status -->
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
                <span style="background-color: {{ $machine->color ?? '#00897b' }};" 
                      class="w-10 h-10 rounded text-white font-black text-sm flex items-center justify-center shrink-0 shadow-xs">
                    {{ $isWasher ? '👕' : '🔄' }}
                </span>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="font-extrabold text-base tracking-tight text-slate-800">{{ $machine->name }}</span>
                        @if ($machine->color)
                            <span class="w-2.5 h-2.5 rounded-full inline-block border border-slate-300 shrink-0" style="background-color: {{ $machine->color }};" title="{{ $machine->color }}"></span>
                        @endif
                    </div>
                    <span class="text-xs text-slate-500 font-medium">{{ $typeLabel }}</span>
                </div>
            </div>
            
            <x-status-badge :status="$machine->status" />
        </div>

        <!-- Machine specs & details -->
        <div class="grid grid-cols-2 gap-2 my-3 text-xs bg-slate-50 p-2.5 rounded-lg border border-slate-100">
            <div class="flex items-center space-x-1.5 text-slate-500">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <span>Type: <strong class="text-slate-800">{{ $isWasher ? 'Lave-linge' : 'Sèche-linge' }}</strong></span>
            </div>
            <div class="flex items-center space-x-1.5 text-slate-500">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Cycle: <strong class="text-slate-800">{{ $machine->default_duration_minutes }}m</strong></span>
            </div>
            @if ($machine->color)
                <div class="col-span-2 flex items-center space-x-1.5 text-slate-500">
                    <span class="w-2.5 h-2.5 rounded-full inline-block border border-slate-300" style="background-color: {{ $machine->color }};"></span>
                    <span>Repère couleur : <strong class="text-slate-800 font-mono">{{ $machine->color }}</strong></span>
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
                <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-amber-500 to-emerald-500 h-1.5 rounded-full transition-all duration-1000" style="width: {{ $progress }}%"></div>
                </div>
            </div>
        @endif
    </div>

    <!-- Actions / Booking Button -->
    <div class="pt-3 border-t border-slate-100 mt-2 flex items-center justify-between">
        <div class="text-xs">
            <span class="text-slate-500">Tarif:</span>
            <span class="font-bold text-[#00897b] ml-1">{{ $machine->cost_per_cycle }} Crédit(s)</span>
        </div>

        <div>
            @if ($machine->isAvailable())
                <a href="{{ route('bookings.create', ['machine_id' => $machine->id]) }}" class="px-3.5 py-1.5 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded shadow-xs transition-colors">
                    Réserver
                </a>
            @elseif ($machine->isReserved())
                <span class="text-xs text-sky-700 font-semibold px-2 py-1 bg-sky-50 rounded border border-sky-200">
                    Réservé
                </span>
            @elseif ($machine->isInUse())
                <span class="text-xs text-amber-700 font-semibold px-2 py-1 bg-amber-50 rounded border border-amber-200">
                    En cours
                </span>
            @else
                <span class="text-xs text-rose-700 font-semibold px-2 py-1 bg-rose-50 rounded border border-rose-200">
                    Indisponible
                </span>
            @endif
        </div>
    </div>
</div>
