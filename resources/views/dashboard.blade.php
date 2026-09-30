@extends('layouts.app')

@section('content')
@php
    $washers = $machines->filter(fn($m) => in_array($m->type instanceof \App\Enums\MachineType ? $m->type->value : (string)$m->type, ['washing-machine', 'washer']));
    $dryers = $machines->filter(fn($m) => in_array($m->type instanceof \App\Enums\MachineType ? $m->type->value : (string)$m->type, ['dryer']));
    $firstMachine = $machines->first();
    $currentSelectedMachineId = request('machine_id', $firstMachine?->id ?? 1);
    $currentMachine = $machines->firstWhere('id', $currentSelectedMachineId) ?? $firstMachine;
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
    <div class="bg-white border-l-4 border-[#00897b] p-3.5 rounded shadow-xs flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-5 h-5 rounded-full bg-[#00897b]/10 text-[#00897b] flex items-center justify-center font-bold text-xs shrink-0">
                i
            </div>
            <span class="text-xs text-slate-700">
                Sélectionnez une machine pour consulter son planning ou réserver un créneau.
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
        <input type="text" x-model="search" placeholder="Rechercher une machine..." 
               class="w-full px-4 py-2 bg-white border border-slate-300 rounded text-xs placeholder-slate-400 focus:outline-none focus:border-[#00897b] shadow-xs">
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
                    <button type="button" @click="selectMachine('{{ $machine->name }}', {{ $machine->id }})" 
                            :class="{'ring-3 ring-slate-900 scale-105 shadow-md': selectedMachineId === {{ $machine->id }}}"
                            style="background-color: {{ $machine->color ?? '#00897b' }};"
                            class="badge-machine text-white hover:opacity-90">
                        <span class="truncate">{{ $machine->name }}</span>
                        <span class="text-sm">👕</span>
                    </button>
                @empty
                    <p class="text-xs text-slate-400 col-span-3 text-center py-2">Aucun lave-linge</p>
                @endforelse
            </div>

            <!-- Column 2: Sèche-linge (Dryers) -->
            <div class="grid grid-cols-2 gap-2 pl-3">
                @forelse($dryers as $machine)
                    <button type="button" @click="selectMachine('{{ $machine->name }}', {{ $machine->id }})"
                            :class="{'ring-3 ring-slate-900 scale-105 shadow-md': selectedMachineId === {{ $machine->id }}}"
                            style="background-color: {{ $machine->color ?? '#263238' }};"
                            class="badge-machine text-white hover:opacity-90">
                        <span class="truncate">{{ $machine->name }}</span>
                        <span class="text-xs">🔄</span>
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
                    <input type="date" value="{{ $selectedDate }}" 
                           @change="window.location.href = '{{ route('calendrier') }}?date=' + $event.target.value + '&machine_id=' + selectedMachineId" 
                           class="px-2.5 py-1 text-xs border border-slate-300 rounded bg-white text-slate-700 hover:border-[#00897b] focus:outline-none focus:border-[#00897b] cursor-pointer shadow-xs font-medium"
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

    <!-- Timetable / Calendar Timeline Grid -->
    <div class="bg-white rounded-lg border border-slate-300 shadow-xs overflow-hidden">
        
        <div class="flex border-b border-slate-300 bg-[#f9f9e8] text-xs font-semibold text-slate-700">
            <div class="w-16 p-2 text-center border-r border-slate-300 text-[11px] text-slate-500">
                Toute la journée
            </div>
            <div class="flex-1 p-2 text-center font-bold text-slate-800 capitalize">
                {{ $dayName }} ({{ $dateFormatted }})
            </div>
        </div>

        <div class="divide-y divide-slate-200 text-xs">
            @for ($h = 0; $h < 24; $h++)
                @php
                    $hourStr = sprintf('%02d h', $h);
                    $matching = $reservationsByHour[$h] ?? collect();
                @endphp
                <div class="flex items-center min-h-[42px] py-1 hover:bg-slate-50/60 transition-colors">
                    <div class="timeline-hour">{{ $hourStr }}</div>
                    <div class="flex-1 px-2 h-full flex flex-wrap items-center gap-2">
                        @forelse($matching as $res)
                            <div style="background-color: {{ $res->machine->color ?? '#00897b' }};" 
                                 :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachineId === {{ $res->machine_id }}}"
                                 class="h-7 rounded text-white font-bold text-[11px] px-2.5 flex items-center space-x-2 shadow-xs transition-all cursor-pointer"
                                 @click="selectMachine('{{ $res->machine->name }}', {{ $res->machine_id }})"
                                 title="Machine: {{ $res->machine->name }} (Cliquer pour sélectionner)">
                                <span>{{ $res->start_time->format('H:i') }} - {{ $res->end_time->format('H:i') }}</span>
                                <span>•</span>
                                <span>{{ $res->machine->name }}</span>
                                @if($res->user)
                                    <span class="opacity-80 text-[10px] font-normal">({{ $res->user->name }})</span>
                                @endif
                            </div>
                        @empty
                            <div class="w-full h-full border-t border-dashed border-slate-200"></div>
                        @endforelse
                    </div>
                </div>
            @endfor
        </div>
    </div>

</div>
@endsection
