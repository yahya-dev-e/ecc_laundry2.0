@extends('layouts.app')
@section('content')
<div class="max-w-3xl mx-auto space-y-5" x-data="bookingForm({{ $selectedMachine?->id ?? $machines->first()?->id ?? 'null' }}, @js($initialDate))">
    <h1 class="text-xl font-bold">Réserver une machine</h1>
    <p class="text-sm text-slate-600">Horaires du campus : {{ config('app.timezone') }}. Réservation jusqu’au {{ \Carbon\Carbon::parse($maxDate)->format('d/m/Y') }} inclus.</p>
    @if($machines->isEmpty())
        <p role="status" class="p-4 bg-white rounded-lg">Aucune machine n’est disponible dans le catalogue.</p>
    @else
    <form method="POST" action="{{ route('bookings.store') }}" class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 space-y-5 pb-28 sm:pb-6" @submit="if (!selectedTimes.length) { $event.preventDefault() } else { submitting = true }">
        @csrf
        <div><label for="machineSelect" class="block font-semibold text-sm mb-1">Machine</label>
            <select id="machineSelect" name="machine_id" x-model="machineId" @change="selectedTimes = []; loadSlots()" class="w-full border border-slate-300 rounded-lg px-3 py-2.5">
                @foreach($machines as $machine)
                <option value="{{ $machine->id }}" @selected($machine->id === ($selectedMachine?->id ?? $machines->first()?->id))>{{ $machine->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="bookingDate" class="block font-semibold text-sm mb-1">Date</label>
            <input id="bookingDate" type="date" name="date" x-model="selectedDate" @change="selectedTimes = []; loadSlots()" value="{{ $initialDate }}" min="{{ $minDate }}" max="{{ $maxDate }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5" required>
        </div>
        <noscript>
            <p>Choisissez l’heure de début. Chaque réservation dure une heure.</p>
            <label for="manual-time">Date et heure de début</label>
            <input id="manual-time" type="datetime-local" name="start_time" min="{{ now()->format('Y-m-d\TH:i') }}" max="{{ $maxDate }}T23:00" required>
            <button class="btn-primary" type="submit">Réserver ce créneau</button>
        </noscript>
        <div x-cloak>
            <h2 class="font-semibold mb-2">Créneaux d’une heure disponibles</h2>
            <p role="status" aria-live="polite" class="text-sm text-slate-600 mb-3" x-text="message"></p>
            <p x-show="loading" role="status">Chargement des créneaux…</p>
            <div x-show="error" role="alert" class="p-3 bg-rose-50 text-rose-800 rounded-lg">
                <span x-text="error"></span><button type="button" @click="loadSlots()" class="ml-2 underline font-semibold">Réessayer</button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-80 overflow-y-auto p-1">
                <template x-for="slot in slots" :key="slot.datetime">
                    <button type="button" @click="toggle(slot.datetime)" :aria-pressed="selectedTimes.includes(slot.datetime)"
                        :class="selectedTimes.includes(slot.datetime) ? 'bg-emerald-100 border-emerald-700 text-emerald-900' : 'bg-white border-slate-300 text-slate-800'"
                        class="min-h-[48px] p-3 rounded-lg border font-mono text-sm" x-text="slot.start_time + ' – ' + slot.end_time"></button>
                </template>
            </div>
            <p x-show="!loading && !error && !slots.length" class="p-4 rounded-lg bg-slate-50">Aucun créneau disponible pour cette machine et cette date.</p>
            <template x-for="time in selectedTimes" :key="time"><input type="hidden" name="start_times[]" :value="time"></template>
            <p class="mt-4 text-sm" aria-live="polite"><span x-text="selectedTimes.length"></span>h choisies · <span x-text="remainingMinutes / 60"></span>h disponibles · <span x-text="maxNewBookings"></span> réservation(s) active(s) encore autorisée(s).</p>
            <p x-show="overQuota" role="alert" class="text-rose-700 text-sm mt-2">La sélection dépasse votre quota ou la limite de réservations actives.</p>
            <div class="flex flex-wrap gap-3 pt-5 mt-5 border-t">
                <button type="submit" :disabled="!selectedTimes.length || overQuota || submitting || loading || !!error" class="btn-primary">Confirmer la réservation</button>
                <a href="{{ route('calendrier') }}" class="px-4 py-2 underline">Annuler</a>
            </div>
            <div x-show="selectedTimes.length" class="sm:hidden fixed bottom-0 inset-x-0 p-3 bg-white border-t shadow-lg z-40 safe-pb flex items-center justify-between gap-3">
                <span class="text-sm" x-text="selectedTimes.length + 'h choisies'"></span>
                <button type="submit" :disabled="overQuota || submitting || loading || !!error" class="btn-primary">Confirmer</button>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection
