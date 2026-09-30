@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Parc des Machines</h1>
            <p class="text-xs text-slate-500">État en temps réel des machines à laver et sèche-linge du campus Centrale Casablanca</p>
        </div>
        @if(auth()->check() && auth()->user()->isAdmin())
            <span class="px-3 py-1 bg-[#00897b]/10 text-[#00897b] font-bold text-xs rounded border border-[#00897b]/30">
                Administration Activer
            </span>
        @endif
    </div>

    <!-- Machine Grid grouped by Washer vs Dryer -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Machines à laver -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-[#00897b] text-white px-4 py-3 flex items-center justify-between">
                <span class="font-bold text-sm">Machines à laver (7 unités)</span>
                <span class="text-xs font-mono bg-white/20 px-2 py-0.5 rounded">ML</span>
            </div>
            <div class="divide-y divide-slate-100">
                @php
                    $washers = [
                        ['code' => 'ML1-OM', 'name' => 'Machine à laver 1 Omar', 'cap' => '9.0 kg', 'loc' => 'Bâtiment Omar, RDC', 'status' => 'Disponible', 'bg' => '#e53935'],
                        ['code' => 'ML2-OM', 'name' => 'Machine à laver 2 Omar', 'cap' => '9.0 kg', 'loc' => 'Bâtiment Omar, RDC', 'status' => 'En cours (cycle 40°C)', 'bg' => '#00e676'],
                        ['code' => 'ML1-PE', 'name' => 'Machine à laver 1 Petit', 'cap' => '8.0 kg', 'loc' => 'Bâtiment Petit, Étage 1', 'status' => 'Disponible', 'bg' => '#2979ff'],
                        ['code' => 'ML2-PE', 'name' => 'Machine à laver 2 Petit', 'cap' => '8.0 kg', 'loc' => 'Bâtiment Petit, Étage 1', 'status' => 'Réservé (07h-09h)', 'bg' => '#ffd600'],
                        ['code' => 'ML3-PE', 'name' => 'Machine à laver 3 Petit', 'cap' => '8.5 kg', 'loc' => 'Bâtiment Petit, Étage 1', 'status' => 'Réservé (07h-09h)', 'bg' => '#ff007f'],
                        ['code' => 'ML4-PE', 'name' => 'Machine à laver 4 Petit', 'cap' => '8.5 kg', 'loc' => 'Bâtiment Petit, Étage 2', 'status' => 'Disponible', 'bg' => '#ff9100'],
                        ['code' => 'ML3-OM', 'name' => 'Machine à laver 3 Omar', 'cap' => '10.0 kg', 'loc' => 'Bâtiment Omar, RDC', 'status' => 'Disponible', 'bg' => '#004d40'],
                    ];
                @endphp
                @foreach ($washers as $w)
                    <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition-colors">
                        <div class="flex items-center space-x-3">
                            <span style="background-color: {{ $w['bg'] }};" class="w-8 h-8 rounded text-white font-black text-xs flex items-center justify-center shrink-0">
                                👕
                            </span>
                            <div>
                                <span class="font-bold text-xs text-slate-800">{{ $w['code'] }}</span>
                                <span class="text-xs text-slate-500 ml-1">• {{ $w['name'] }}</span>
                                <p class="text-[11px] text-slate-400">{{ $w['loc'] }} • Capacité: {{ $w['cap'] }}</p>
                            </div>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded {{ str_contains($w['status'], 'Disponible') ? 'bg-emerald-100 text-emerald-800' : (str_contains($w['status'], 'Réservé') ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800') }}">
                            {{ $w['status'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Sèche-linge -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-[#00897b] text-white px-4 py-3 flex items-center justify-between">
                <span class="font-bold text-sm">Sèche-linge (6 unités)</span>
                <span class="text-xs font-mono bg-white/20 px-2 py-0.5 rounded">SL</span>
            </div>
            <div class="divide-y divide-slate-100">
                @php
                    $dryers = [
                        ['code' => 'SL1-OM', 'name' => 'Sèche-linge 1 Omar', 'cap' => '9.5 kg', 'loc' => 'Bâtiment Omar, RDC', 'status' => 'Disponible', 'bg' => '#4e342e'],
                        ['code' => 'SL2-OM', 'name' => 'Sèche-linge 2 Omar', 'cap' => '9.5 kg', 'loc' => 'Bâtiment Omar, RDC', 'status' => 'Disponible', 'bg' => '#4caf50'],
                        ['code' => 'SL1-PE', 'name' => 'Sèche-linge 1 Petit', 'cap' => '8.0 kg', 'loc' => 'Bâtiment Petit, Étage 1', 'status' => 'En cours (06h-07h)', 'bg' => '#1a237e'],
                        ['code' => 'SL2-PE', 'name' => 'Sèche-linge 2 Petit', 'cap' => '8.0 kg', 'loc' => 'Bâtiment Petit, Étage 1', 'status' => 'Disponible', 'bg' => '#827717'],
                        ['code' => 'SL3-PE', 'name' => 'Sèche-linge 3 Petit', 'cap' => '8.5 kg', 'loc' => 'Bâtiment Petit, Étage 2', 'status' => 'Disponible', 'bg' => '#8e24aa'],
                        ['code' => 'SL3-OM', 'name' => 'Sèche-linge 3 Omar', 'cap' => '9.5 kg', 'loc' => 'Bâtiment Omar, RDC', 'status' => 'Disponible', 'bg' => '#212121'],
                    ];
                @endphp
                @foreach ($dryers as $d)
                    <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition-colors">
                        <div class="flex items-center space-x-3">
                            <span style="background-color: {{ $d['bg'] }};" class="w-8 h-8 rounded text-white font-black text-xs flex items-center justify-center shrink-0">
                                🔄
                            </span>
                            <div>
                                <span class="font-bold text-xs text-slate-800">{{ $d['code'] }}</span>
                                <span class="text-xs text-slate-500 ml-1">• {{ $d['name'] }}</span>
                                <p class="text-[11px] text-slate-400">{{ $d['loc'] }} • Capacité: {{ $d['cap'] }}</p>
                            </div>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded {{ str_contains($d['status'], 'Disponible') ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                            {{ $d['status'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</div>
@endsection
