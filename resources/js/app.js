import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

Alpine.plugin(focus);

Alpine.data('bookingForm', (machineId, selectedDate) => ({
    machineId, selectedDate, slots: [], selectedTimes: [], remainingMinutes: 0, maxNewBookings: 0,
    loading: false, submitting: false, error: '', message: '', timer: null, requestId: 0,
    get overQuota() { return this.selectedTimes.length * 60 > this.remainingMinutes || this.selectedTimes.length > this.maxNewBookings; },
    init() {
        this.loadSlots();
        this.timer = setInterval(() => { if (!document.hidden && !this.submitting) this.loadSlots(); }, 30000);
    },
    destroy() { clearInterval(this.timer); this.requestId++; },
    toggle(time) {
        this.selectedTimes = this.selectedTimes.includes(time) ? this.selectedTimes.filter(t => t !== time) : [...this.selectedTimes, time];
    },
    async loadSlots() {
        if (!this.machineId || !this.selectedDate) return;
        const id = ++this.requestId;
        this.loading = true; this.error = '';
        try {
            const response = await fetch('/machines/' + encodeURIComponent(this.machineId) + '/slots?date=' + encodeURIComponent(this.selectedDate), { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(response.status === 401 ? 'Votre session a expiré. Reconnectez-vous.' : 'Impossible de charger les créneaux.');
            const data = await response.json();
            if (id !== this.requestId) return;
            this.slots = data.slots.filter(s => s.is_available);
            const previousCount = this.selectedTimes.length;
            this.selectedTimes = this.selectedTimes.filter(t => this.slots.some(s => s.datetime === t));
            this.remainingMinutes = data.remaining_minutes; this.maxNewBookings = data.max_new_bookings;
            this.message = previousCount !== this.selectedTimes.length ? 'Un créneau choisi n’est plus disponible. Vérifiez votre sélection.' : this.slots.length + ' créneaux disponibles';
        } catch (e) { if (id === this.requestId) { this.error = e.message; this.slots = []; } }
        finally { if (id === this.requestId) this.loading = false; }
    }
}));

Alpine.data('liveRevision', () => ({
    revision: null, changed: false, timer: null,
    init() { this.check(); this.timer = setInterval(() => { if (!document.hidden) this.check(); }, 30000); },
    destroy() { clearInterval(this.timer); },
    async check() {
        try {
            const response = await fetch('/calendar-revision', { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            if (this.revision !== null && this.revision !== data.revision) this.changed = true;
            this.revision = data.revision;
        } catch (_) { /* Retain the current view while offline. */ }
    }
}));

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

// Campus clock drives the calendar even when the browser is in another timezone.
export function systemTimePointer(selectedDateStr, serverIsoString, timeZone) {
    return {
        base: new Date(serverIsoString).getTime(), clientBase: Date.now(),
        isVisible: false, topPx: 0, timeFormatted: '', timer: null,
        init() { this.update(); this.timer = setInterval(() => this.update(), 1000); },
        update() {
            const now = new Date(this.base + Date.now() - this.clientBase);
            const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
                timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
            }).formatToParts(now).map(p => [p.type, p.value]));
            this.isVisible = selectedDateStr === parts.year + '-' + parts.month + '-' + parts.day;
            this.topPx = (Number(parts.hour) + Number(parts.minute) / 60 + Number(parts.second) / 3600) * 52;
            this.timeFormatted = parts.hour + ':' + parts.minute;
        },
        destroy() { clearInterval(this.timer); }
    };
}
window.serverClock = serverClock;
window.systemTimePointer = systemTimePointer;

Alpine.data('serverClock', serverClock);
Alpine.data('systemTimePointer', systemTimePointer);

Alpine.start();
