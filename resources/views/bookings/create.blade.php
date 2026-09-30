@extends('layouts.app')

@section('title', 'Reserve a Laundry Machine')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-white tracking-tight">Reserve a Laundry Slot</h1>
            <p class="text-xs text-slate-400">Select a machine, date, and preferred operating window</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn-secondary text-xs">
            &larr; Back to Dashboard
        </a>
    </div>

    <!-- Main Booking Form Card -->
    <div class="glass-card p-6 md:p-8" x-data="{
        machineId: '{{ $selectedMachine->id ?? ($machines->first()->id ?? '') }}',
        selectedDate: '{{ now()->toDateString() }}',
        selectedSlot: '',
        slots: {{ json_encode($availableSlots ?? []) }},
        loadingSlots: false,

        async updateSlots() {
            if (!this.machineId) return;
            this.loadingSlots = true;
            try {
                const res = await fetch(`/machines/${this.machineId}/slots?date=${this.selectedDate}`);
                const data = await res.json();
                this.slots = data.slots || [];
                this.selectedSlot = '';
            } catch (err) {
                console.error('Could not load slots', err);
            } finally {
                this.loadingSlots = false;
            }
        }
    }">
        <form method="POST" action="{{ route('bookings.store') }}" class="space-y-6">
            @csrf

            <!-- Step 1: Select Machine -->
            <div>
                <label for="machine_id" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">
                    Step 1: Choose Machine
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach ($machines as $machine)
                        <label class="cursor-pointer">
                            <input type="radio" name="machine_id" value="{{ $machine->id }}" 
                                   x-model="machineId" @change="updateSlots()"
                                   class="peer sr-only">
                            <div class="p-4 rounded-xl border border-slate-800 bg-slate-950/60 peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 peer-checked:ring-1 peer-checked:ring-cyan-500 transition-all">
                                <div class="flex items-center justify-between">
                                    <span class="font-extrabold text-sm text-white">{{ $machine->code }}</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-300 uppercase font-mono">{{ $machine->type->value }}</span>
                                </div>
                                <p class="text-xs text-slate-400 mt-1 truncate">{{ $machine->name }}</p>
                                <div class="mt-2 text-[11px] text-slate-500 flex justify-between">
                                    <span>{{ $machine->capacity_kg }}kg • {{ $machine->default_duration_minutes }}m</span>
                                    <span class="text-amber-400 font-bold">{{ $machine->cost_per_cycle }} cr</span>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Step 2: Select Date -->
            <div>
                <label for="reservation_date" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">
                    Step 2: Select Date
                </label>
                <div class="flex items-center gap-3">
                    <input type="date" id="reservation_date" 
                           x-model="selectedDate" 
                           @change="updateSlots()"
                           min="{{ now()->toDateString() }}" 
                           max="{{ now()->addDays(config('laundry.max_advance_booking_days', 7))->toDateString() }}"
                           class="glass-input text-sm">
                    <span class="text-xs text-slate-500">Book up to 7 days ahead</span>
                </div>
            </div>

            <!-- Step 3: Choose Available Time Slot -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                        Step 3: Select Start Time Slot
                    </label>
                    <span x-show="loadingSlots" class="text-xs text-cyan-400 animate-pulse">Loading availability...</span>
                </div>

                <input type="hidden" name="start_time" :value="selectedSlot" required>

                <!-- Slots Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5">
                    <template x-for="slot in slots" :key="slot.datetime">
                        <button type="button" 
                                @click="if(slot.is_available) selectedSlot = slot.datetime"
                                :disabled="!slot.is_available"
                                :class="{
                                    'border-cyan-500 bg-cyan-500/20 text-cyan-300 ring-2 ring-cyan-500/50': selectedSlot === slot.datetime,
                                    'border-slate-800 bg-slate-950/40 text-slate-300 hover:border-slate-700': slot.is_available && selectedSlot !== slot.datetime,
                                    'opacity-40 border-slate-900 bg-slate-950/20 text-slate-600 cursor-not-allowed': !slot.is_available
                                }"
                                class="p-3 rounded-xl border text-center transition-all">
                            <div class="font-mono font-bold text-sm" x-text="slot.start_time"></div>
                            <div class="text-[10px] text-slate-500 mt-0.5" x-text="slot.is_available ? 'Available' : (slot.is_past ? 'Past' : 'Booked')"></div>
                        </button>
                    </template>
                </div>

                <div x-show="slots.length === 0 && !loadingSlots" class="p-4 rounded-xl bg-slate-900/60 text-center text-xs text-slate-500">
                    No operating slots available for the selected machine on this date.
                </div>
            </div>

            <!-- Order Summary & Confirmation -->
            <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="text-xs space-y-1">
                    <div class="flex items-center space-x-2">
                        <span class="text-slate-400">Your Current Balance:</span>
                        <span class="font-bold text-white">{{ auth()->user()->credits }} Credits</span>
                    </div>
                    <div class="text-slate-500">
                        * A 15-minute grace period applies once your scheduled start time arrives.
                    </div>
                </div>

                <button type="submit" 
                        :disabled="!selectedSlot || !machineId"
                        class="btn-primary w-full sm:w-auto text-sm">
                    Confirm & Reserve Slot
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
