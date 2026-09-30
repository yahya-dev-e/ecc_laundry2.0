@extends('layouts.app')

@section('content')
@php
    $washers = $machines->filter(fn($m) => in_array($m->type instanceof \App\Enums\MachineType ? $m->type->value : (string)$m->type, ['washing-machine', 'washer']));
    $dryers = $machines->filter(fn($m) => in_array($m->type instanceof \App\Enums\MachineType ? $m->type->value : (string)$m->type, ['dryer']));
    $firstMachine = $machines->first();
@endphp

<div class="space-y-6 max-w-6xl mx-auto" x-data="{
    search: '',
    selectedMachine: '{{ $firstMachine?->name ?? 'ML1-OM' }}',
    selectedMachineId: {{ $firstMachine?->id ?? 1 }},
    selectMachine(name, id) {
        this.selectedMachine = name;
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
                Cliquez sur une machine pour voir les créneaux déjà réservés.
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
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl text-white">
            <h3 class="text-xs font-bold uppercase tracking-wider text-cyan-400 mb-2 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                Vos cycles en cours
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($activeCycles as $cycle)
                    <div class="bg-slate-800/80 p-3 rounded-lg border border-slate-700 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-white text-sm">{{ $cycle->machine->name }}</span>
                            <span class="text-xs text-slate-400 block">Fin prévue: {{ $cycle->end_time->format('H:i') }}</span>
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
            <div>Machine à laver ({{ $washers->count() }})</div>
            <div>Sèche-linge ({{ $dryers->count() }})</div>
        </div>

        <!-- Matrix Content -->
        <div class="grid grid-cols-2 divide-x divide-slate-200 p-4">
            
            <!-- Column 1: Machine à laver (Washers) -->
            <div class="grid grid-cols-3 gap-2 pr-3">
                @forelse($washers as $machine)
                    <button type="button" @click="selectMachine('{{ $machine->name }}', {{ $machine->id }})" 
                            :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === '{{ $machine->name }}'}"
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
                            :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === '{{ $machine->name }}'}"
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
            Machine sélectionnée : <span class="font-bold text-[#00897b] bg-[#00897b]/10 px-2.5 py-1 rounded text-sm" x-text="selectedMachine"></span>
        </div>
        <div>
            <a :href="'{{ route('bookings.create') }}?machine_id=' + selectedMachineId" 
               class="px-6 py-2.5 rounded bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold transition-all shadow-md flex items-center space-x-2 cursor-pointer active:scale-95">
                <span>Réserver cette machine</span>
                <span>&rarr;</span>
            </a>
        </div>
    </div>

    <!-- Date Title & Navigation Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-4 border-t border-slate-200">
        <h2 class="text-xl font-normal text-slate-700">
            {{ now()->isoFormat('D MMMM YYYY') }}
        </h2>

        <div class="inline-flex rounded shadow-xs text-xs">
            <button class="px-3.5 py-1.5 bg-[#546e7a] hover:bg-[#455a64] text-white font-medium rounded-l">
                Aujourd'hui
            </button>
            <button class="px-3.5 py-1.5 bg-[#37474f] hover:bg-[#263238] text-white font-medium">
                Précédent
            </button>
            <button class="px-3.5 py-1.5 bg-[#263238] hover:bg-black text-white font-medium rounded-r">
                Suivant
            </button>
        </div>
    </div>

    <!-- Timetable / Calendar Timeline Grid -->
    <div class="bg-white rounded-lg border border-slate-300 shadow-xs overflow-hidden">
        
        <div class="flex border-b border-slate-300 bg-[#f9f9e8] text-xs font-semibold text-slate-700">
            <div class="w-16 p-2 text-center border-r border-slate-300 text-[11px] text-slate-500">
                Toute la journée
            </div>
            <div class="flex-1 p-2 text-center font-bold text-slate-800">
                {{ now()->isoFormat('dddd') }}
            </div>
        </div>

        <div class="divide-y divide-slate-200 text-xs">
            @foreach(['00 h', '01 h', '02 h', '03 h', '04 h', '05 h', '06 h', '07 h', '08 h', '09 h', '10 h', '11 h', '12 h', '13 h', '14 h', '15 h', '16 h', '17 h', '18 h', '19 h', '20 h', '21 h', '22 h', '23 h'] as $hour)
                <div class="flex items-center h-9 hover:bg-slate-50/50">
                    <div class="timeline-hour">{{ $hour }}</div>
                    <div class="flex-1 h-full border-t border-dashed border-slate-200"></div>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
