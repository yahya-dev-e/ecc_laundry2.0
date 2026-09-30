import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Countdown timer Alpine component
Alpine.data('countdown', (endsAtTimestamp) => ({
    endsAt: endsAtTimestamp ? new Date(endsAtTimestamp).getTime() : null,
    remainingSeconds: 0,
    formattedTime: '--:--',
    timer: null,
    isCompleted: false,

    init() {
        if (!this.endsAt) return;
        this.updateRemaining();
        this.timer = setInterval(() => {
            this.updateRemaining();
        }, 1000);
    },

    updateRemaining() {
        const now = new Date().getTime();
        const diff = Math.floor((this.endsAt - now) / 1000);

        if (diff <= 0) {
            this.remainingSeconds = 0;
            this.formattedTime = 'Finished';
            this.isCompleted = true;
            if (this.timer) clearInterval(this.timer);
            return;
        }

        this.remainingSeconds = diff;
        const minutes = Math.floor(diff / 60);
        const seconds = diff % 60;
        this.formattedTime = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    },

    destroy() {
        if (this.timer) clearInterval(this.timer);
    }
}));

// Quick slot picker Alpine component
Alpine.data('slotPicker', (initialMachineId = null) => ({
    selectedMachine: initialMachineId,
    selectedDate: new Date().toISOString().split('T')[0],
    selectedSlot: null,
    slots: [],
    loading: false,

    async fetchSlots() {
        if (!this.selectedMachine) return;
        this.loading = true;
        try {
            const res = await fetch(`/machines/${this.selectedMachine}/slots?date=${this.selectedDate}`);
            const data = await res.json();
            this.slots = data.slots || [];
        } catch (e) {
            console.error('Error fetching slots:', e);
            this.slots = [];
        } finally {
            this.loading = false;
        }
    }
}));

Alpine.start();
