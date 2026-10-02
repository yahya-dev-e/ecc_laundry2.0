@extends('layouts.app')

@section('content')
@php
    $allMachines = \App\Models\Machine::orderBy('name')->get();
    
    $washers = $allMachines->filter(fn($m) => in_array($m->type instanceof \App\Enums\MachineType ? $m->type->value : (string)$m->type, ['washing-machine', 'washer']));
    $dryers = $allMachines->filter(fn($m) => in_array($m->type instanceof \App\Enums\MachineType ? $m->type->value : (string)$m->type, ['dryer']));

    // Fallback if database hasn't been seeded yet
    if ($allMachines->isEmpty()) {
        $washers = collect([
            (object)['id' => 1, 'name' => 'ML1-OM', 'type' => 'washing-machine', 'status' => 'available', 'color' => '#4338ca'],
            (object)['id' => 2, 'name' => 'ML2-OM', 'type' => 'washing-machine', 'status' => 'in-use', 'color' => '#0d9488'],
            (object)['id' => 3, 'name' => 'ML1-PE', 'type' => 'washing-machine', 'status' => 'available', 'color' => '#2563eb'],
            (object)['id' => 4, 'name' => 'ML2-PE', 'type' => 'washing-machine', 'status' => 'reserved', 'color' => '#d97706'],
            (object)['id' => 5, 'name' => 'ML3-PE', 'type' => 'washing-machine', 'status' => 'reserved', 'color' => '#db2777'],
            (object)['id' => 6, 'name' => 'ML4-PE', 'type' => 'washing-machine', 'status' => 'available', 'color' => '#ea580c'],
            (object)['id' => 7, 'name' => 'ML3-OM', 'type' => 'washing-machine', 'status' => 'available', 'color' => '#059669'],
        ]);
        $dryers = collect([
            (object)['id' => 8, 'name' => 'SL1-OM', 'type' => 'dryer', 'status' => 'available', 'color' => '#b45309'],
            (object)['id' => 9, 'name' => 'SL2-OM', 'type' => 'dryer', 'status' => 'available', 'color' => '#16a34a'],
            (object)['id' => 10, 'name' => 'SL1-PE', 'type' => 'dryer', 'status' => 'in-use', 'color' => '#475569'],
            (object)['id' => 11, 'name' => 'SL2-PE', 'type' => 'dryer', 'status' => 'available', 'color' => '#65a30d'],
            (object)['id' => 12, 'name' => 'SL3-PE', 'type' => 'dryer', 'status' => 'available', 'color' => '#9333ea'],
            (object)['id' => 13, 'name' => 'SL3-OM', 'type' => 'dryer', 'status' => 'available', 'color' => '#52525b'],
        ]);
    }
@endphp

<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Parc des Machines</h1>
            <p class="text-xs text-slate-500">État en temps réel des machines à laver et sèche-linge du campus Centrale Casablanca</p>
        </div>
        @if(auth()->check() && auth()->user()->isAdmin())
            <span class="px-3 py-1 bg-[#00897b]/10 text-[#00897b] font-bold text-xs rounded border border-[#00897b]/30">
                Administration Activée
            </span>
        @endif
    </div>

    <!-- Machine Grid grouped by Washer vs Dryer -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Machines à laver -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-[#00897b] text-white px-4 py-3 flex items-center justify-between">
                <span class="font-bold text-sm">Machines à laver ({{ $washers->count() }} unités)</span>
                <span class="text-xs font-mono bg-white/20 px-2 py-0.5 rounded">ML</span>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($washers as $w)
                    <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition-colors">
                        <div class="flex items-center space-x-3">
                            <span style="background-color: {{ $w->color ?? '#00897b' }};" class="w-8 h-8 rounded text-white font-black text-xs flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" stroke-width="2"/><circle cx="12" cy="14" r="4" stroke-width="2"/><circle cx="8" cy="6" r="1" fill="currentColor"/></svg>
                            </span>
                            <div>
                                <span class="font-bold text-xs text-slate-800">{{ $w->name }}</span>
                                @if($w->color)
                                    <span class="text-[11px] text-slate-400 font-mono ml-1">• {{ $w->color }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <x-status-badge :status="$w->status" />
                            @if(isset($w->id))
                                <a href="{{ route('bookings.create', ['machine_id' => $w->id]) }}" class="text-xs font-semibold px-2 py-1 bg-slate-100 hover:bg-[#00897b] hover:text-white rounded text-slate-700 transition-colors">
                                    Réserver
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Sèche-linge -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-[#00897b] text-white px-4 py-3 flex items-center justify-between">
                <span class="font-bold text-sm">Sèche-linge ({{ $dryers->count() }} unités)</span>
                <span class="text-xs font-mono bg-white/20 px-2 py-0.5 rounded">SL</span>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($dryers as $d)
                    <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition-colors">
                        <div class="flex items-center space-x-3">
                            <span style="background-color: {{ $d->color ?? '#263238' }};" class="w-8 h-8 rounded text-white font-black text-xs flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" stroke-width="2"/><circle cx="12" cy="13" r="5" stroke-dasharray="3 3" stroke-width="2"/><circle cx="12" cy="13" r="2" stroke-width="2"/></svg>
                            </span>
                            <div>
                                <span class="font-bold text-xs text-slate-800">{{ $d->name }}</span>
                                @if($d->color)
                                    <span class="text-[11px] text-slate-400 font-mono ml-1">• {{ $d->color }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <x-status-badge :status="$d->status" />
                            @if(isset($d->id))
                                <a href="{{ route('bookings.create', ['machine_id' => $d->id]) }}" class="text-xs font-semibold px-2 py-1 bg-slate-100 hover:bg-[#00897b] hover:text-white rounded text-slate-700 transition-colors">
                                    Réserver
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</div>
@endsection
