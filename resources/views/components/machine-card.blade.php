@props(['machine'])

<div class="glass-card-hover p-5 flex flex-col justify-between relative overflow-hidden group">
    <!-- Top accent border line based on status -->
    <div class="absolute top-0 left-0 right-0 h-1 
        {{ $machine->isAvailable() ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : '' }}
        {{ $machine->isInUse() ? 'bg-gradient-to-r from-amber-500 to-orange-400' : '' }}
        {{ $machine->isReserved() ? 'bg-gradient-to-r from-sky-500 to-cyan-400' : '' }}
        {{ !$machine->status->isOperable() ? 'bg-rose-500' : '' }}
    "></div>

    <!-- Header: Icon, Machine Code & Name, Status Badge -->
    <div>
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 
                    {{ $machine->type->value === 'washer' ? 'bg-sky-500/10 text-cyan-400 border border-sky-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                    @if ($machine->type->value === 'washer')
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
                        <span class="font-extrabold text-base tracking-tight text-white">{{ $machine->code }}</span>
                        <span class="text-xs px-2 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">{{ ucfirst($machine->type->value) }}</span>
                    </div>
                    <p class="text-xs text-slate-400 font-medium truncate max-w-[140px]">{{ $machine->name }}</p>
                </div>
            </div>
            
            <x-status-badge :status="$machine->status" />
        </div>

        <!-- Machine specs & location -->
        <div class="grid grid-cols-2 gap-2 my-3 text-xs bg-slate-950/40 p-2.5 rounded-xl border border-slate-800/60">
            <div class="flex items-center space-x-1.5 text-slate-400">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                </svg>
                <span>Cap: <strong class="text-slate-200">{{ $machine->capacity_kg }} kg</strong></span>
            </div>
            <div class="flex items-center space-x-1.5 text-slate-400">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Cycle: <strong class="text-slate-200">{{ $machine->default_duration_minutes }}m</strong></span>
            </div>
            <div class="col-span-2 flex items-center space-x-1.5 text-slate-400 truncate">
                <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="truncate">{{ $machine->location ?? 'Campus Laundry Hub' }}</span>
            </div>
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
            <span class="text-slate-400">Rate:</span>
            <span class="font-bold text-amber-400 ml-1">{{ $machine->cost_per_cycle }} Credits</span>
        </div>

        <div>
            @if ($machine->isAvailable())
                <a href="{{ route('bookings.create', ['machine_id' => $machine->id]) }}" class="btn-primary text-xs !py-1.5 !px-3">
                    Reserve
                </a>
            @elseif ($machine->isReserved())
                <span class="text-xs text-sky-400 font-medium px-2 py-1 bg-sky-500/10 rounded-lg border border-sky-500/20">
                    Booked
                </span>
            @elseif ($machine->isInUse())
                <span class="text-xs text-amber-400 font-medium px-2 py-1 bg-amber-500/10 rounded-lg border border-amber-500/20">
                    Running
                </span>
            @else
                <span class="text-xs text-rose-400 font-medium px-2 py-1 bg-rose-500/10 rounded-lg border border-rose-500/20">
                    Unavailable
                </span>
            @endif
        </div>
    </div>
</div>
