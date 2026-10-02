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
            <p class="text-xs text-slate-500">Planification des créneaux horaires disponibles par machine (24h/24)</p>
        </div>
        <a href="{{ route('calendrier') }}" class="text-xs text-[#00897b] hover:underline font-semibold flex items-center space-x-1">
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

            <!-- Multi-Slot Interactive Selection Grid (Only Open Slots Shown) -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block font-bold text-slate-700 uppercase tracking-wider">
                        Créneaux horaires disponibles (Sélection multiple possible)
                    </label>
                    <span class="text-[11px] text-slate-500 font-semibold font-mono" x-text="availableSlots.length + ' créneaux disponibles'"></span>
                </div>
                <p class="text-[11px] text-slate-500 mb-3">
                    Sélectionnez un ou plusieurs créneaux d'1 heure. Les heures déjà réservées sont automatiquement masquées pour n'afficher que les créneaux ouverts en ce moment.
                </p>

                <!-- Hidden inputs for form submission -->
                <template x-for="slot in selectedSlots" :key="slot">
                    <input type="hidden" name="start_times[]" :value="slotToDateTime(slot)">
                </template>

                <div x-show="availableSlots.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 max-h-72 overflow-y-auto p-2 border border-slate-200 rounded-lg bg-slate-50/50">
                    <template x-for="slot in availableSlots" :key="slot">
                        <label :class="{'border-[#00897b] bg-[#e0f2f1] text-[#00695c] font-bold shadow-xs': isSelected(slot), 'border-slate-200 bg-white text-slate-700 hover:border-[#00897b]': !isSelected(slot)}"
                               class="flex items-center justify-between p-2.5 rounded border transition-all cursor-pointer select-none text-xs"
                               @click.prevent="toggleSlot(slot)">
                            <span class="font-mono" x-text="slot"></span>
                            <span class="w-4 h-4 rounded-full border flex items-center justify-center text-[10px]"
                                  :class="{'bg-[#00897b] border-[#00897b] text-white': isSelected(slot), 'border-slate-300': !isSelected(slot)}">
                                <svg x-show="isSelected(slot)" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </span>
                        </label>
                    </template>
                </div>

                <div x-show="availableSlots.length === 0" class="p-8 text-center bg-slate-50 border border-slate-200 rounded-lg">
                    <p class="text-xs font-bold text-slate-600">Aucun créneau disponible</p>
                    <p class="text-[11px] text-slate-400 mt-1">Tous les créneaux de cette machine sont déjà réservés pour la date sélectionnée.</p>
                </div>
            </div>

            <!-- Quota & Credit Surveillance Summary Box -->
            <div class="p-4 rounded-lg bg-emerald-50/80 border border-emerald-200 text-xs space-y-2.5">
                <div class="flex items-center justify-between font-bold text-emerald-900 border-b border-emerald-200/60 pb-2">
                    <span class="flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span>Surveillance du Quota Hebdomadaire (<span x-text="weeklyLimit"></span> crédits / semaine)</span>
                    </span>
                    <span class="text-xs font-mono bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded">
                        1h = 1 crédit
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-slate-700 pt-1">
                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Créneaux choisis</span>
                        <span class="font-bold text-slate-800 text-sm font-mono" x-text="selectedSlots.length + ' heure' + (selectedSlots.length > 1 ? 's' : '')"></span>
                    </div>

                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Coût total</span>
                        <span class="font-bold text-[#00897b] text-sm font-mono" x-text="selectedSlots.length + ' crédit' + (selectedSlots.length > 1 ? 's' : '')"></span>
                    </div>

                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Solde actuel</span>
                        <span class="font-bold text-slate-800 text-sm font-mono" x-text="remainingHours + 'h / ' + weeklyLimit + 'h'"></span>
                    </div>

                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Solde après</span>
                        <span class="font-bold text-sm font-mono"
                              :class="(remainingHours - selectedSlots.length < 0) ? 'text-rose-600' : 'text-emerald-700'"
                              x-text="((remainingHours - selectedSlots.length) + 'h / ' + weeklyLimit + 'h')"></span>
                    </div>
                </div>

                <!-- Over quota alert -->
                <div x-show="selectedSlots.length > remainingHours" class="p-2.5 bg-rose-50 border border-rose-200 text-rose-800 rounded font-semibold text-[11px] flex items-center space-x-2">
                    <svg class="w-4 h-4 text-rose-600 shrink-0 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>Dépassement de quota : vous avez sélectionné <span x-text="selectedSlots.length"></span>h alors qu'il ne vous reste que <span x-text="remainingHours"></span>h sur vos <span x-text="weeklyLimit"></span>h cette semaine.</span>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="pt-3 flex items-center justify-end space-x-3 border-t border-slate-200">
                <a href="{{ route('calendrier') }}" 
                   class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded">
                    Annuler
                </a>
                <button type="submit" 
                        :disabled="selectedSlots.length === 0 || (selectedSlots.length > remainingHours)"
                        :class="(selectedSlots.length === 0 || (selectedSlots.length > remainingHours)) ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-[#00897b] hover:bg-[#00796b] cursor-pointer shadow-xs'"
                        class="px-6 py-2.5 text-white text-xs font-bold rounded transition-all">
                    <span x-show="selectedSlots.length === 0">Sélectionnez au moins 1 créneau</span>
                    <span x-show="selectedSlots.length > 0 && (selectedSlots.length > remainingHours)">Quota insuffisant</span>
                    <span x-show="selectedSlots.length > 0 && (selectedSlots.length <= remainingHours)"
                          x-text="'Confirmer la réservation (' + selectedSlots.length + 'h • ' + selectedSlots.length + ' crédit' + (selectedSlots.length > 1 ? 's' : '') + ')'"></span>
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

    const reservationsList = @json($reservations ?? []);
    const isAdminUser = {{ (auth()->check() && auth()->user()->isAdmin()) ? 'true' : 'false' }};

    // Full 24-hour slots without limiters
    const all24Slots = [];
    for (let h = 0; h < 24; h++) {
        const sH = String(h).padStart(2, '0') + ':00';
        const eH = String(h + 1 === 24 ? 24 : h + 1).padStart(2, '0') + ':00';
        all24Slots.push(sH + ' - ' + eH);
    }

    return {
        selectedMachineId: initialMachineId,
        selectedDate: '{{ now()->toDateString() }}',
        weeklyLimit: {{ auth()->check() ? auth()->user()->weeklyLimit() : (auth()->check() && auth()->user()->isAdmin() ? 100 : 8) }},
        remainingHours: {{ auth()->check() ? auth()->user()->weeklyRemainingLimit() : 8 }},
        isAdmin: isAdminUser,
        selectedSlots: [],
        allDaySlots: all24Slots,
        get selectedMachineName() {
            return machinesMap[this.selectedMachineId] || 'Machine';
        },
        get availableSlots() {
            // Filter to show ONLY open slots
            return this.allDaySlots.filter(slot => {
                const [startStr, endStr] = slot.split(' - ');
                const slotStartMinutes = parseInt(startStr.split(':')[0], 10) * 60;
                let slotEndMinutes = parseInt(endStr.split(':')[0], 10) * 60;
                if (slotEndMinutes === 0) slotEndMinutes = 1440;

                // Check against reservations for this machine and date
                for (const r of reservationsList) {
                    if (r.machine_id === this.selectedMachineId && r.date === this.selectedDate) {
                        const rStartTime = r.start.split(' ')[1] || '00:00';
                        const rEndTime = r.end.split(' ')[1] || '00:00';
                        const rStartMin = parseInt(rStartTime.split(':')[0], 10) * 60 + parseInt(rStartTime.split(':')[1] || '0', 10);
                        let rEndMin = parseInt(rEndTime.split(':')[0], 10) * 60 + parseInt(rEndTime.split(':')[1] || '0', 10);
                        if (rEndMin === 0) rEndMin = 1440;

                        if (slotStartMinutes < rEndMin && slotEndMinutes > rStartMin) {
                            return false; // Slot is booked, filter it out!
                        }
                    }
                }
                return true; // Slot is open!
            });
        },
        slotToDateTime(slot) {
            const startHour = slot.split(' - ')[0];
            return this.selectedDate + ' ' + startHour + ':00';
        },
        isSelected(slot) {
            return this.selectedSlots.includes(slot);
        },
        toggleSlot(slot) {
            const idx = this.selectedSlots.indexOf(slot);
            if (idx > -1) {
                this.selectedSlots.splice(idx, 1);
            } else {
                this.selectedSlots.push(slot);
            }
        },
        onMachineChange() {
            this.selectedSlots = [];
        },
        onDateChange() {
            this.selectedSlots = [];
        }
    };
}
</script>
@endsection
