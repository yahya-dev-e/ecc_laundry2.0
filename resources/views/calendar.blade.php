@extends('layouts.app')

@section('content')
@php
    $washers = $machines->filter(fn($m) => in_array($m->type instanceof \App\Enums\MachineType ? $m->type->value : (string)$m->type, ['washing-machine', 'washer']));
    $dryers = $machines->filter(fn($m) => in_array($m->type instanceof \App\Enums\MachineType ? $m->type->value : (string)$m->type, ['dryer']));
    $firstMachine = $machines->first();
    $currentSelectedMachineId = request('machine_id', $firstMachine?->id ?? 1);
    $currentMachine = $machines->firstWhere('id', $currentSelectedMachineId) ?? $firstMachine;

    // Harmonious, accessible color palette (neither harsh neon nor dull/dark)
    $machineColorPalette = [
        'ML1-OM' => '#4338ca', // Refined Indigo
        'ML2-OM' => '#0d9488', // Teal
        'ML1-PE' => '#2563eb', // Royal Blue
        'ML2-PE' => '#d97706', // Warm Amber (replaces unreadable neon yellow)
        'ML3-PE' => '#db2777', // Rose / Pink (replaces harsh magenta)
        'ML4-PE' => '#ea580c', // Orange
        'ML3-OM' => '#059669', // Emerald (replaces dull dark teal)
        'SL1-OM' => '#b45309', // Amber Brown
        'SL2-OM' => '#16a34a', // Green
        'SL1-PE' => '#475569', // Slate
        'SL2-PE' => '#65a30d', // Lime
        'SL3-PE' => '#9333ea', // Purple
        'SL3-OM' => '#52525b', // Zinc
    ];
@endphp

<div class="space-y-6 max-w-6xl mx-auto" x-data="{
    search: '',
    selectedMachineName: '{{ $currentMachine?->name ?? 'ML1-OM' }}',
    selectedMachineId: {{ $currentMachine?->id ?? 1 }},
    selectedDate: '{{ $selectedDate }}',
    selectMachine(name, id) {
        this.selectedMachineName = name;
        this.selectedMachineId = id;
    }
}">

    <!-- Info Notice Banner -->
    <div class="bg-white border-l-4 border-[#00897b] p-3.5 rounded shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div class="flex items-center space-x-3">
            <div class="w-5 h-5 rounded-full bg-[#00897b]/10 text-[#00897b] flex items-center justify-center font-bold text-xs shrink-0">
                i
            </div>
            <span class="text-xs text-slate-700">
                Réservations ouvertes pour la semaine en cours (jusqu'à dimanche 23h59). Vos crédits se réinitialisent chaque lundi.
            </span>
        </div>

        @if(!auth()->check() || !auth()->user()->isAdmin())
            <div class="hidden sm:flex items-center space-x-2 text-xs font-semibold px-2.5 py-1 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">
                <span>Quota restant :</span>
                <span class="font-bold">{{ auth()->check() ? auth()->user()->weeklyRemainingLimit() : 8 }} / 8 heures</span>
            </div>
        @else
            <div class="hidden sm:flex items-center space-x-2 text-xs font-semibold px-2.5 py-1 rounded bg-amber-50 text-amber-800 border border-amber-200">
                <span>Régime :</span>
                <span class="font-bold">Admin (Illimité)</span>
            </div>
        @endif
    </div>

    <!-- Active cycles bar if any -->
    @if(isset($activeCycles) && $activeCycles->isNotEmpty())
        <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-xs">
            <h3 class="text-xs font-bold uppercase tracking-wider text-[#00897b] mb-2 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span>Vos cycles en cours</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($activeCycles as $cycle)
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-slate-800 text-sm">{{ $cycle->machine->name }}</span>
                            <span class="text-xs text-slate-500 block">Fin prévue: {{ $cycle->end_time->format('H:i') }}</span>
                        </div>
                        <x-status-badge :status="$cycle->status" />
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Search Input -->
    <div class="max-w-md mx-auto">
        <input type="text" x-model="search" placeholder="Rechercher une machine…" aria-label="Rechercher une machine" autocomplete="off"
               class="w-full px-4 py-2 bg-white border border-slate-300 rounded text-xs placeholder-slate-400 focus:outline-none focus:border-[#00897b] focus-visible:ring-2 focus-visible:ring-[#00897b]/40 shadow-xs">
    </div>

    <!-- Machine Selection Table Card -->
    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden max-w-2xl mx-auto">
        <!-- Dual Column Header -->
        <div class="grid grid-cols-2 bg-[#00897b] text-white text-xs font-bold text-center py-2.5">
            <div>Machines à laver ({{ $washers->count() }})</div>
            <div>Sèche-linge ({{ $dryers->count() }})</div>
        </div>

        <!-- Matrix Content -->
        <div class="grid grid-cols-2 divide-x divide-slate-200 p-4">
            
            <!-- Column 1: Machine à laver (Washers) -->
            <div class="grid grid-cols-3 gap-2 pr-3">
                @forelse($washers as $machine)
                    @php
                        $mColor = $machineColorPalette[$machine->name ?? ''] ?? $machine->color ?? '#4338ca';
                    @endphp
                    <button type="button" @click="selectMachine('{{ $machine->name }}', {{ $machine->id }})" 
                            :class="{'ring-3 ring-slate-900 scale-105 shadow-md': selectedMachineId === {{ $machine->id }}}"
                            style="background-color: {{ $mColor }};"
                            class="badge-machine text-white hover:opacity-90">
                        <span class="truncate">{{ $machine->name }}</span>
                    </button>
                @empty
                    <p class="text-xs text-slate-400 col-span-3 text-center py-2">Aucun lave-linge</p>
                @endforelse
            </div>

            <!-- Column 2: Sèche-linge (Dryers) -->
            <div class="grid grid-cols-2 gap-2 pl-3">
                @forelse($dryers as $machine)
                    @php
                        $mColor = $machineColorPalette[$machine->name ?? ''] ?? $machine->color ?? '#b45309';
                    @endphp
                    <button type="button" @click="selectMachine('{{ $machine->name }}', {{ $machine->id }})"
                            :class="{'ring-3 ring-slate-900 scale-105 shadow-md': selectedMachineId === {{ $machine->id }}}"
                            style="background-color: {{ $mColor }};"
                            class="badge-machine text-white hover:opacity-90">
                        <span class="truncate">{{ $machine->name }}</span>
                    </button>
                @empty
                    <p class="text-xs text-slate-400 col-span-2 text-center py-2">Aucun sèche-linge</p>
                @endforelse
            </div>

        </div>
    </div>

    <!-- Action Button Réserver (Direct Link to Dedicated Page) -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 max-w-2xl mx-auto bg-slate-50 p-3.5 rounded-lg border border-slate-200 shadow-xs">
        <div class="text-xs text-slate-600">
            Machine sélectionnée : <span class="font-bold text-[#00897b] bg-[#00897b]/10 px-2.5 py-1 rounded text-sm" x-text="selectedMachineName"></span>
        </div>
        <div>
            <a :href="'{{ route('bookings.create') }}?machine_id=' + selectedMachineId" 
               class="px-6 py-2.5 rounded bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold transition-all shadow-md flex items-center space-x-2 cursor-pointer active:scale-95">
                <span>Réserver cette machine</span>
                <span>&rarr;</span>
            </a>
        </div>
    </div>

    <!-- Date Title & Working Navigation Controls (Aujourd'hui, Précédent, Suivant, All Days) -->
    <div class="space-y-3 pt-4 border-t border-slate-200">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <h2 class="text-xl font-normal text-slate-700 capitalize">
                    {{ $dayName }}, {{ $dateFormatted }}
                </h2>

                <!-- Direct Date Picker (Select Any Day) -->
                <div class="relative">
                    <input type="date" value="{{ $selectedDate }}" aria-label="Sélectionner une date"
                           @change="window.location.href = '{{ route('calendrier') }}?date=' + $event.target.value + '&machine_id=' + selectedMachineId" 
                           class="px-2.5 py-1 text-xs border border-slate-300 rounded bg-white text-slate-700 hover:border-[#00897b] focus:outline-none focus:border-[#00897b] focus-visible:ring-2 focus-visible:ring-[#00897b]/40 cursor-pointer shadow-xs font-medium"
                           title="Choisir une date quelconque">
                </div>
            </div>

            <!-- Navigation Buttons: Aujourd'hui, Précédent (Hier), Suivant (Demain) -->
            <div class="inline-flex rounded shadow-xs text-xs">
                <a :href="'{{ route('calendrier') }}?date={{ $todayDate }}&machine_id=' + selectedMachineId" 
                   class="px-3.5 py-1.5 {{ $selectedDate === $todayDate ? 'bg-[#00897b] text-white font-bold' : 'bg-[#546e7a] hover:bg-[#455a64] text-white font-medium' }} rounded-l transition-colors flex items-center">
                    Aujourd'hui
                </a>
                <a :href="'{{ route('calendrier') }}?date={{ $prevDate }}&machine_id=' + selectedMachineId" 
                   class="px-3.5 py-1.5 bg-[#37474f] hover:bg-[#263238] text-white font-medium transition-colors flex items-center space-x-1"
                   title="Jour précédent ({{ $prevDate }})">
                    <span>&larr;</span>
                    <span>Précédent</span>
                </a>
                <a :href="'{{ route('calendrier') }}?date={{ $nextDate }}&machine_id=' + selectedMachineId" 
                   class="px-3.5 py-1.5 bg-[#263238] hover:bg-black text-white font-medium rounded-r transition-colors flex items-center space-x-1"
                   title="Jour suivant ({{ $nextDate }})">
                    <span>Suivant</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <!-- 7-Day Week Selector (All Days Quick-Jump Strip) -->
        @if(isset($weekDays) && count($weekDays) > 0)
            <div class="grid grid-cols-7 gap-1 sm:gap-2 p-1.5 bg-white rounded-lg border border-slate-200 shadow-xs">
                @foreach($weekDays as $wDay)
                    <a :href="'{{ route('calendrier') }}?date={{ $wDay['date'] }}&machine_id=' + selectedMachineId"
                       class="py-2 px-1 text-center rounded transition-all flex flex-col items-center justify-center {{ $wDay['isSelected'] ? 'bg-[#00897b] text-white font-bold shadow-xs scale-102' : 'hover:bg-slate-100 text-slate-700' }}">
                        <span class="text-[10px] uppercase font-semibold {{ $wDay['isSelected'] ? 'text-emerald-100' : 'text-slate-400' }}">
                            {{ $wDay['shortName'] }}
                        </span>
                        <span class="text-sm font-bold {{ $wDay['isSelected'] ? 'text-white' : ($wDay['isToday'] ? 'text-[#00897b]' : 'text-slate-800') }}">
                            {{ $wDay['dayNumber'] }}
                        </span>
                        @if($wDay['isToday'])
                            <span class="w-1.5 h-1.5 rounded-full {{ $wDay['isSelected'] ? 'bg-white' : 'bg-[#00897b]' }} mt-0.5"></span>
                        @else
                            <span class="w-1.5 h-1.5 mt-0.5"></span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Timetable / Calendar Timeline Grid with Continuous Blocks & Limitor Lines -->
    <div class="bg-white rounded-lg border border-slate-300 shadow-xs overflow-hidden">
        
        <div class="flex border-b border-slate-300 bg-[#f9f9e8] text-xs font-semibold text-slate-700">
            <div class="w-16 sm:w-20 p-2.5 text-center border-r border-slate-300 text-[11px] text-slate-500 font-semibold tracking-tight">
                Horaires
            </div>
            <div class="flex-1 p-2.5 text-center font-bold text-slate-800 capitalize flex items-center justify-center space-x-2">
                <span>{{ $dayName }} ({{ $dateFormatted }})</span>
                @if(isset($calendarBlocks) && count($calendarBlocks) > 0)
                    <span class="text-[10px] font-normal text-slate-500 bg-white/80 px-2 py-0.5 rounded border border-slate-200">
                        {{ count($calendarBlocks) }} réservation{{ count($calendarBlocks) > 1 ? 's' : '' }}
                    </span>
                @endif
            </div>
        </div>

        <div class="relative overflow-x-auto">
            <div class="flex min-w-[620px] relative select-none">
                
                <!-- Left Axis: Hours of the Day directly ON the line as limitor indicators -->
                <div class="w-16 sm:w-20 shrink-0 border-r border-slate-300 bg-slate-50/70 relative select-none" style="height: {{ 24 * 52 }}px;">
                    @for ($h = 0; $h <= 24; $h++)
                        @php
                            $top = $h * 52;
                            $hourLabel = sprintf('%02d:00', $h === 24 ? 24 : $h);
                        @endphp
                        <div class="absolute right-0 pr-3 flex items-center -translate-y-1/2 pointer-events-none" style="top: {{ $top }}px;">
                            <span class="text-[11px] font-bold text-slate-500 font-mono tracking-tight">{{ $hourLabel }}</span>
                        </div>
                    @endfor
                </div>

                <!-- Schedule Area: Horizontal Limitor Lines & Continuous Blocks -->
                <div class="flex-1 relative bg-white" style="height: {{ 24 * 52 }}px;">
                    
                    <!-- Background: 24 hour rows with click-to-book and horizontal divider lines -->
                    @php
                        $endOfWeekDate = \Carbon\Carbon::now()->endOfWeek()->toDateString();
                        $isReservableDay = ($selectedDate >= $todayDate && $selectedDate <= $endOfWeekDate);
                        $currentHour = (int)\Carbon\Carbon::now()->format('H');
                    @endphp
                    @for ($h = 0; $h < 24; $h++)
                        @php
                            $top = $h * 52;
                            $hourStr = sprintf('%02d:00', $h);
                            $isPastSlot = ($selectedDate === $todayDate && $h < $currentHour) || ($selectedDate < $todayDate);
                            $canBook = $isReservableDay && !$isPastSlot;
                        @endphp
                        <!-- Limitor boundary line at top of hour -->
                        <div class="absolute left-0 right-0 border-t border-slate-200 pointer-events-none" style="top: {{ $top }}px;"></div>
                        
                        <!-- Mid-hour subtle 30m dashed guide line -->
                        <div class="absolute left-0 right-0 border-t border-dashed border-slate-100 pointer-events-none" style="top: {{ $top + 26 }}px;"></div>

                        @if($canBook)
                            <!-- Clickable / Hoverable hour row slot -->
                            <a :href="'{{ route('bookings.create') }}?machine_id=' + selectedMachineId + '&start_time={{ $selectedDate }}T{{ sprintf('%02d', $h) }}:00:00'" 
                               class="absolute left-0 right-0 h-[52px] hover:bg-slate-50/60 transition-colors group cursor-pointer"
                               style="top: {{ $top }}px;"
                               title="Cliquer pour réserver le créneau {{ $hourStr }}">
                                <div class="w-full h-full flex items-center px-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <span class="text-[10px] text-slate-400 font-medium">+ Réserver à {{ $hourStr }}</span>
                                </div>
                            </a>
                        @endif
                    @endfor
                    <!-- Final boundary line at bottom (24:00) -->
                    <div class="absolute left-0 right-0 border-t border-slate-300 pointer-events-none" style="top: {{ 24 * 52 }}px;"></div>

                    <!-- Current System Time Indicator (Points to the hour of the system) -->
                    @php
                        $now = \Carbon\Carbon::now();
                        $nowMinutes = ($now->hour * 60) + $now->minute;
                        $nowTop = ($nowMinutes / 60) * 52;
                    @endphp
                    <div id="system-time-pointer"
                         x-data="systemTimePointer('{{ $selectedDate }}', '{{ $todayDate }}')"
                         x-show="isVisible"
                         class="absolute left-0 right-0 z-30 pointer-events-none flex items-center transition-all duration-300"
                         :style="'top: ' + topPx + 'px;'"
                         @if($selectedDate === $todayDate) style="top: {{ $nowTop }}px;" @else style="display: none;" @endif
                         title="Heure actuelle du système">
                        <div class="w-2.5 h-2.5 rounded-full bg-rose-500 shadow -ml-1.5 shrink-0 ring-2 ring-white"></div>
                        <div class="flex-1 border-t-2 border-rose-500 shadow-xs"></div>
                        <span class="bg-rose-500 text-white font-mono text-[9px] font-bold px-1.5 py-0.5 rounded shadow -mr-1 flex items-center space-x-1"
                              x-text="timeFormatted">
                            {{ $now->format('H:i') }}
                        </span>
                    </div>

                    <!-- Render Continuous Blocks -->
                    @forelse($calendarBlocks ?? [] as $block)
                        @php
                            $isMultiHour = $block['durationMinutes'] > 60;
                            $machineColor = $machineColorPalette[$block['machine']->name ?? ''] ?? $block['machine']->color ?? '#4338ca';
                        @endphp
                        <div style="background-color: {{ $machineColor }}; top: {{ $block['top'] + 1 }}px; height: {{ $block['height'] - 2 }}px; left: calc({{ $block['leftPct'] }}% + 4px); width: calc({{ $block['widthPct'] }}% - 8px);"
                             :class="{'ring-3 ring-slate-900 shadow-xl scale-[1.01] z-30': selectedMachineId === {{ $block['machine_id'] }}, 'shadow-sm hover:shadow-md hover:brightness-105 z-20': selectedMachineId !== {{ $block['machine_id'] }}}"
                             class="absolute rounded-md text-white overflow-hidden transition-all cursor-pointer border border-white/20 select-none"
                             @click.stop="selectMachine('{{ $block['machine']->name }}', {{ $block['machine_id'] }})"
                             title="{{ $block['machine']->name }} • {{ $block['timeFormatted'] }} ({{ $block['user']?->name ?? 'Occupé' }}) - Cliquer pour sélectionner la machine">
                            
                            <div class="h-full p-2 flex flex-col justify-center">
                                <div class="flex items-center justify-between text-xs leading-tight">
                                    <div class="flex items-center space-x-1.5 truncate">
                                        <span class="font-mono font-bold text-[11px] bg-black/25 px-1.5 py-0.5 rounded">{{ $block['timeFormatted'] }}</span>
                                        <span class="opacity-60">•</span>
                                        <span class="font-bold text-[12px] truncate">{{ $block['machine']->name }}</span>
                                        @if($isMultiHour)
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-black/25 uppercase tracking-wider">{{ $block['durationFormatted'] }}</span>
                                        @endif
                                    </div>
                                    @if($block['user'])
                                        <span class="opacity-90 text-[11px] font-medium truncate max-w-[140px] ml-2">
                                            ({{ $block['user']->name }})
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <!-- Empty Day State Overlay -->
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div class="bg-white/90 border border-slate-200 shadow-sm rounded-lg p-4 text-center max-w-sm">
                                <svg aria-hidden="true" class="w-8 h-8 mx-auto mb-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-xs font-bold text-slate-700 block">Aucune réservation pour cette journée</span>
                                <span class="text-[11px] text-slate-500 block mt-1">Cliquez sur un créneau horaire ou sélectionnez une machine ci-dessus pour réserver.</span>
                            </div>
                        </div>
                    @endforelse

                </div>
            </div>
        </div>
    </div>

</div>
@endsection
