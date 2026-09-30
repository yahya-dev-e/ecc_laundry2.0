@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto" x-data="{
    search: '',
    selectedMachine: 'ML1-OM'
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
                <span class="font-bold">6 / 8 heures</span>
            </div>
        @else
            <div class="hidden sm:flex items-center space-x-2 text-xs font-semibold px-2.5 py-1 rounded bg-amber-50 text-amber-800 border border-amber-200">
                <span>Régime :</span>
                <span class="font-bold">Admin (Illimité)</span>
            </div>
        @endif
    </div>

    <!-- Search Input -->
    <div class="max-w-md mx-auto">
        <input type="text" x-model="search" placeholder="Rechercher une machine..." 
               class="w-full px-4 py-2 bg-white border border-slate-300 rounded text-xs placeholder-slate-400 focus:outline-none focus:border-[#00897b] shadow-xs">
    </div>

    <!-- Machine Selection Table Card matching Image 2 -->
    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden max-w-2xl mx-auto">
        <!-- Dual Column Header -->
        <div class="grid grid-cols-2 bg-[#00897b] text-white text-xs font-bold text-center py-2.5">
            <div>Machine à laver</div>
            <div>Sèche-linge</div>
        </div>

        <!-- Matrix Content -->
        <div class="grid grid-cols-2 divide-x divide-slate-200 p-4">
            
            <!-- Column 1: Machine à laver (Washers) -->
            <div class="grid grid-cols-3 gap-2 pr-3">
                <button type="button" @click="selectedMachine = 'ML1-OM'" 
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'ML1-OM'}"
                        class="badge-machine bg-[#e53935] hover:bg-[#d32f2f]">
                    <span>ML1-OM</span>
                    <span class="text-sm">👕</span>
                </button>

                <button type="button" @click="selectedMachine = 'ML2-OM'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'ML2-OM'}"
                        class="badge-machine bg-[#00e676] text-slate-900 hover:bg-[#00c853]">
                    <span>ML2-OM</span>
                    <span class="text-sm">👕</span>
                </button>

                <button type="button" @click="selectedMachine = 'ML1-PE'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'ML1-PE'}"
                        class="badge-machine bg-[#2979ff] hover:bg-[#2962ff]">
                    <span>ML1-PE</span>
                    <span class="text-sm">👕</span>
                </button>

                <button type="button" @click="selectedMachine = 'ML2-PE'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'ML2-PE'}"
                        class="badge-machine bg-[#ffd600] text-slate-900 hover:bg-[#ffab00]">
                    <span>ML2-PE</span>
                    <span class="text-sm">👕</span>
                </button>

                <button type="button" @click="selectedMachine = 'ML3-PE'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'ML3-PE'}"
                        class="badge-machine bg-[#ff007f] hover:bg-[#e91e63]">
                    <span>ML3-PE</span>
                    <span class="text-sm">👕</span>
                </button>

                <button type="button" @click="selectedMachine = 'ML4-PE'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'ML4-PE'}"
                        class="badge-machine bg-[#ff9100] hover:bg-[#ff6d00]">
                    <span>ML4-PE</span>
                    <span class="text-sm">👕</span>
                </button>

                <button type="button" @click="selectedMachine = 'ML3-OM'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'ML3-OM'}"
                        class="badge-machine bg-[#004d40] hover:bg-[#00332c]">
                    <span>ML3-OM</span>
                    <span class="text-sm">👕</span>
                </button>
            </div>

            <!-- Column 2: Sèche-linge (Dryers) -->
            <div class="grid grid-cols-2 gap-2 pl-3">
                <button type="button" @click="selectedMachine = 'SL1-OM'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'SL1-OM'}"
                        class="badge-machine bg-[#4e342e] hover:bg-[#3e2723]">
                    <span>SL1-OM</span>
                    <span class="text-xs">🔄</span>
                </button>

                <button type="button" @click="selectedMachine = 'SL2-OM'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'SL2-OM'}"
                        class="badge-machine bg-[#4caf50] hover:bg-[#388e3c]">
                    <span>SL2-OM</span>
                    <span class="text-xs">🔄</span>
                </button>

                <button type="button" @click="selectedMachine = 'SL1-PE'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'SL1-PE'}"
                        class="badge-machine bg-[#1a237e] hover:bg-[#0d47a1]">
                    <span>SL1-PE</span>
                    <span class="text-xs">🔄</span>
                </button>

                <button type="button" @click="selectedMachine = 'SL2-PE'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'SL2-PE'}"
                        class="badge-machine bg-[#827717] hover:bg-[#9e9d24]">
                    <span>SL2-PE</span>
                    <span class="text-xs">🔄</span>
                </button>

                <button type="button" @click="selectedMachine = 'SL3-PE'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'SL3-PE'}"
                        class="badge-machine bg-[#8e24aa] hover:bg-[#7b1fa2]">
                    <span>SL3-PE</span>
                    <span class="text-xs">🔄</span>
                </button>

                <button type="button" @click="selectedMachine = 'SL3-OM'"
                        :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === 'SL3-OM'}"
                        class="badge-machine bg-[#212121] hover:bg-black">
                    <span>SL3-OM</span>
                    <span class="text-xs">🔄</span>
                </button>
            </div>

        </div>
    </div>

    <!-- Action Button Réserver (Direct Link to Dedicated Page) -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 max-w-2xl mx-auto bg-slate-50 p-3.5 rounded-lg border border-slate-200 shadow-xs">
        <div class="text-xs text-slate-600">
            Machine sélectionnée : <span class="font-bold text-[#00897b] bg-[#00897b]/10 px-2.5 py-1 rounded text-sm" x-text="selectedMachine">ML1-OM</span>
        </div>
        <div>
            <a :href="'/reserver?machine=' + encodeURIComponent(selectedMachine)" 
               class="px-6 py-2.5 rounded bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold transition-all shadow-md flex items-center space-x-2 cursor-pointer active:scale-95">
                <span>Réserver cette machine</span>
                <span>&rarr;</span>
            </a>
        </div>
    </div>

    <!-- Date Title & Navigation Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-4 border-t border-slate-200">
        <h2 class="text-xl font-normal text-slate-700">
            30 septembre 2026
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
                mercredi
            </div>
        </div>

        <div class="divide-y divide-slate-200 text-xs">
            
            <!-- 00 h -->
            <div class="flex items-center h-10 hover:bg-slate-50/50">
                <div class="timeline-hour">00 h</div>
                <div class="flex-1 px-1 h-full flex items-center">
                    <div class="w-full h-7 rounded bg-[#00e676] text-slate-900 font-bold text-[11px] px-3 flex items-center shadow-xs">
                        0:00 - 1:00 • ML2-OM
                    </div>
                </div>
            </div>

            <!-- 01 h - 05 h -->
            @foreach(['01 h', '02 h', '03 h', '04 h', '05 h'] as $hour)
                <div class="flex items-center h-9 hover:bg-slate-50/50">
                    <div class="timeline-hour">{{ $hour }}</div>
                    <div class="flex-1 h-full border-t border-dashed border-slate-200"></div>
                </div>
            @endforeach

            <!-- 06 h -->
            <div class="flex items-center h-10 hover:bg-slate-50/50">
                <div class="timeline-hour">06 h</div>
                <div class="flex-1 px-1 h-full flex items-center">
                    <div class="w-full h-7 rounded bg-[#1a237e] text-white font-bold text-[11px] px-3 flex items-center shadow-xs">
                        6:00 - 7:00 • SL1-PE
                    </div>
                </div>
            </div>

            <!-- 07 h -->
            <div class="flex items-center h-10 hover:bg-slate-50/50">
                <div class="timeline-hour">07 h</div>
                <div class="flex-1 px-1 h-full flex items-center space-x-1">
                    <div class="flex-1 h-7 rounded bg-[#ffd600] text-slate-900 font-bold text-[11px] px-2 flex items-center truncate shadow-xs">
                        7:00 - 9:00 • ML2-PE
                    </div>
                    <div class="flex-1 h-7 rounded bg-[#ff007f] text-white font-bold text-[11px] px-2 flex items-center truncate shadow-xs">
                        7:00 - 9:00 • ML3-PE
                    </div>
                    <div class="flex-1 h-7 rounded bg-[#00e676] text-slate-900 font-bold text-[11px] px-2 flex items-center truncate shadow-xs">
                        7:00 - 9:00 • ML2-OM
                    </div>
                </div>
            </div>

            <!-- 08 h - 23 h -->
            @foreach(['08 h', '09 h', '10 h', '11 h', '12 h', '13 h', '14 h', '15 h', '16 h', '17 h', '18 h', '19 h', '20 h', '21 h', '22 h', '23 h'] as $hour)
                <div class="flex items-center h-9 hover:bg-slate-50/50">
                    <div class="timeline-hour">{{ $hour }}</div>
                    <div class="flex-1 h-full border-t border-dashed border-slate-200"></div>
                </div>
            @endforeach

        </div>
    </div>

</div>
@endsection
