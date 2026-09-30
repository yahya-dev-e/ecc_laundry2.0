@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="bookingApp()">

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
            <span class="text-xs bg-white/20 px-2.5 py-1 rounded font-mono font-bold" x-text="selectedMachine">ML1-OM</span>
        </div>

        <form method="POST" action="/reserver" class="p-6 space-y-6 text-xs">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Machine Selector (CLEAN CODES - NO PARENTHESES!) -->
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Machine
                    </label>
                    <select name="machine" x-model="selectedMachine" @change="onMachineChange()"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded text-xs focus:border-[#00897b] focus:outline-none bg-slate-50 font-bold text-slate-800">
                        <optgroup label="Machines à laver">
                            <option value="ML1-OM">ML1-OM</option>
                            <option value="ML2-OM">ML2-OM</option>
                            <option value="ML1-PE">ML1-PE</option>
                            <option value="ML2-PE">ML2-PE</option>
                            <option value="ML3-PE">ML3-PE</option>
                            <option value="ML4-PE">ML4-PE</option>
                            <option value="ML3-OM">ML3-OM</option>
                        </optgroup>
                        <optgroup label="Sèche-linge">
                            <option value="SL1-OM">SL1-OM</option>
                            <option value="SL2-OM">SL2-OM</option>
                            <option value="SL1-PE">SL1-PE</option>
                            <option value="SL2-PE">SL2-PE</option>
                            <option value="SL3-PE">SL3-PE</option>
                            <option value="SL3-OM">SL3-OM</option>
                        </optgroup>
                    </select>
                </div>

                <!-- Date -->
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Date de réservation
                    </label>
                    <input type="date" name="date" x-model="selectedDate" @change="onDateChange()"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded text-xs focus:border-[#00897b] focus:outline-none bg-white">
                </div>
            </div>

            <!-- Multi-Slot Interactive Selection Grid -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block font-bold text-slate-700 uppercase tracking-wider">
                        Créneaux horaires disponibles (Sélection multiple possible)
                    </label>
                    <span class="text-[11px] text-slate-500 font-semibold" x-text="availableSlots.length + ' créneaux disponibles'"></span>
                </div>
                <p class="text-[11px] text-slate-500 mb-3">
                    Sélectionnez un ou plusieurs créneaux d'1 heure consécutifs ou distincts sur la même machine. Les heures déjà réservées sont automatiquement masquées pour éviter tout conflit.
                </p>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 max-h-72 overflow-y-auto p-1 border border-slate-200 rounded-lg bg-slate-50/50">
                    <template x-for="slot in availableSlots" :key="slot">
                        <label :class="{'border-[#00897b] bg-[#e0f2f1] text-[#00695c] font-bold shadow-xs': isSelected(slot), 'border-slate-200 bg-white text-slate-700 hover:border-[#00897b]': !isSelected(slot)}"
                               class="flex items-center justify-between p-2.5 rounded border transition-all cursor-pointer select-none text-xs">
                            <input type="checkbox" name="hours[]" :value="slot" @change="toggleSlot(slot)" :checked="isSelected(slot)" class="hidden">
                            <span class="font-mono" x-text="slot"></span>
                            <span class="w-4 h-4 rounded-full border flex items-center justify-center text-[10px]"
                                  :class="{'bg-[#00897b] border-[#00897b] text-white': isSelected(slot), 'border-slate-300': !isSelected(slot)}">
                                <span x-show="isSelected(slot)">✓</span>
                            </span>
                        </label>
                    </template>
                </div>
            </div>

            <!-- Quota & Credit Surveillance Summary Box -->
            <div class="p-4 rounded-lg bg-emerald-50/80 border border-emerald-200 text-xs space-y-2.5">
                <div class="flex items-center justify-between font-bold text-emerald-900 border-b border-emerald-200/60 pb-2">
                    <span class="flex items-center space-x-1.5">
                        <span>⚡</span>
                        <span>Surveillance du Quota Hebdomadaire (8h / semaine)</span>
                    </span>
                    <span class="text-xs font-mono bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded">
                        1h = 1 crédit
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-slate-700 pt-1">
                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Créneaux choisis</span>
                        <span class="font-bold text-slate-800 text-sm font-mono" x-text="selectedSlots.length + ' heure(s)'"></span>
                    </div>

                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Coût total</span>
                        <span class="font-bold text-[#00897b] text-sm font-mono" x-text="selectedSlots.length + ' crédit(s)'"></span>
                    </div>

                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Solde actuel</span>
                        <span class="font-bold text-slate-800 text-sm font-mono" x-text="remainingHours + 'h / 8h'"></span>
                    </div>

                    <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                        <span class="text-[10px] uppercase text-slate-400 font-bold block">Solde après</span>
                        <span class="font-bold text-sm font-mono" 
                              :class="(remainingHours - selectedSlots.length) < 0 ? 'text-rose-600' : 'text-emerald-700'"
                              x-text="(remainingHours - selectedSlots.length) + 'h / 8h'"></span>
                    </div>
                </div>

                <!-- Insufficient Quota Alert -->
                <div x-show="selectedSlots.length > remainingHours" 
                     class="p-2.5 bg-rose-50 border border-rose-200 text-rose-800 rounded font-semibold text-[11px] flex items-center space-x-2">
                    <span>⚠️</span>
                    <span>Dépassement de quota : vous avez sélectionné plus d'heures que votre solde hebdomadaire restant (<span x-text="remainingHours"></span>h disponibles).</span>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="pt-3 flex items-center justify-end space-x-3 border-t border-slate-200">
                <a href="{{ route('dashboard') }}" 
                   class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded">
                    Annuler
                </a>
                <button type="submit" 
                        :disabled="selectedSlots.length === 0 || selectedSlots.length > remainingHours"
                        :class="(selectedSlots.length === 0 || selectedSlots.length > remainingHours) ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-[#00897b] hover:bg-[#00796b] cursor-pointer shadow-xs'"
                        class="px-6 py-2.5 text-white text-xs font-bold rounded transition-all">
                    <span x-show="selectedSlots.length === 0">Sélectionnez au moins 1 créneau</span>
                    <span x-show="selectedSlots.length > 0" x-text="'Confirmer la réservation (' + selectedSlots.length + 'h • ' + selectedSlots.length + ' crédit' + (selectedSlots.length > 1 ? 's' : '') + ')'"></span>
                </button>
            </div>
        </form>

    </div>

</div>

<script>
function bookingApp() {
    return {
        selectedMachine: 'ML1-OM',
        selectedDate: '2026-09-30',
        remainingHours: 6, // 8h total - 2h used
        selectedSlots: [],
        allDaySlots: [
            '00:00 - 01:00', '01:00 - 02:00', '02:00 - 03:00', '03:00 - 04:00', '04:00 - 05:00', '05:00 - 06:00',
            '06:00 - 07:00', '07:00 - 08:00', '08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '11:00 - 12:00',
            '12:00 - 13:00', '13:00 - 14:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00',
            '18:00 - 19:00', '19:00 - 20:00', '20:00 - 21:00', '21:00 - 22:00', '22:00 - 23:00', '23:00 - 00:00'
        ],
        // Known reservations to filter out
        bookedMap: {
            'ML1-PE': ['14:00 - 15:00'],
            'ML2-OM': ['00:00 - 01:00', '07:00 - 08:00', '08:00 - 09:00'],
            'SL1-PE': ['06:00 - 07:00'],
            'ML2-PE': ['07:00 - 08:00', '08:00 - 09:00'],
            'ML3-PE': ['07:00 - 08:00', '08:00 - 09:00']
        },
        get availableSlots() {
            const booked = this.bookedMap[this.selectedMachine] || [];
            return this.allDaySlots.filter(s => !booked.includes(s));
        },
        isSelected(slot) {
            return this.selectedSlots.includes(slot);
        },
        toggleSlot(slot) {
            const index = this.selectedSlots.indexOf(slot);
            if (index > -1) {
                this.selectedSlots.splice(index, 1);
            } else {
                this.selectedSlots.push(slot);
            }
        },
        onMachineChange() {
            // Filter out any selected slot that isn't available on new machine
            const booked = this.bookedMap[this.selectedMachine] || [];
            this.selectedSlots = this.selectedSlots.filter(s => !booked.includes(s));
        },
        onDateChange() {
            this.selectedSlots = [];
        }
    };
}
</script>
@endsection
