@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Réclamations & Signalements</h1>
            <p class="text-xs text-slate-500">Suivi des incidents, pannes de machines et demandes d'assistance campus</p>
        </div>
        <button class="px-4 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded shadow-xs">
            + Nouveau signalement
        </button>
    </div>

    <!-- Complaints List -->
    <div class="space-y-3">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-slate-200 flex items-start justify-between">
            <div class="flex items-start space-x-3">
                <span class="p-2 rounded bg-amber-100 text-amber-800 font-bold text-xs shrink-0">
                    ML3-OM
                </span>
                <div>
                    <h3 class="text-xs font-bold text-slate-800">Problème d'évacuation d'eau en fin de cycle</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Signalé par: Sara Bennani (Bât. Petit) • Il y a 2 heures</p>
                    <p class="text-xs text-slate-600 mt-2 bg-slate-50 p-2.5 rounded border border-slate-100">
                        "La machine s'est arrêtée avec le voyant filtre allumé. L'eau ne s'est pas complètement vidée."
                    </p>
                </div>
            </div>
            <span class="px-2.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-bold">En cours de traitement</span>
        </div>

        <div class="bg-white p-4 rounded-lg shadow-sm border border-slate-200 flex items-start justify-between">
            <div class="flex items-start space-x-3">
                <span class="p-2 rounded bg-emerald-100 text-emerald-800 font-bold text-xs shrink-0">
                    SL2-PE
                </span>
                <div>
                    <h3 class="text-xs font-bold text-slate-800">Nettoyage du filtre à peluches requis</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Signalé par: Alex Rivera • Hier à 18:30</p>
                    <p class="text-xs text-slate-600 mt-2 bg-slate-50 p-2.5 rounded border border-slate-100">
                        "Filtre saturé, séchage moins efficace."
                    </p>
                </div>
            </div>
            <span class="px-2.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold">Résolu</span>
        </div>
    </div>
</div>
@endsection
