@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div>
        <div class="flex items-center space-x-2">
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Paramètres du Système Buanderie</h1>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Admin Uniquement</span>
        </div>
        <p class="text-xs text-slate-500">Configuration des règles de quotas hebdomadaires, délais et plages horaires</p>
    </div>

    <!-- Settings Card -->
    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-6 text-xs">
        
        <!-- Quotas Hebdomadaires (Replaces Credit System) -->
        <div>
            <h2 class="font-bold text-sm text-slate-800 border-b border-slate-200 pb-2 mb-4">
                Politique des Quotas Hebdomadaires
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nombre maximal de réservations par semaine</label>
                    <div class="flex items-center space-x-2">
                        <input type="number" value="3" min="1" max="10" class="w-24 px-3 py-2 border border-slate-300 rounded focus:border-[#00897b] focus:outline-none">
                        <span class="text-slate-500">réservations / étudiant / semaine</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">S'applique à l'ensemble des lave-linge et sèche-linge.</p>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Jour de réinitialisation automatique du quota</label>
                    <select class="w-48 px-3 py-2 border border-slate-300 rounded focus:border-[#00897b] focus:outline-none">
                        <option selected>Lundi à 00h00</option>
                        <option>Dimanche à 23h59</option>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Remise à zéro des compteurs pour tous les étudiants.</p>
                </div>
            </div>
        </div>

        <!-- Horaires & Délais -->
        <div>
            <h2 class="font-bold text-sm text-slate-800 border-b border-slate-200 pb-2 mb-4">
                Horaires d'Ouverture & Règles de Délai
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Heure d'ouverture buanderie</label>
                    <input type="time" value="06:00" class="w-32 px-3 py-2 border border-slate-300 rounded focus:border-[#00897b] focus:outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Heure de fermeture buanderie</label>
                    <input type="time" value="23:30" class="w-32 px-3 py-2 border border-slate-300 rounded focus:border-[#00897b] focus:outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Délai de grâce avant libération du créneau</label>
                    <div class="flex items-center space-x-2">
                        <input type="number" value="15" class="w-24 px-3 py-2 border border-slate-300 rounded focus:border-[#00897b] focus:outline-none">
                        <span class="text-slate-500">minutes de tolérance</span>
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Anticipation maximale de réservation</label>
                    <div class="flex items-center space-x-2">
                        <input type="number" value="7" class="w-24 px-3 py-2 border border-slate-300 rounded focus:border-[#00897b] focus:outline-none">
                        <span class="text-slate-500">jours à l'avance</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div>
            <h2 class="font-bold text-sm text-slate-800 border-b border-slate-200 pb-2 mb-4">
                Rappels Automatiques
            </h2>
            <div class="space-y-2">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" checked class="rounded text-[#00897b]">
                    <span class="text-slate-700">Notifier l'étudiant 5 minutes avant la fin de son cycle</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" checked class="rounded text-[#00897b]">
                    <span class="text-slate-700">Restituer le quota hebdomadaire si la réservation est annulée plus de 2 heures à l'avance</span>
                </label>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end">
            <button class="px-5 py-2 rounded bg-[#00897b] hover:bg-[#00796b] text-white font-bold transition-all shadow-xs">
                Enregistrer les paramètres
            </button>
        </div>

    </div>
</div>
@endsection
