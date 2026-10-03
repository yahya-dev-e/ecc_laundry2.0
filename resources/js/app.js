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

// Live Server Time Clock Alpine component
export function serverClock(serverIsoString, timeZone = 'UTC') {
    return {
        serverBaseTime: serverIsoString ? new Date(serverIsoString).getTime() : Date.now(),
        clientBaseTime: Date.now(),
        timeFormatted: '',
        timer: null,
        formatter: null,

        init() {
            try {
                this.formatter = new Intl.DateTimeFormat('fr-FR', {
                    timeZone: timeZone,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });
            } catch (e) {
                this.formatter = null;
            }
            this.update();
            this.timer = setInterval(() => this.update(), 1000);
        },

        update() {
            const elapsed = Date.now() - this.clientBaseTime;
            const currentServerDate = new Date(this.serverBaseTime + elapsed);
            if (this.formatter) {
                this.timeFormatted = this.formatter.format(currentServerDate);
            } else {
                const h = String(currentServerDate.getUTCHours()).padStart(2, '0');
                const m = String(currentServerDate.getUTCMinutes()).padStart(2, '0');
                const s = String(currentServerDate.getUTCSeconds()).padStart(2, '0');
                this.timeFormatted = `${h}:${m}:${s}`;
            }
        },

        destroy() {
            if (this.timer) clearInterval(this.timer);
        }
    };
}

// Local System Hour Pointer component for Calendar timeline
export function systemTimePointer(selectedDateStr, serverTodayStr = '') {
    return {
        isVisible: false,
        topPx: 0,
        timeFormatted: '',
        timer: null,

        init() {
            this.update();
            this.timer = setInterval(() => this.update(), 1000);
        },

        update() {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            const localDateStr = `${y}-${m}-${d}`;

            // Show pointer when viewing today's schedule (either matching local system date or server today)
            this.isVisible = (selectedDateStr === localDateStr || (serverTodayStr && selectedDateStr === serverTodayStr));
            if (!this.isVisible) return;

            const hours = now.getHours();
            const minutes = now.getMinutes();
            const seconds = now.getSeconds();
            const totalMinutes = (hours * 60) + minutes + (seconds / 60);

            // 52px height per hour in calendar timeline
            this.topPx = Math.min(24 * 52, Math.max(0, (totalMinutes / 60) * 52));
            this.timeFormatted = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        },

        destroy() {
            if (this.timer) clearInterval(this.timer);
        }
    };
}

window.serverClock = serverClock;
window.systemTimePointer = systemTimePointer;

Alpine.data('serverClock', serverClock);
Alpine.data('systemTimePointer', systemTimePointer);

Alpine.start();
