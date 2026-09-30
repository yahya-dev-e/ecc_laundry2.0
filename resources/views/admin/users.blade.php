@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <h1 class="text-xl font-bold text-slate-800 tracking-tight">Gestion des Utilisateurs</h1>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Admin Uniquement</span>
            </div>
            <p class="text-xs text-slate-500">Supervision des comptes et suivi des quotas de réservation par semaine</p>
        </div>
        <button class="px-4 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded shadow-xs">
            + Ajouter un utilisateur
        </button>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left text-xs">
            <thead class="bg-[#00897b] text-white">
                <tr>
                    <th class="p-3.5 font-bold">Nom de l'utilisateur</th>
                    <th class="p-3.5 font-bold">Email Institutionnel</th>
                    <th class="p-3.5 font-bold">Identifiant / Chambre</th>
                    <th class="p-3.5 font-bold">Rôle</th>
                    <th class="p-3.5 font-bold">Quota Hebdomadaire</th>
                    <th class="p-3.5 font-bold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-3.5 font-bold text-slate-800 flex items-center space-x-2">
                        <span class="w-7 h-7 rounded-full bg-[#00897b] text-white flex items-center justify-center font-bold text-[11px]">R</span>
                        <span>R. Omari</span>
                    </td>
                    <td class="p-3.5 text-slate-600">r.omari@fecc.ma</td>
                    <td class="p-3.5 text-slate-500 font-mono">ADM-001 (Direction Campus)</td>
                    <td class="p-3.5"><span class="px-2.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px]">Administrateur</span></td>
                    <td class="p-3.5 font-bold text-[#00897b]">Illimité</td>
                    <td class="p-3.5 text-right">
                        <button class="text-slate-400 hover:text-slate-600 text-xs font-semibold">Modifier</button>
                    </td>
                </tr>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-3.5 font-bold text-slate-800 flex items-center space-x-2">
                        <span class="w-7 h-7 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-[11px]">A</span>
                        <span>Alex Rivera</span>
                    </td>
                    <td class="p-3.5 text-slate-600">alex.rivera@fecc.ma</td>
                    <td class="p-3.5 text-slate-500 font-mono">STU-98241 (Bât. Omar, Ch. 214)</td>
                    <td class="p-3.5"><span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">Étudiant</span></td>
                    <td class="p-3.5 font-bold text-emerald-600">1 / 3 utilisée (2 restantes)</td>
                    <td class="p-3.5 text-right space-x-2">
                        <button class="text-[#00897b] hover:underline font-semibold">Réinitialiser quota</button>
                        <button class="text-slate-400 hover:text-slate-600 font-semibold">Modifier</button>
                    </td>
                </tr>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-3.5 font-bold text-slate-800 flex items-center space-x-2">
                        <span class="w-7 h-7 rounded-full bg-purple-500 text-white flex items-center justify-center font-bold text-[11px]">S</span>
                        <span>Sara Bennani</span>
                    </td>
                    <td class="p-3.5 text-slate-600">sara.bennani@fecc.ma</td>
                    <td class="p-3.5 text-slate-500 font-mono">STU-88219 (Bât. Petit, Ch. 108)</td>
                    <td class="p-3.5"><span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">Étudiant</span></td>
                    <td class="p-3.5 font-bold text-amber-600">2 / 3 utilisées (1 restante)</td>
                    <td class="p-3.5 text-right space-x-2">
                        <button class="text-[#00897b] hover:underline font-semibold">Réinitialiser quota</button>
                        <button class="text-slate-400 hover:text-slate-600 font-semibold">Modifier</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
