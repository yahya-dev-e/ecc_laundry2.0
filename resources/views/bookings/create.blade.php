@extends('layouts.app')

@section('content')
@php
    $washers = $machines->filter(fn($m) => in_array($m->type instanceof \App\Enums\MachineType ? $m->type->value : (string)$m->type, ['washing-machine', 'washer']));
    $dryers = $machines->filter(fn($m) => in_array($m->type instanceof \App\Enums\MachineType ? $m->type->value : (string)$m->type, ['dryer']));
    $defaultMachineId = $selectedMachine?->id ?? ($machines->first()?->id ?? 1);
@endphp

<div class="max-w-3xl mx-auto space-y-6" x-data="bookingApp({{ $defaultMachineId }})">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Réserver une machine</h1>
            <p class="text-xs text-slate-500">Planification des créneaux horaires disponibles par machine</p>
        </div>
        <a href="{{ route('dashboard') }}" class="text-xs text-[#00897b] hover:underline font-semibold flex items-center space-x-1">
            <span>&larr;</span>
            <span>Retour au calendrier</span>
        </a>
    </div>

    <!-- Reservation Form Card -->
    <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden">
        
        <div class="bg-[#00897b] px-6 py-4 text-white flex items-center justify-between">
            <span class="text-sm font-bold">Sélection des créneaux de réservation</span>
            <span class="text-xs bg-white/20 px-2.5 py-1 rounded font-mono font-bold" x-text="selectedMachineName">Machine</span>
        </div>

        <form method="POST" action="{{ route('bookings.store') }}" class="p-6 space-y-6 text-xs">
            @csrf

            @if(session('error'))
                <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded text-xs font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Machine Selector -->
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Machine
                    </label>
                    <select name="machine_id" x-model.number="selectedMachineId" @change="onMachineChange()"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded text-xs focus:border-[#00897b] focus:outline-none bg-slate-50 font-bold text-slate-800">
                        @if($washers->isNotEmpty())
                            <optgroup label="Machines à laver">
                                @foreach($washers as $machine)
                                    <option value="{{ $machine->id }}">{{ $machine->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($dryers->isNotEmpty())
                            <optgroup label="Sèche-linge">
                                @foreach($dryers as $machine)
                                    <option value="{{ $machine->id }}">{{ $machine->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>

                <!-- Date -->
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Date de réservation
                    </label>
                    <input type="date" name="date" x-model="selectedDate" @change="onDateChange()" min="{{ now()->toDateString() }}" max="{{ now()->addDays(7)->toDateString() }}"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded text-xs focus:border-[#00897b] focus:outline-none bg-white">
                </div>
            </div>

            <!-- Multi-Slot Interactive Selection Grid -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block font-bold text-slate-700 uppercase tracking-wider">
                        Créneaux horaires disponibles
                    </label>
                    <span class="text-[11px] text-slate-500 font-semibold" x-text="availableSlots.length + ' créneaux disponibles'"></span>
                </div>
                <p class="text-[11px] text-slate-500 mb-3">
                    Sélectionnez un créneau horaire d'1 heure. Les heures déjà réservées sont automatiquement filtrées pour éviter tout conflit.
                </p>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 max-h-72 overflow-y-auto p-1 border border-slate-200 rounded-lg bg-slate-50/50">
                    <template x-for="slot in availableSlots" :key="slot">
                        <label :class="{'border-[#00897b] bg-[#e0f2f1] text-[#00695c] font-bold shadow-xs': isSelected(slot), 'border-slate-200 bg-white text-slate-700 hover:border-[#00897b]': !isSelected(slot)}"
                               class="flex items-center justify-between p-2.5 rounded border transition-all cursor-pointer select-none text-xs">
                            <input type="radio" name="start_time" :value="slotToDateTime(slot)" @change="selectSlot(slot)" :checked="isSelected(slot)" class="hidden">
                            <span class="font-mono" x-text="slot"></span>
                            <span class="w-4 h-4 rounded-full border flex items-center justify-center text-[10px]"
                                  :class="{'bg-[#00897b] border-[#00897b] text-white': isSelected(slot), 'border-slate-300': !isSelected(slot)}">
                                <svg x-show="isSelected(slot)" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </span>
                        </label>
                    </template>
                </div>
            </div>

            <!-- Quota & Credit Surveillance Summary Box -->
            <div class="p-4 rounded-lg bg-emerald-50/80 border border-emerald-200 text-xs space-y-2.5">
                <div class="flex items-center justify-between font-bold text-emerald-900 border-b border-emerald-200/60 pb-2">
                    <span class="flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span>Surveillance du Quota Hebdomadaire (8h / semaine)</span>
                    </span>
                    <span class="text-xs font-mono bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded">
                        1h = 1 crédit
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-slate-700 pt-1">
                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Créneau choisi</span>
                        <span class="font-bold text-slate-800 text-sm font-mono" x-text="selectedSlot ? '1 heure' : '0 heure'"></span>
                    </div>

                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Coût</span>
                        <span class="font-bold text-[#00897b] text-sm font-mono" x-text="selectedSlot ? '1 crédit' : '0 crédit'"></span>
                    </div>

                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Solde actuel</span>
                        <span class="font-bold text-slate-800 text-sm font-mono" x-text="remainingHours + 'h / 8h'"></span>
                    </div>

                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Solde après</span>
                        <span class="font-bold text-sm font-mono text-emerald-700"
                              x-text="(remainingHours - (selectedSlot ? 1 : 0)) + 'h / 8h'"></span>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="pt-3 flex items-center justify-end space-x-3 border-t border-slate-200">
                <a href="{{ route('dashboard') }}" 
                   class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded">
                    Annuler
                </a>
                <button type="submit" 
                        :disabled="!selectedSlot"
                        :class="!selectedSlot ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-[#00897b] hover:bg-[#00796b] cursor-pointer shadow-xs'"
                        class="px-6 py-2.5 text-white text-xs font-bold rounded transition-all">
                    <span x-show="!selectedSlot">Sélectionnez un créneau</span>
                    <span x-show="selectedSlot">Confirmer la réservation (1h • 1 crédit)</span>
                </button>
            </div>
        </form>

    </div>

</div>

<script>
function bookingApp(initialMachineId) {
    const machinesMap = {
        @foreach($machines as $m)
            {{ $m->id }}: @json($m->name),
        @endforeach
    };

    return {
        selectedMachineId: initialMachineId,
        selectedDate: '{{ now()->toDateString() }}',
        remainingHours: {{ auth()->check() ? auth()->user()->weeklyRemainingLimit() : 8 }},
        selectedSlot: null,
        allDaySlots: [
            '06:00 - 07:00', '07:00 - 08:00', '08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '11:00 - 12:00',
            '12:00 - 13:00', '13:00 - 14:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00',
            '18:00 - 19:00', '19:00 - 20:00', '20:00 - 21:00', '21:00 - 22:00', '22:00 - 23:00'
        ],
        get selectedMachineName() {
            return machinesMap[this.selectedMachineId] || 'Machine';
        },
        get availableSlots() {
            return this.allDaySlots;
        },
        slotToDateTime(slot) {
            const startHour = slot.split(' - ')[0];
            return this.selectedDate + ' ' + startHour + ':00';
        },
        isSelected(slot) {
            return this.selectedSlot === slot;
        },
        selectSlot(slot) {
            this.selectedSlot = slot;
        },
        onMachineChange() {
            this.selectedSlot = null;
        },
        onDateChange() {
            this.selectedSlot = null;
        }
    };
}
</script>
@endsection
