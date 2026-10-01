import http from 'http';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const PORT = process.env.PORT ? parseInt(process.env.PORT, 10) : 3000;

function getAssets() {
    let cssFile = 'assets/app-BaKgmCTs.css';
    let jsFile = 'assets/app-CEzoOuxG.js';
    try {
        const manifest = JSON.parse(fs.readFileSync(path.join(__dirname, 'public/build/manifest.json'), 'utf8'));
        if (manifest['resources/css/app.css']) cssFile = manifest['resources/css/app.css'].file;
        if (manifest['resources/js/app.js']) jsFile = manifest['resources/js/app.js'].file;
    } catch (e) {
        console.warn('Fallback manifest assets');
    }
    return { cssFile, jsFile };
}

// 24 standard 1-hour slots for campus laundry
const ALL_HOURLY_SLOTS = [
    '00:00 - 01:00', '01:00 - 02:00', '02:00 - 03:00', '03:00 - 04:00', '04:00 - 05:00', '05:00 - 06:00',
    '06:00 - 07:00', '07:00 - 08:00', '08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '11:00 - 12:00',
    '12:00 - 13:00', '13:00 - 14:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00',
    '18:00 - 19:00', '19:00 - 20:00', '20:00 - 21:00', '21:00 - 22:00', '22:00 - 23:00', '23:00 - 00:00'
];

function parseTimeToMinutes(t) {
    const parts = t.trim().replace('h', ':00').split(':');
    return parseInt(parts[0], 10) * 60 + (parts[1] ? parseInt(parts[1], 10) : 0);
}

function isSlotBooked(reservations, machineCode, date, slotTime) {
    const [sStartStr, sEndStr] = slotTime.split('-').map(s => s.trim());
    const slotStart = parseTimeToMinutes(sStartStr);
    let slotEnd = parseTimeToMinutes(sEndStr);
    if (slotEnd === 0) slotEnd = 24 * 60;

    for (const r of reservations) {
        const resDate = r.date || '2026-09-30';
        if (resDate !== date) continue;

        if (r.multi) {
            for (const m of r.multi) {
                if (m.code === machineCode) {
                    const [rStartStr, rEndStr] = m.time.split('-').map(s => s.trim());
                    const rStart = parseTimeToMinutes(rStartStr);
                    let rEnd = parseTimeToMinutes(rEndStr);
                    if (rEnd === 0) rEnd = 24 * 60;
                    if (slotStart < rEnd && slotEnd > rStart) return true;
                }
            }
        } else if (r.code === machineCode) {
            const [rStartStr, rEndStr] = r.time.split('-').map(s => s.trim());
            const rStart = parseTimeToMinutes(rStartStr);
            let rEnd = parseTimeToMinutes(rEndStr);
            if (rEnd === 0) rEnd = 24 * 60;
            if (slotStart < rEnd && slotEnd > rStart) return true;
        }
    }
    return false;
}

// In-memory state with 8-hour weekly reservation quota (1h = 1 credit)
const state = {
    isAuthenticated: false, // FIRST SCREEN IS LOGIN
    isAdmin: true, // Role switcher for testing
    weeklyLimit: 8, // 8 hours per week quota
    user: {
        name: 'R. Omari',
        email: 'r.omari@fecc.ma',
        weeklyUsed: 2, // 2 hours used out of 8 (6 hours remaining)
    },
    machines: [
        { code: 'ML1-OM', name: 'Machine à laver 1 Omar', type: 'washer', bg: '#e53935', text: 'text-white', icon: '👕', status: 'available', cap: '9.0 kg', loc: 'Bâtiment Omar, RDC' },
        { code: 'ML2-OM', name: 'Machine à laver 2 Omar', type: 'washer', bg: '#00e676', text: 'text-slate-900', icon: '👕', status: 'in_use', cap: '9.0 kg', loc: 'Bâtiment Omar, RDC' },
        { code: 'ML1-PE', name: 'Machine à laver 1 Petit', type: 'washer', bg: '#2979ff', text: 'text-white', icon: '👕', status: 'available', cap: '8.0 kg', loc: 'Bâtiment Petit, Étage 1' },
        { code: 'ML2-PE', name: 'Machine à laver 2 Petit', type: 'washer', bg: '#ffd600', text: 'text-slate-900', icon: '👕', status: 'reserved', cap: '8.0 kg', loc: 'Bâtiment Petit, Étage 1' },
        { code: 'ML3-PE', name: 'Machine à laver 3 Petit', type: 'washer', bg: '#ff007f', text: 'text-white', icon: '👕', status: 'reserved', cap: '8.5 kg', loc: 'Bâtiment Petit, Étage 1' },
        { code: 'ML4-PE', name: 'Machine à laver 4 Petit', type: 'washer', bg: '#ff9100', text: 'text-white', icon: '👕', status: 'available', cap: '8.5 kg', loc: 'Bâtiment Petit, Étage 2' },
        { code: 'ML3-OM', name: 'Machine à laver 3 Omar', type: 'washer', bg: '#004d40', text: 'text-white', icon: '👕', status: 'available', cap: '10.0 kg', loc: 'Bâtiment Omar, RDC' },
        
        { code: 'SL1-OM', name: 'Sèche-linge 1 Omar', type: 'dryer', bg: '#4e342e', text: 'text-white', icon: '🔄', status: 'available', cap: '9.5 kg', loc: 'Bâtiment Omar, RDC' },
        { code: 'SL2-OM', name: 'Sèche-linge 2 Omar', type: 'dryer', bg: '#4caf50', text: 'text-white', icon: '🔄', status: 'available', cap: '9.5 kg', loc: 'Bâtiment Omar, RDC' },
        { code: 'SL1-PE', name: 'Sèche-linge 1 Petit', type: 'dryer', bg: '#1a237e', text: 'text-white', icon: '🔄', status: 'in_use', cap: '8.0 kg', loc: 'Bâtiment Petit, Étage 1' },
        { code: 'SL2-PE', name: 'Sèche-linge 2 Petit', type: 'dryer', bg: '#827717', text: 'text-white', icon: '🔄', status: 'available', cap: '8.0 kg', loc: 'Bâtiment Petit, Étage 1' },
        { code: 'SL3-PE', name: 'Sèche-linge 3 Petit', type: 'dryer', bg: '#8e24aa', text: 'text-white', icon: '🔄', status: 'available', cap: '8.5 kg', loc: 'Bâtiment Petit, Étage 2' },
        { code: 'SL3-OM', name: 'Sèche-linge 3 Omar', type: 'dryer', bg: '#212121', text: 'text-white', icon: '🔄', status: 'available', cap: '9.5 kg', loc: 'Bâtiment Omar, RDC' },
    ],
    reservations: [
        { date: '2026-09-30', hour: '00 h', time: '00:00 - 01:00', code: 'ML2-OM', bg: '#00e676', textColor: 'text-slate-900', user: 'Alex Rivera', durationHours: 1 },
        { date: '2026-09-30', hour: '06 h', time: '06:00 - 07:00', code: 'SL1-PE', bg: '#1a237e', textColor: 'text-white', user: 'Youssef Alami', durationHours: 1 },
        { 
            date: '2026-09-30',
            hour: '07 h', 
            multi: [
                { time: '07:00 - 09:00', code: 'ML2-PE', bg: '#ffd600', textColor: 'text-slate-900', user: 'Sara Bennani' },
                { time: '07:00 - 09:00', code: 'ML3-PE', bg: '#ff007f', textColor: 'text-white', user: 'Mehdi Tazi' },
                { time: '07:00 - 09:00', code: 'ML2-OM', bg: '#00e676', textColor: 'text-slate-900', user: 'Sara Bennani' }
            ]
        },
        // Pre-seeded reservation for ML1-PE from 2pm to 3pm (14:00 - 15:00) as requested
        { date: '2026-09-30', hour: '14 h', time: '14:00 - 15:00', code: 'ML1-PE', bg: '#2979ff', textColor: 'text-white', user: 'Mehdi Tazi', durationHours: 1 },
        // Pre-seeded reservations for 2026-10-01 (Jeudi, 1 Octobre 2026) matching user screenshot
        { date: '2026-10-01', hour: '00 h', time: '00:00 - 01:00', code: 'SL3-PE', bg: '#8e24aa', textColor: 'text-white', user: 'Coulibaly', durationHours: 1 },
        { date: '2026-10-01', hour: '00 h', time: '00:00 - 02:00', code: 'ML1-PE', bg: '#2979ff', textColor: 'text-white', user: 'ghadi', durationHours: 2 },
        { date: '2026-10-01', hour: '02 h', time: '02:00 - 04:00', code: 'SL2-PE', bg: '#827717', textColor: 'text-white', user: 'ghadi', durationHours: 2 }
    ]
};

function renderLayout(title, content, currentPath = '/', flash = '') {
    const { cssFile, jsFile } = getAssets();
    const remaining = Math.max(0, state.weeklyLimit - state.user.weeklyUsed);

    return `<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${title} - Centrale Casablanca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/build/${cssFile}">
    <script defer src="/build/${jsFile}"></script>
</head>
<body class="min-h-screen bg-[#f4f7f6] text-slate-800 flex font-sans antialiased">

    <!-- Sidebar matching Image 2 -->
    <aside class="w-64 admin-sidebar min-h-screen flex flex-col justify-between shrink-0 shadow-lg select-none">
        <div>
            <!-- Header -->
            <div class="px-5 py-5 flex items-center space-x-3 border-b border-[#00695c]">
                <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center text-[#00796b] shadow font-black text-lg">
                    ${state.isAdmin ? '☺' : '🎓'}
                </div>
                <div>
                    <span class="text-xs font-black tracking-wider uppercase text-white block">
                        ${state.isAdmin ? 'ADMIN PANNEAU' : 'ESPACE ÉTUDIANT'}
                    </span>
                    <span class="text-[10px] text-emerald-200/70 font-semibold block">Centrale Casablanca</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="mt-4 space-y-0.5">
                <a href="/dashboard" class="sidebar-link ${currentPath === '/dashboard' ? 'active' : ''}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>Tableau de bord</span>
                </a>

                <a href="/reservations" class="sidebar-link ${currentPath === '/reservations' ? 'active' : ''}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Réservations</span>
                </a>

                <a href="/calendrier" class="sidebar-link ${currentPath === '/calendrier' ? 'active' : ''}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Calendrier des réservations</span>
                </a>

                <a href="/reserver" class="sidebar-link ${currentPath === '/reserver' ? 'active' : ''}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Nouvelle réservation</span>
                </a>

                <a href="/machines" class="sidebar-link ${currentPath === '/machines' ? 'active' : ''}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3" stroke-width="2"/><circle cx="12" cy="13" r="4" stroke-width="2"/></svg>
                    <span>Machines</span>
                </a>

                ${state.isAdmin ? `
                    <a href="/utilisateurs" class="sidebar-link ${currentPath === '/utilisateurs' ? 'active' : ''}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>Gestion des utilisateurs</span>
                    </a>
                ` : ''}

                <a href="/reclamations" class="sidebar-link ${currentPath === '/reclamations' ? 'active' : ''}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    <span>Réclamations</span>
                </a>

                ${state.isAdmin ? `
                    <a href="/parametres" class="sidebar-link flex items-center justify-between ${currentPath === '/parametres' ? 'active' : ''}">
                        <div class="flex items-center space-x-3">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                            <span>Paramètres</span>
                        </div>
                        <span class="text-xs text-emerald-200/60">&rsaquo;</span>
                    </a>
                ` : ''}
            </nav>
        </div>

        <div class="p-4 border-t border-[#00695c] flex items-center justify-between">
            <a href="/logout" title="Se déconnecter" class="flex items-center space-x-2 text-xs text-emerald-200/80 hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span>Déconnexion</span>
            </a>
            <span class="text-[10px] text-emerald-200/50">v2.0</span>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        
        <!-- Top Navbar -->
        <header class="h-14 bg-white border-b border-slate-200 px-6 flex items-center justify-between shrink-0 sticky top-0 z-30 shadow-xs">
            <div class="flex items-center space-x-3">
                <span class="text-xs text-slate-400 font-medium">laundry.fecc.ma${currentPath}</span>
                
                <a href="/toggle-role" class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition-all ${state.isAdmin ? 'bg-amber-50 text-amber-800 border-amber-300' : 'bg-blue-50 text-blue-800 border-blue-300'}" title="Cliquez pour basculer entre vue Administrateur et vue Étudiant">
                    ${state.isAdmin ? '👑 Mode: ADMIN (Cliquez pour tester vue Étudiant)' : '🎓 Mode: ÉTUDIANT (Cliquez pour tester vue Admin)'}
                </a>
            </div>

            <div class="flex items-center space-x-5">
                <div class="flex items-center space-x-2 px-3 py-1 rounded-full ${state.isAdmin ? 'bg-amber-50 text-amber-900 border border-amber-200' : (remaining > 0 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200')} text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full ${state.isAdmin ? 'bg-amber-500' : (remaining > 0 ? 'bg-emerald-500' : 'bg-rose-500')}"></span>
                    <span>
                        ${state.isAdmin ? 'Quota : Illimité (Admin)' : `Quota : ${state.user.weeklyUsed}h / ${state.weeklyLimit}h cette semaine (${remaining}h restante${remaining > 1 ? 's' : ''})`}
                    </span>
                </div>

                <div class="flex items-center space-x-1 cursor-pointer">
                    <span class="text-base" title="Français">🇫🇷</span>
                </div>

                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-full bg-slate-200 border border-slate-300 flex items-center justify-center overflow-hidden">
                        <svg class="w-5 h-5 text-slate-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                    <span class="text-xs font-semibold text-slate-700">${state.isAdmin ? 'R. Omari' : 'Alex Rivera'}</span>
                </div>
            </div>
        </header>

        <!-- Flash Toast Notification if exists -->
        ${flash ? `
        <div class="px-6 pt-4">
            <div class="p-3.5 rounded bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-xs flex items-center justify-between shadow-xs">
                <div class="flex items-center space-x-2">
                    <span class="font-bold text-sm">✓</span>
                    <span>${flash}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-700 font-bold">&times;</button>
            </div>
        </div>` : ''}

        <main class="p-6 md:p-8 flex-1">
            ${content}
        </main>
    </div>

    <!-- Machine Selection Script for Direct Reservation Page Link -->
    <script>
    window.currentSelectedMachine = 'ML1-OM';

    function pickMachine(code) {
        window.currentSelectedMachine = code;
        const label = document.getElementById('activeMachineCode');
        if (label) label.innerText = code;
        const dedicatedBtn = document.getElementById('dedicatedPageBtn');
        if (dedicatedBtn) dedicatedBtn.href = '/reserver?machine=' + encodeURIComponent(code);
        
        document.querySelectorAll('.badge-machine-btn').forEach(btn => {
            if (btn.getAttribute('data-code') === code) {
                btn.classList.add('ring-3', 'ring-slate-900', 'scale-105', 'shadow-md');
            } else {
                btn.classList.remove('ring-3', 'ring-slate-900', 'scale-105', 'shadow-md');
            }
        });
    }
    </script>
</body>
</html>`;
}

// 1. Calendrier Page (Image 2) with date navigation
function renderCalendarPage(selectedDateStr = '2026-09-30') {
    const washers = state.machines.filter(m => m.type === 'washer');
    const dryers = state.machines.filter(m => m.type === 'dryer');
    const hours = ['00 h', '01 h', '02 h', '03 h', '04 h', '05 h', '06 h', '07 h', '08 h', '09 h', '10 h', '11 h', '12 h', '13 h', '14 h', '15 h', '16 h', '17 h', '18 h', '19 h', '20 h', '21 h', '22 h', '23 h'];
    const remaining = Math.max(0, state.weeklyLimit - state.user.weeklyUsed);

    const d = new Date(selectedDateStr + 'T00:00:00');
    const prevDate = new Date(d);
    prevDate.setDate(prevDate.getDate() - 1);
    const prevDateStr = prevDate.toISOString().split('T')[0];

    const nextDate = new Date(d);
    nextDate.setDate(nextDate.getDate() + 1);
    const nextDateStr = nextDate.toISOString().split('T')[0];

    const todayStr = '2026-09-30';

    const dayNames = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    const monthNames = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    const dayName = isNaN(d.getDay()) ? 'mercredi' : dayNames[d.getDay()];
    const dateFormatted = isNaN(d.getDate()) ? '30 septembre 2026' : (d.getDate() + ' ' + monthNames[d.getMonth()] + ' ' + d.getFullYear());

    // Calculate Monday of the current selected week
    const currentDayOfWeek = d.getDay();
    const diffToMonday = currentDayOfWeek === 0 ? -6 : 1 - currentDayOfWeek;
    const monday = new Date(d);
    monday.setDate(monday.getDate() + diffToMonday);

    const weekDays = [];
    const shortDays = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    for (let i = 0; i < 7; i++) {
        const wDay = new Date(monday);
        wDay.setDate(monday.getDate() + i);
        const wDateStr = wDay.toISOString().split('T')[0];
        weekDays.push({
            date: wDateStr,
            dayNumber: wDay.getDate(),
            shortName: shortDays[wDay.getDay()],
            isToday: wDateStr === todayStr,
            isSelected: wDateStr === selectedDateStr,
        });
    }

    // 1. Gather all reservations for this date
    const dayRawReservations = [];
    state.reservations.forEach(r => {
        const rDate = r.date || '2026-09-30';
        if (rDate === selectedDateStr) {
            if (r.multi) {
                r.multi.forEach(m => {
                    dayRawReservations.push({
                        ...m,
                        date: rDate,
                        time: m.time,
                        code: m.code,
                        user: m.user,
                        bg: m.bg,
                        textColor: m.textColor,
                    });
                });
            } else {
                dayRawReservations.push({
                    ...r,
                    date: rDate,
                    time: r.time,
                    code: r.code,
                    user: r.user,
                    bg: r.bg,
                    textColor: r.textColor,
                });
            }
        }
    });

    // 2. Parse start and end times to minutes
    const dayParsed = dayRawReservations.map(r => {
        const [startStr, endStr] = r.time.split('-').map(s => s.trim());
        const startMinutes = parseTimeToMinutes(startStr);
        let endMinutes = parseTimeToMinutes(endStr);
        if (endMinutes === 0 && startMinutes > 0) endMinutes = 24 * 60;
        if (endMinutes <= startMinutes) endMinutes = startMinutes + 60;
        return {
            ...r,
            startMinutes,
            endMinutes,
            durationMinutes: endMinutes - startMinutes,
        };
    });

    // 3. Sort by machine code, then startMinutes
    dayParsed.sort((a, b) => {
        if (a.code !== b.code) return a.code.localeCompare(b.code);
        return a.startMinutes - b.startMinutes;
    });

    // 4. Coalesce adjacent / contiguous reservations for SAME machine and SAME user into a single continuous block
    const mergedBlocks = [];
    for (const res of dayParsed) {
        const last = mergedBlocks[mergedBlocks.length - 1];
        if (last && last.code === res.code && last.user && last.user === res.user && res.startMinutes <= last.endMinutes) {
            last.endMinutes = Math.max(last.endMinutes, res.endMinutes);
            last.durationMinutes = last.endMinutes - last.startMinutes;
            const startH = String(Math.floor(last.startMinutes / 60)).padStart(2, '0') + ':' + String(last.startMinutes % 60).padStart(2, '0');
            const endH = String(Math.floor(last.endMinutes / 60)).padStart(2, '0') + ':' + String(last.endMinutes % 60).padStart(2, '0');
            last.time = `${startH} - ${endH}`;
        } else {
            mergedBlocks.push({ ...res });
        }
    }

    // 5. Cluster overlapping blocks for side-by-side columns
    mergedBlocks.sort((a, b) => {
        if (a.startMinutes === b.startMinutes) {
            return b.durationMinutes - a.durationMinutes;
        }
        return a.startMinutes - b.startMinutes;
    });

    const clusters = [];
    let currentCluster = [];
    let clusterEnd = 0;

    for (const block of mergedBlocks) {
        if (currentCluster.length === 0) {
            currentCluster.push(block);
            clusterEnd = block.endMinutes;
        } else {
            if (block.startMinutes < clusterEnd) {
                currentCluster.push(block);
                clusterEnd = Math.max(clusterEnd, block.endMinutes);
            } else {
                clusters.push(currentCluster);
                currentCluster = [block];
                clusterEnd = block.endMinutes;
            }
        }
    }
    if (currentCluster.length > 0) {
        clusters.push(currentCluster);
    }

    const hourHeight = 52;
    const calendarBlocks = [];

    for (const cluster of clusters) {
        const columns = []; // tracks end minute of last event in column
        const assignments = [];

        cluster.forEach((b, idx) => {
            let placed = false;
            for (let c = 0; c < columns.length; c++) {
                if (b.startMinutes >= columns[c]) {
                    columns[c] = b.endMinutes;
                    assignments[idx] = c;
                    placed = true;
                    break;
                }
            }
            if (!placed) {
                assignments[idx] = columns.length;
                columns.push(b.endMinutes);
            }
        });

        const numCols = Math.max(1, columns.length);

        cluster.forEach((b, idx) => {
            const colIdx = assignments[idx];
            const widthPct = 100 / numCols;
            const leftPct = colIdx * widthPct;

            const top = (b.startMinutes / 60) * hourHeight;
            const height = (b.durationMinutes / 60) * hourHeight;

            const durFormatted = b.durationMinutes >= 60
                ? (b.durationMinutes % 60 === 0 ? (b.durationMinutes / 60) + ' h' : `${Math.floor(b.durationMinutes / 60)}h${String(b.durationMinutes % 60).padStart(2, '0')}`)
                : `${b.durationMinutes} min`;

            calendarBlocks.push({
                ...b,
                top,
                height: Math.max(34, height),
                leftPct,
                widthPct,
                numCols,
                colIdx,
                durationFormatted: durFormatted,
            });
        });
    }

    return `
    <div class="space-y-6 max-w-6xl mx-auto">
        <div class="bg-white border-l-4 border-[#00897b] p-3.5 rounded shadow-xs flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-5 h-5 rounded-full bg-[#00897b]/10 text-[#00897b] flex items-center justify-center font-bold text-xs shrink-0">i</div>
                <span class="text-xs text-slate-700">Cliquez sur une machine pour voir ses créneaux et réserver directement.</span>
            </div>
            <div class="text-xs font-semibold text-[#00897b]">
                ${state.isAdmin ? 'Régime Administrateur (Illimité)' : `Quota restant : ${remaining}h sur ${state.weeklyLimit}h cette semaine (1h = 1 crédit)`}
            </div>
        </div>

        <div class="max-w-md mx-auto">
            <input type="text" placeholder="Rechercher une machine..." 
                   class="w-full px-4 py-2 bg-white border border-slate-300 rounded text-xs placeholder-slate-400 focus:outline-none focus:border-[#00897b] shadow-xs">
        </div>

        <!-- Machine Selection Matrix matching Image 2 -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden max-w-2xl mx-auto">
            <div class="grid grid-cols-2 bg-[#00897b] text-white text-xs font-bold text-center py-2.5">
                <div>Machine à laver</div>
                <div>Sèche-linge</div>
            </div>

            <div class="grid grid-cols-2 divide-x divide-slate-200 p-4">
                <div class="grid grid-cols-3 gap-2 pr-3">
                    ${washers.map(m => `
                        <button type="button" 
                                onclick="pickMachine('${m.code}')" 
                                data-code="${m.code}"
                                style="background-color: ${m.bg};"
                                class="badge-machine badge-machine-btn ${m.text} ${m.code === 'ML1-OM' ? 'ring-3 ring-slate-900 scale-105 shadow-md' : ''}">
                            <span>${m.code}</span>
                            <span class="text-sm">${m.icon}</span>
                        </button>
                    `).join('')}
                </div>

                <div class="grid grid-cols-2 gap-2 pl-3">
                    ${dryers.map(m => `
                        <button type="button" 
                                onclick="pickMachine('${m.code}')" 
                                data-code="${m.code}"
                                style="background-color: ${m.bg};"
                                class="badge-machine badge-machine-btn ${m.text}">
                            <span>${m.code}</span>
                            <span class="text-xs">${m.icon}</span>
                        </button>
                    `).join('')}
                </div>
            </div>
        </div>

        <!-- Action Button Réserver (Direct Link to Dedicated Page) -->
        <div class="flex flex-col sm:flex-row justify-between items-center gap-3 max-w-2xl mx-auto bg-slate-50 p-3.5 rounded-lg border border-slate-200 shadow-xs">
            <div class="text-xs text-slate-600">
                Machine sélectionnée : <span id="activeMachineCode" class="font-bold text-[#00897b] bg-[#00897b]/10 px-2.5 py-1 rounded text-sm">ML1-OM</span>
            </div>
            <div>
                <a id="dedicatedPageBtn" href="/reserver?machine=ML1-OM" 
                   class="px-6 py-2.5 rounded bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold transition-all shadow-md flex items-center space-x-2 cursor-pointer active:scale-95">
                    <span>Réserver cette machine</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <!-- Date Controls and 7-day strip -->
        <div class="space-y-3 pt-4 border-t border-slate-200">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <h2 class="text-xl font-normal text-slate-700 capitalize">${dayName}, ${dateFormatted}</h2>
                    <input type="date" value="${selectedDateStr}" 
                           onchange="window.location.href = '/calendrier?date=' + this.value"
                           class="px-2.5 py-1 text-xs border border-slate-300 rounded bg-white text-slate-700 hover:border-[#00897b] focus:outline-none focus:border-[#00897b] cursor-pointer shadow-xs font-medium"
                           title="Choisir une date quelconque">
                </div>
                <div class="inline-flex rounded shadow-xs text-xs">
                    <a href="/calendrier?date=${todayStr}" class="px-3.5 py-1.5 ${selectedDateStr === todayStr ? 'bg-[#00897b] text-white font-bold' : 'bg-[#546e7a] hover:bg-[#455a64] text-white font-medium'} rounded-l transition-colors flex items-center">Aujourd'hui</a>
                    <a href="/calendrier?date=${prevDateStr}" class="px-3.5 py-1.5 bg-[#37474f] hover:bg-[#263238] text-white font-medium transition-colors flex items-center space-x-1" title="Jour précédent (${prevDateStr})">
                        <span>&larr;</span>
                        <span>Précédent</span>
                    </a>
                    <a href="/calendrier?date=${nextDateStr}" class="px-3.5 py-1.5 bg-[#263238] hover:bg-black text-white font-medium rounded-r transition-colors flex items-center space-x-1" title="Jour suivant (${nextDateStr})">
                        <span>Suivant</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </div>

            <!-- 7-Day Quick Jump Strip -->
            <div class="grid grid-cols-7 gap-1 sm:gap-2 p-1.5 bg-white rounded-lg border border-slate-200 shadow-xs">
                ${weekDays.map(w => `
                    <a href="/calendrier?date=${w.date}" 
                       class="py-2 px-1 text-center rounded transition-all flex flex-col items-center justify-center ${w.isSelected ? 'bg-[#00897b] text-white font-bold shadow-xs scale-102' : 'hover:bg-slate-100 text-slate-700'}">
                        <span class="text-[10px] uppercase font-semibold ${w.isSelected ? 'text-emerald-100' : 'text-slate-400'}">${w.shortName}</span>
                        <span class="text-sm font-bold ${w.isSelected ? 'text-white' : (w.isToday ? 'text-[#00897b]' : 'text-slate-800')}">${w.dayNumber}</span>
                        ${w.isToday ? `<span class="w-1.5 h-1.5 rounded-full ${w.isSelected ? 'bg-white' : 'bg-[#00897b]'} mt-0.5"></span>` : '<span class="w-1.5 h-1.5 mt-0.5"></span>'}
                    </a>
                `).join('')}
            </div>
        </div>

        <!-- Timetable / Calendar Timeline Grid with Continuous Blocks & Limitor Lines -->
        <div class="bg-white rounded-lg border border-slate-300 shadow-xs overflow-hidden">
            <div class="flex border-b border-slate-300 bg-[#f9f9e8] text-xs font-semibold text-slate-700">
                <div class="w-16 sm:w-20 p-2.5 text-center border-r border-slate-300 text-[11px] text-slate-500 font-semibold tracking-tight">Horaires</div>
                <div class="flex-1 p-2.5 text-center font-bold text-slate-800 capitalize flex items-center justify-center space-x-2">
                    <span>${dayName} (${dateFormatted})</span>
                    ${calendarBlocks.length > 0 ? `<span class="text-[10px] font-normal text-slate-500 bg-white/80 px-2 py-0.5 rounded border border-slate-200">${calendarBlocks.length} créneau${calendarBlocks.length > 1 ? 'x' : ''} continu${calendarBlocks.length > 1 ? 's' : ''}</span>` : ''}
                </div>
            </div>

            <div class="relative overflow-x-auto">
                <div class="flex min-w-[620px] relative select-none">
                    
                    <!-- Left Axis: Hours of the Day directly ON the line as limitor indicators -->
                    <div class="w-16 sm:w-20 shrink-0 border-r border-slate-300 bg-slate-50/70 relative select-none" style="height: ${24 * 52}px;">
                        ${Array.from({ length: 25 }, (_, h) => {
                            const top = h * 52;
                            const hourLabel = String(h === 24 ? 24 : h).padStart(2, '0') + ':00';
                            return `
                            <div class="absolute right-0 pr-2 flex items-center -translate-y-1/2 pointer-events-none" style="top: ${top}px;">
                                <span class="text-[11px] font-semibold text-slate-600 font-mono tracking-tight">${hourLabel}</span>
                                <span class="w-1.5 h-[1.5px] bg-slate-400 ml-1.5 inline-block"></span>
                            </div>`;
                        }).join('')}
                    </div>

                    <!-- Schedule Area: Horizontal Limitor Lines & Continuous Blocks -->
                    <div class="flex-1 relative bg-white" style="height: ${24 * 52}px;">
                        
                        <!-- Background: 24 hour rows with click-to-book and horizontal divider lines -->
                        ${Array.from({ length: 24 }, (_, h) => {
                            const top = h * 52;
                            const hourStr = String(h).padStart(2, '0') + ':00';
                            return `
                            <div class="absolute left-0 right-0 border-t border-slate-200 pointer-events-none" style="top: ${top}px;"></div>
                            <div class="absolute left-0 right-0 border-t border-dashed border-slate-100 pointer-events-none" style="top: ${top + 26}px;"></div>
                            <a href="/reserver?machine=ML1-OM&date=${selectedDateStr}&hour=${encodeURIComponent(hourStr)}" 
                               class="absolute left-0 right-0 h-[52px] hover:bg-slate-50/60 transition-colors group cursor-pointer"
                               style="top: ${top}px;"
                               title="Cliquer pour réserver le créneau ${hourStr}">
                                <div class="w-full h-full flex items-center px-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <span class="text-[10px] text-slate-400 font-medium">+ Réserver à ${hourStr}</span>
                                </div>
                            </a>`;
                        }).join('')}
                        <div class="absolute left-0 right-0 border-t border-slate-300 pointer-events-none" style="top: ${24 * 52}px;"></div>

                        <!-- Continuous Blocks -->
                        ${calendarBlocks.map(block => {
                            const isMultiHour = block.durationMinutes > 60;
                            const machineColor = block.bg || '#00897b';
                            return `
                            <div style="background-color: ${machineColor}; top: ${block.top + 1}px; height: ${block.height - 2}px; left: calc(${block.leftPct}% + 4px); width: calc(${block.widthPct}% - 8px);"
                                 class="absolute rounded-md text-white overflow-hidden transition-all cursor-pointer border border-white/20 select-none shadow-sm hover:shadow-md hover:brightness-105 z-20"
                                 onclick="pickMachine('${block.code}')"
                                 title="${block.code} • ${block.time} (${block.user || 'Occupé'}) - Cliquer pour sélectionner la machine">
                                
                                ${!isMultiHour ? `
                                    <div class="h-full px-2.5 py-1 flex items-center justify-between text-xs leading-none">
                                        <div class="flex items-center space-x-1.5 truncate">
                                            <span class="font-mono font-bold text-[11px] bg-black/25 px-1.5 py-0.5 rounded">${block.time}</span>
                                            <span class="opacity-60">•</span>
                                            <span class="font-bold text-[12px] truncate">${block.code}</span>
                                        </div>
                                        ${block.user ? `
                                            <span class="opacity-90 text-[11px] font-medium truncate max-w-[140px] ml-2">(${block.user})</span>
                                        ` : ''}
                                    </div>
                                ` : `
                                    <div class="h-full p-2.5 flex flex-col justify-between">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <div class="inline-flex items-center space-x-1.5 px-2 py-0.5 rounded bg-black/25 font-mono font-bold text-[11px] tracking-tight mb-1">
                                                    <span>${block.time}</span>
                                                    <span class="opacity-75">(${block.durationFormatted})</span>
                                                </div>
                                                <div class="text-sm font-black flex items-center space-x-1.5">
                                                    <span>${block.code}</span>
                                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-white/20 uppercase tracking-wider">
                                                        Créneau continu
                                                    </span>
                                                </div>
                                            </div>
                                            ${block.user ? `
                                                <div class="text-right shrink-0">
                                                    <span class="text-[9px] uppercase font-semibold tracking-wider opacity-75 block">Réservé par</span>
                                                    <span class="text-xs font-bold bg-white/15 px-2 py-0.5 rounded inline-block mt-0.5">${block.user}</span>
                                                </div>
                                            ` : ''}
                                        </div>
                                        ${block.height >= 80 ? `
                                            <div class="flex items-center justify-between text-[10px] opacity-80 pt-1.5 border-t border-white/20">
                                                <span class="flex items-center space-x-1">
                                                    <span>Machine :</span>
                                                    <strong class="font-bold">${block.code}</strong>
                                                </span>
                                                <span class="italic text-[9px]">Cliquer pour sélectionner</span>
                                            </div>
                                        ` : ''}
                                    </div>
                                `}
                            </div>`;
                        }).join('')}

                        ${calendarBlocks.length === 0 ? `
                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                <div class="bg-white/90 border border-slate-200 shadow-sm rounded-lg p-4 text-center max-w-sm">
                                    <span class="text-2xl block mb-1">📅</span>
                                    <span class="text-xs font-bold text-slate-700 block">Aucune réservation pour cette journée</span>
                                    <span class="text-[11px] text-slate-500 block mt-1">Cliquez sur un créneau horaire ou sélectionnez une machine ci-dessus pour réserver.</span>
                                </div>
                            </div>
                        ` : ''}

                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

// Dedicated full page for reservation (Multi-slot selection + 8h quota system)
function renderDedicatedReservationPage(selectedMachine = 'ML1-OM') {
    const washers = state.machines.filter(m => m.type === 'washer');
    const dryers = state.machines.filter(m => m.type === 'dryer');
    const remaining = Math.max(0, state.weeklyLimit - state.user.weeklyUsed);
    
    // Initial available slots for selected machine
    const initDate = '2026-09-30';
    const initAvailable = ALL_HOURLY_SLOTS.filter(s => !isSlotBooked(state.reservations, selectedMachine, initDate, s));

    return `
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 tracking-tight">Réserver une machine</h1>
                <p class="text-xs text-slate-500">Planification des créneaux horaires disponibles par machine</p>
            </div>
            <a href="/calendrier" class="text-xs text-[#00897b] hover:underline font-semibold flex items-center space-x-1">
                <span>&larr;</span>
                <span>Retour au calendrier</span>
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden">
            <div class="bg-[#00897b] px-6 py-4 text-white flex items-center justify-between">
                <span class="text-sm font-bold">Sélection des créneaux de réservation</span>
                <span id="activeBadgeHeader" class="text-xs bg-white/20 px-2.5 py-1 rounded font-mono font-bold">${selectedMachine}</span>
            </div>

            <form method="POST" action="/reserver" class="p-6 space-y-6 text-xs" id="bookingForm">
                <!-- Machine and Date Selectors -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Machine Choice (JUST THE CLEAN CODES - NO PARENTHESES!) -->
                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Sélectionner la machine
                        </label>
                        <select name="machine" id="machineSelect" onchange="onMachineOrDateChange()"
                                class="w-full px-3.5 py-2.5 border border-slate-300 rounded text-xs focus:border-[#00897b] focus:outline-none bg-slate-50 font-bold text-slate-800">
                            <optgroup label="Machines à laver">
                                ${washers.map(w => `<option value="${w.code}" ${w.code === selectedMachine ? 'selected' : ''}>${w.code}</option>`).join('')}
                            </optgroup>
                            <optgroup label="Sèche-linge">
                                ${dryers.map(d => `<option value="${d.code}" ${d.code === selectedMachine ? 'selected' : ''}>${d.code}</option>`).join('')}
                            </optgroup>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Date de réservation
                        </label>
                        <input type="date" name="date" id="dateInput" value="2026-09-30" onchange="onMachineOrDateChange()"
                               class="w-full px-3.5 py-2.5 border border-slate-300 rounded text-xs focus:border-[#00897b] focus:outline-none bg-white">
                    </div>
                </div>

                <!-- Multi-Slot Interactive Selection Grid -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block font-bold text-slate-700 uppercase tracking-wider">
                            Créneaux horaires disponibles (Sélection multiple possible)
                        </label>
                        <span id="availableCountBadge" class="text-[11px] text-slate-500 font-semibold font-mono">
                            ${initAvailable.length} créneaux disponibles
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 mb-3">
                        Cochez un ou plusieurs créneaux d'1 heure consécutifs ou distincts sur cette machine. Les heures déjà réservées sont masquées automatiquement pour éviter les doublons.
                    </p>

                    <div id="slotsGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 max-h-72 overflow-y-auto p-2 border border-slate-200 rounded-lg bg-slate-50/50">
                        <!-- Populated dynamically by updateSlotsView() -->
                    </div>
                </div>

                <!-- Quota & Credit Surveillance Summary Box (8 hours / week) -->
                <div class="p-4 rounded-lg bg-emerald-50/80 border border-emerald-200 text-xs space-y-2.5">
                    <div class="flex items-center justify-between font-bold text-emerald-900 border-b border-emerald-200/60 pb-2">
                        <span class="flex items-center space-x-1.5">
                            <span>⚡</span>
                            <span>Surveillance du Quota Hebdomadaire (8h / semaine)</span>
                        </span>
                        <span class="text-xs font-mono bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded">
                            1h de créneau = 1 crédit
                        </span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-slate-700 pt-1">
                        <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                            <span class="text-[10px] uppercase text-slate-400 font-bold block">Créneaux choisis</span>
                            <span id="statSelectedHours" class="font-bold text-slate-800 text-sm font-mono">0 heure</span>
                        </div>

                        <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                            <span class="text-[10px] uppercase text-slate-400 font-bold block">Coût total</span>
                            <span id="statTotalCost" class="font-bold text-[#00897b] text-sm font-mono">0 crédit</span>
                        </div>

                        <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                            <span class="text-[10px] uppercase text-slate-400 font-bold block">Solde actuel</span>
                            <span id="statCurrentBalance" class="font-bold text-slate-800 text-sm font-mono">${state.isAdmin ? 'Illimité (Admin)' : `${remaining}h / 8h`}</span>
                        </div>

                        <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                            <span class="text-[10px] uppercase text-slate-400 font-bold block">Solde après</span>
                            <span id="statBalanceAfter" class="font-bold text-emerald-700 text-sm font-mono">${state.isAdmin ? 'Illimité' : `${remaining}h / 8h`}</span>
                        </div>
                    </div>

                    <!-- Insufficient Quota Alert -->
                    <div id="quotaExceededAlert" class="hidden p-2.5 bg-rose-50 border border-rose-200 text-rose-800 rounded font-semibold text-[11px] flex items-center space-x-2">
                        <span>⚠️</span>
                        <span>Dépassement de quota : vous avez sélectionné plus d'heures que votre solde hebdomadaire restant (<span id="alertRemainingSpan">${remaining}</span>h disponibles sur 8h).</span>
                    </div>
                </div>

                <div class="pt-3 flex items-center justify-end space-x-3 border-t border-slate-200">
                    <a href="/calendrier" 
                       class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded">
                        Annuler
                    </a>
                    <button type="submit" id="submitBookingBtn" disabled
                            class="px-6 py-2.5 opacity-50 cursor-not-allowed bg-slate-400 text-white text-xs font-bold rounded transition-all shadow-xs">
                        Sélectionnez au moins 1 créneau
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-Side Reactivity Script -->
    <script>
    const ALL_SLOTS = ${JSON.stringify(ALL_HOURLY_SLOTS)};
    const RESERVATIONS = ${JSON.stringify(state.reservations)};
    const WEEKLY_LIMIT = ${state.weeklyLimit};
    let WEEKLY_USED = ${state.user.weeklyUsed};
    const IS_ADMIN = ${state.isAdmin ? 'true' : 'false'};

    function parseTimeMins(t) {
        const parts = t.trim().replace('h', ':00').split(':');
        return parseInt(parts[0], 10) * 60 + (parts[1] ? parseInt(parts[1], 10) : 0);
    }

    function isSlotReserved(machineCode, date, slotTime) {
        const [sStartStr, sEndStr] = slotTime.split('-').map(s => s.trim());
        const slotStart = parseTimeMins(sStartStr);
        let slotEnd = parseTimeMins(sEndStr);
        if (slotEnd === 0) slotEnd = 24 * 60;

        for (const r of RESERVATIONS) {
            const resDate = r.date || '2026-09-30';
            if (resDate !== date) continue;

            if (r.multi) {
                for (const m of r.multi) {
                    if (m.code === machineCode) {
                        const [rStartStr, rEndStr] = m.time.split('-').map(s => s.trim());
                        const rStart = parseTimeMins(rStartStr);
                        let rEnd = parseTimeMins(rEndStr);
                        if (rEnd === 0) rEnd = 24 * 60;
                        if (slotStart < rEnd && slotEnd > rStart) return true;
                    }
                }
            } else if (r.code === machineCode) {
                const [rStartStr, rEndStr] = r.time.split('-').map(s => s.trim());
                const rStart = parseTimeMins(rStartStr);
                let rEnd = parseTimeMins(rEndStr);
                if (rEnd === 0) rEnd = 24 * 60;
                if (slotStart < rEnd && slotEnd > rStart) return true;
            }
        }
        return false;
    }

    function onMachineOrDateChange() {
        updateSlotsView();
    }

    function updateSlotsView() {
        const machine = document.getElementById('machineSelect').value;
        const date = document.getElementById('dateInput').value;
        const grid = document.getElementById('slotsGrid');
        const headerBadge = document.getElementById('activeBadgeHeader');
        if (headerBadge) headerBadge.innerText = machine;

        // Find which slots are booked
        const bookedSlots = [];
        const availableSlots = [];
        ALL_SLOTS.forEach(slot => {
            if (isSlotReserved(machine, date, slot)) {
                bookedSlots.push(slot);
            } else {
                availableSlots.push(slot);
            }
        });

        // Update count badge
        document.getElementById('availableCountBadge').innerText = availableSlots.length + ' créneaux disponibles';

        // Render available slots
        grid.innerHTML = availableSlots.map((slot, idx) => {
            return '<label id="card-' + idx + '" class="flex items-center justify-between p-2.5 rounded border border-slate-200 bg-white text-slate-700 hover:border-[#00897b] transition-all cursor-pointer select-none text-xs">' +
                '<input type="checkbox" name="hours" value="' + slot + '" id="slot-cb-' + idx + '" onchange="onSlotToggle(' + idx + ')" class="slot-checkbox hidden">' +
                '<span class="font-mono font-medium">' + slot + '</span>' +
                '<span id="check-' + idx + '" class="w-4 h-4 rounded-full border border-slate-300 flex items-center justify-center text-[10px]"></span>' +
            '</label>';
        }).join('');

        updateSummary();
    }

    function onSlotToggle(idx) {
        const cb = document.getElementById('slot-cb-' + idx);
        const card = document.getElementById('card-' + idx);
        const check = document.getElementById('check-' + idx);

        if (cb.checked) {
            card.classList.add('border-[#00897b]', 'bg-[#e0f2f1]', 'text-[#00695c]', 'font-bold', 'shadow-xs');
            card.classList.remove('border-slate-200', 'bg-white', 'text-slate-700');
            check.classList.add('bg-[#00897b]', 'border-[#00897b]', 'text-white');
            check.classList.remove('border-slate-300');
            check.innerHTML = '✓';
        } else {
            card.classList.remove('border-[#00897b]', 'bg-[#e0f2f1]', 'text-[#00695c]', 'font-bold', 'shadow-xs');
            card.classList.add('border-slate-200', 'bg-white', 'text-slate-700');
            check.classList.remove('bg-[#00897b]', 'border-[#00897b]', 'text-white');
            check.classList.add('border-slate-300');
            check.innerHTML = '';
        }

        updateSummary();
    }

    function updateSummary() {
        const checkedBoxes = document.querySelectorAll('.slot-checkbox:checked');
        const count = checkedBoxes.length;
        const remaining = Math.max(0, WEEKLY_LIMIT - WEEKLY_USED);

        document.getElementById('statSelectedHours').innerText = count + ' heure' + (count > 1 ? 's' : '');
        document.getElementById('statTotalCost').innerText = count + ' crédit' + (count > 1 ? 's' : '');

        const balanceAfterEl = document.getElementById('statBalanceAfter');
        const alertEl = document.getElementById('quotaExceededAlert');
        const btn = document.getElementById('submitBookingBtn');

        if (IS_ADMIN) {
            balanceAfterEl.innerText = 'Illimité (Admin)';
            balanceAfterEl.className = 'font-bold text-emerald-700 text-sm font-mono';
            alertEl.classList.add('hidden');
            if (count > 0) {
                btn.disabled = false;
                btn.className = 'px-6 py-2.5 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded transition-all shadow-xs cursor-pointer';
                btn.innerText = 'Confirmer la réservation (' + count + 'h • ' + count + ' crédit' + (count > 1 ? 's' : '') + ')';
            } else {
                btn.disabled = true;
                btn.className = 'px-6 py-2.5 opacity-50 cursor-not-allowed bg-slate-400 text-white text-xs font-bold rounded transition-all shadow-xs';
                btn.innerText = 'Sélectionnez au moins 1 créneau';
            }
            return;
        }

        const afterBalance = remaining - count;
        if (afterBalance < 0) {
            balanceAfterEl.innerText = afterBalance + 'h / 8h (Dépassé)';
            balanceAfterEl.className = 'font-bold text-rose-600 text-sm font-mono';
            alertEl.classList.remove('hidden');
            document.getElementById('alertRemainingSpan').innerText = remaining;
            btn.disabled = true;
            btn.className = 'px-6 py-2.5 opacity-50 cursor-not-allowed bg-rose-500 text-white text-xs font-bold rounded transition-all shadow-xs';
            btn.innerText = 'Quota insuffisant (' + remaining + 'h restantes)';
        } else {
            balanceAfterEl.innerText = afterBalance + 'h / 8h';
            balanceAfterEl.className = 'font-bold text-emerald-700 text-sm font-mono';
            alertEl.classList.add('hidden');
            if (count > 0) {
                btn.disabled = false;
                btn.className = 'px-6 py-2.5 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded transition-all shadow-xs cursor-pointer';
                btn.innerText = 'Confirmer la réservation (' + count + 'h • ' + count + ' crédit' + (count > 1 ? 's' : '') + ')';
            } else {
                btn.disabled = true;
                btn.className = 'px-6 py-2.5 opacity-50 cursor-not-allowed bg-slate-400 text-white text-xs font-bold rounded transition-all shadow-xs';
                btn.innerText = 'Sélectionnez au moins 1 créneau';
            }
        }
    }

    // Initialize on load
    document.addEventListener('DOMContentLoaded', () => {
        updateSlotsView();
    });
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        updateSlotsView();
    }
    </script>`;
}

// 2. Tableau de bord Page
function renderDashboardPage() {
    const remaining = Math.max(0, state.weeklyLimit - state.user.weeklyUsed);

    return `
    <div class="space-y-6 max-w-6xl mx-auto">
        <h1 class="text-xl font-bold text-slate-800">Vue d'ensemble Buanderie</h1>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase">Machines Disponibles</span>
                <p class="text-2xl font-bold text-[#00897b] mt-2">10 / 13</p>
                <span class="text-[11px] text-emerald-600 font-medium">Prêtes à l'emploi</span>
            </div>
            <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase">Quota Hebdomadaire</span>
                <p class="text-2xl font-bold ${remaining > 0 ? 'text-emerald-600' : 'text-rose-600'} mt-2">
                    ${state.isAdmin ? 'Illimité' : `${remaining}h / ${state.weeklyLimit}h`}
                </p>
                <span class="text-[11px] text-slate-500">${state.isAdmin ? 'Admin' : 'Heures restantes cette semaine'}</span>
            </div>
            <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase">Réservations Aujourd'hui</span>
                <p class="text-2xl font-bold text-blue-600 mt-2">${state.reservations.length + 5}</p>
                <span class="text-[11px] text-slate-500">30 septembre 2026</span>
            </div>
            <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase">Taux d'utilisation</span>
                <p class="text-2xl font-bold text-slate-800 mt-2">78%</p>
                <span class="text-[11px] text-slate-500">Heures de pointe 18h-22h</span>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-xs">
            <h2 class="text-sm font-bold text-slate-800 mb-4">Accès rapide</h2>
            <div class="flex flex-wrap gap-3">
                <a href="/calendrier" class="px-4 py-2 bg-[#00897b] text-white rounded text-xs font-bold shadow-xs">Consulter le Calendrier</a>
                <a href="/machines" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded text-xs font-bold">État des machines</a>
                <a href="/reservations" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded text-xs font-bold">Toutes les réservations</a>
            </div>
        </div>
    </div>`;
}

// 3. Réservations Page
function renderReservationsPage() {
    const allRes = [];
    state.reservations.forEach(r => {
        if (r.multi) {
            r.multi.forEach(m => allRes.push({ ...m, hour: r.hour, date: '30 sept 2026' }));
        } else {
            allRes.push({ ...r, date: r.date || '30 sept 2026' });
        }
    });

    return `
    <div class="space-y-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Gestion des Réservations</h1>
                <p class="text-xs text-slate-500">Historique et créneaux planifiés cette semaine</p>
            </div>
            <a href="/reserver" class="px-5 py-2.5 bg-[#00897b] hover:bg-[#00796b] text-white rounded text-xs font-bold shadow-xs flex items-center space-x-1.5">
                <span>+</span>
                <span>Nouvelle réservation</span>
            </a>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 shadow-xs overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#00897b] text-white font-bold">
                    <tr>
                        <th class="p-3.5">Machine</th>
                        <th class="p-3.5">Bénéficiaire</th>
                        <th class="p-3.5">Date & Créneau</th>
                        <th class="p-3.5">Décompte Quota</th>
                        <th class="p-3.5">Statut</th>
                        <th class="p-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    ${allRes.map(res => `
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="p-3.5 font-bold">
                            <span style="background-color: ${res.bg || '#00897b'};" class="px-2.5 py-1 rounded text-xs font-mono font-bold ${res.textColor || 'text-white'} shadow-xs inline-block">
                                ${res.code}
                            </span>
                        </td>
                        <td class="p-3.5 font-semibold text-slate-700">${res.user || (state.isAdmin ? 'R. Omari' : 'Alex Rivera')}</td>
                        <td class="p-3.5 text-slate-600 font-mono">${res.date || '30 sept 2026'} • ${res.time}</td>
                        <td class="p-3.5 text-slate-600 font-semibold">${res.durationHours || 1} h crédit décomptée</td>
                        <td class="p-3.5"><span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">Confirmé</span></td>
                        <td class="p-3.5 text-right"><span class="text-emerald-600 font-semibold text-xs">Actif</span></td>
                    </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    </div>`;
}

// 4. Machines Page
function renderMachinesPage() {
    const washers = state.machines.filter(m => m.type === 'washer');
    const dryers = state.machines.filter(m => m.type === 'dryer');

    return `
    <div class="space-y-6 max-w-6xl mx-auto">
        <h1 class="text-xl font-bold text-slate-800">Parc des Machines Buanderie</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-[#00897b] text-white px-4 py-3 flex items-center justify-between">
                    <span class="font-bold text-sm">Machines à laver (7 unités)</span>
                    <span class="text-xs font-mono bg-white/20 px-2 py-0.5 rounded">ML</span>
                </div>
                <div class="divide-y divide-slate-100">
                    ${washers.map(w => `
                        <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition-colors">
                            <div class="flex items-center space-x-3">
                                <span style="background-color: ${w.bg};" class="w-8 h-8 rounded ${w.text} font-black text-xs flex items-center justify-center shrink-0">
                                    ${w.icon}
                                </span>
                                <div>
                                    <span class="font-bold text-xs text-slate-800">${w.code}</span>
                                    <span class="text-xs text-slate-500 ml-1">• ${w.name}</span>
                                    <p class="text-[11px] text-slate-400">${w.loc} • Capacité: ${w.cap}</p>
                                </div>
                            </div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded ${w.status === 'available' ? 'bg-emerald-100 text-emerald-800' : (w.status === 'reserved' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800')}">
                                ${w.status === 'available' ? 'Disponible' : (w.status === 'reserved' ? 'Réservé' : 'En cours')}
                            </span>
                        </div>
                    `).join('')}
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-[#00897b] text-white px-4 py-3 flex items-center justify-between">
                    <span class="font-bold text-sm">Sèche-linge (6 unités)</span>
                    <span class="text-xs font-mono bg-white/20 px-2 py-0.5 rounded">SL</span>
                </div>
                <div class="divide-y divide-slate-100">
                    ${dryers.map(d => `
                        <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition-colors">
                            <div class="flex items-center space-x-3">
                                <span style="background-color: ${d.bg};" class="w-8 h-8 rounded ${d.text} font-black text-xs flex items-center justify-center shrink-0">
                                    ${d.icon}
                                </span>
                                <div>
                                    <span class="font-bold text-xs text-slate-800">${d.code}</span>
                                    <span class="text-xs text-slate-500 ml-1">• ${d.name}</span>
                                    <p class="text-[11px] text-slate-400">${d.loc} • Capacité: ${d.cap}</p>
                                </div>
                            </div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded ${d.status === 'available' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'}">
                                ${d.status === 'available' ? 'Disponible' : 'En cours'}
                            </span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    </div>`;
}

// 5. Gestion des utilisateurs (ADMIN ONLY)
function renderUsersPage() {
    if (!state.isAdmin) {
        return `<div class="p-8 text-center text-rose-600 font-bold bg-white rounded border border-rose-200">Accès interdit : Cette page est réservée aux administrateurs.</div>`;
    }

    return `
    <div class="space-y-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Gestion des Utilisateurs</h1>
                <p class="text-xs text-slate-500">Supervision des comptes étudiants et suivi des quotas de réservation par semaine</p>
            </div>
            <button class="px-4 py-2 bg-[#00897b] text-white rounded text-xs font-bold shadow-xs">+ Ajouter utilisateur</button>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 shadow-xs overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#00897b] text-white font-bold">
                    <tr>
                        <th class="p-3.5">Nom</th>
                        <th class="p-3.5">Email</th>
                        <th class="p-3.5">Identifiant / Chambre</th>
                        <th class="p-3.5">Rôle</th>
                        <th class="p-3.5">Quota Hebdomadaire</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="p-3.5 font-bold text-slate-800">R. Omari</td>
                        <td class="p-3.5 text-slate-600">r.omari@fecc.ma</td>
                        <td class="p-3.5 text-slate-500 font-mono">ADM-001 (Direction Campus)</td>
                        <td class="p-3.5"><span class="px-2.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px]">Administrateur</span></td>
                        <td class="p-3.5 font-bold text-[#00897b]">Illimité</td>
                        <td class="p-3.5 text-right"><button class="text-slate-400 hover:text-slate-600 font-semibold">Modifier</button></td>
                    </tr>
                    <tr>
                        <td class="p-3.5 font-bold text-slate-800">Alex Rivera</td>
                        <td class="p-3.5 text-slate-600">alex.rivera@fecc.ma</td>
                        <td class="p-3.5 text-slate-500 font-mono">STU-98241 (Bât. Omar, Ch. 214)</td>
                        <td class="p-3.5"><span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">Étudiant</span></td>
                        <td class="p-3.5 font-bold text-emerald-600">${state.user.weeklyUsed}h / ${state.weeklyLimit}h utilisées (${state.weeklyLimit - state.user.weeklyUsed}h restantes)</td>
                        <td class="p-3.5 text-right space-x-2"><a href="/reset-quota" class="text-[#00897b] hover:underline font-semibold">Réinitialiser quota (8h)</a></td>
                    </tr>
                    <tr>
                        <td class="p-3.5 font-bold text-slate-800">Sara Bennani</td>
                        <td class="p-3.5 text-slate-600">sara.bennani@fecc.ma</td>
                        <td class="p-3.5 text-slate-500 font-mono">STU-88219 (Bât. Petit, Ch. 108)</td>
                        <td class="p-3.5"><span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">Étudiant</span></td>
                        <td class="p-3.5 font-bold text-amber-600">3h / 8h utilisées (5h restantes)</td>
                        <td class="p-3.5 text-right space-x-2"><button class="text-[#00897b] hover:underline font-semibold">Réinitialiser quota (8h)</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>`;
}

// 6. Réclamations Page
function renderComplaintsPage() {
    return `
    <div class="space-y-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Réclamations & Signalements</h1>
                <p class="text-xs text-slate-500">Suivi des incidents et maintenance machines</p>
            </div>
            <button class="px-4 py-2 bg-[#00897b] text-white rounded text-xs font-bold shadow-xs">+ Nouveau signalement</button>
        </div>

        <div class="space-y-3">
            <div class="bg-white p-4 rounded-lg shadow-sm border border-slate-200 flex items-start justify-between">
                <div class="flex items-start space-x-3">
                    <span class="p-2 rounded bg-amber-100 text-amber-800 font-bold text-xs shrink-0">ML3-OM</span>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800">Problème d'évacuation d'eau en fin de cycle</h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Signalé par: Sara Bennani (Bât. Petit) • Il y a 2 heures</p>
                        <p class="text-xs text-slate-600 mt-2 bg-slate-50 p-2.5 rounded border border-slate-100">
                            "La machine s'est arrêtée avec le voyant filtre allumé. L'eau ne s'est pas complètement vidée."
                        </p>
                    </div>
                </div>
                <span class="px-2.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-bold">En cours</span>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border border-slate-200 flex items-start justify-between">
                <div class="flex items-start space-x-3">
                    <span class="p-2 rounded bg-emerald-100 text-emerald-800 font-bold text-xs shrink-0">SL2-PE</span>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800">Nettoyage du filtre à peluches requis</h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Signalé par: Alex Rivera • Hier à 18:30</p>
                        <p class="text-xs text-slate-600 mt-2 bg-slate-50 p-2.5 rounded border border-slate-100">
                            "Filtre nettoyé par le technicien campus."
                        </p>
                    </div>
                </div>
                <span class="px-2.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold">Résolu</span>
            </div>
        </div>
    </div>`;
}

// 7. Paramètres Page (ADMIN ONLY)
function renderSettingsPage() {
    if (!state.isAdmin) {
        return `<div class="p-8 text-center text-rose-600 font-bold bg-white rounded border border-rose-200">Accès interdit : Cette page est réservée aux administrateurs.</div>`;
    }

    return `
    <div class="space-y-6 max-w-4xl mx-auto">
        <h1 class="text-xl font-bold text-slate-800">Paramètres du Système Buanderie</h1>
        <div class="bg-white rounded-lg border border-slate-200 p-6 space-y-6 text-xs">
            <div>
                <h2 class="font-bold text-sm text-slate-800 border-b border-slate-200 pb-2 mb-4">Politique des Quotas Hebdomadaires</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Quota maximal d'heures par semaine</label>
                        <div class="flex items-center space-x-2"><input type="number" value="${state.weeklyLimit}" class="w-24 px-3 py-2 border border-slate-300 rounded"><span class="text-slate-500">heures / étudiant / semaine (1h = 1 crédit)</span></div>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Jour de réinitialisation automatique</label>
                        <select class="w-48 px-3 py-2 border border-slate-300 rounded bg-slate-50"><option selected>Lundi à 00h00</option></select>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="font-bold text-sm text-slate-800 border-b border-slate-200 pb-2 mb-4">Horaires d'Ouverture & Règles</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Heure d'ouverture</label>
                        <input type="time" value="06:00" class="w-32 px-3 py-2 border border-slate-300 rounded">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Heure de fermeture</label>
                        <input type="time" value="23:30" class="w-32 px-3 py-2 border border-slate-300 rounded">
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end">
                <button class="px-5 py-2 rounded bg-[#00897b] hover:bg-[#00796b] text-white font-bold transition-all shadow-xs">Enregistrer</button>
            </div>
        </div>
    </div>`;
}

// Login Page (Image 1)
function renderLoginPage() {
    return fs.readFileSync(path.join(__dirname, 'resources/views/auth/login.blade.php'), 'utf8')
        .replace("@vite(['resources/css/app.css', 'resources/js/app.js'])", `<link rel="stylesheet" href="/build/${getAssets().cssFile}"><script defer src="/build/${getAssets().jsFile}"></script>`)
        .replace("{{ route('login') }}", "/login")
        .replace("{{ route('register') }}", "/login")
        .replace("{{ old('email', 'admin@fecc.ma') }}", "admin@fecc.ma")
        .replace("@csrf", "");
}

const server = http.createServer((req, res) => {
    const urlObj = new URL(req.url, `http://${req.headers.host}`);
    const pathname = urlObj.pathname;

    // Static compiled assets
    if (pathname.startsWith('/build/')) {
        const filePath = path.join(__dirname, 'public', pathname);
        if (fs.existsSync(filePath)) {
            const ext = path.extname(filePath);
            const contentType = ext === '.css' ? 'text/css' : ext === '.js' ? 'application/javascript' : 'application/octet-stream';
            res.writeHead(200, { 'Content-Type': contentType });
            return res.end(fs.readFileSync(filePath));
        }
    }

    // Role Toggle
    if (pathname === '/toggle-role') {
        state.isAdmin = !state.isAdmin;
        res.writeHead(302, { 'Location': '/calendrier' });
        return res.end();
    }

    // Reset Quota action for demo
    if (pathname === '/reset-quota') {
        state.user.weeklyUsed = 0;
        res.writeHead(302, { 'Location': '/utilisateurs' });
        return res.end();
    }

    // Handle Login submission
    if (pathname === '/login') {
        if (req.method === 'POST') {
            state.isAuthenticated = true;
            res.writeHead(302, { 'Location': '/calendrier' });
            return res.end();
        }
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(renderLoginPage());
    }

    // Logout
    if (pathname === '/logout') {
        state.isAuthenticated = false;
        res.writeHead(302, { 'Location': '/login' });
        return res.end();
    }

    // MANDATORY REQUIREMENT: IF NOT AUTHENTICATED, FIRST THING SHOWN IS LOGIN!
    if (!state.isAuthenticated) {
        res.writeHead(302, { 'Location': '/login' });
        return res.end();
    }

    // HANDLE MULTI-SLOT RESERVATION ACTION WITH 8H WEEKLY QUOTA SURVEILLANCE
    if (pathname === '/reserver' && req.method === 'POST') {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => {
            const params = new URLSearchParams(body);
            const machineCode = params.get('machine') || 'ML1-OM';
            const bookingDate = params.get('date') || '2026-09-30';
            
            // Support multiple hours selected (hours or hours[])
            let selectedHours = params.getAll('hours');
            if (selectedHours.length === 0) {
                selectedHours = params.getAll('hours[]');
            }
            if (selectedHours.length === 0 && params.get('hour')) {
                selectedHours = [params.get('hour')];
            }

            if (selectedHours.length === 0) {
                const msg = encodeURIComponent("Erreur : Aucun créneau sélectionné.");
                res.writeHead(302, { 'Location': `/reserver?machine=${machineCode}&flash=${msg}` });
                return res.end();
            }

            const machine = state.machines.find(m => m.code === machineCode) || state.machines[0];
            const hoursCount = selectedHours.length;

            // Check quota for non-admin
            if (!state.isAdmin) {
                const remaining = Math.max(0, state.weeklyLimit - state.user.weeklyUsed);
                if (hoursCount > remaining) {
                    const msg = encodeURIComponent(`Quota insuffisant : Vous avez sélectionné ${hoursCount}h mais il ne vous reste que ${remaining}h sur vos 8h cette semaine.`);
                    res.writeHead(302, { 'Location': `/reserver?machine=${machineCode}&flash=${msg}` });
                    return res.end();
                }
            }

            // Create reservation for each selected 1-hour slot on the same machine
            for (const slotHour of selectedHours) {
                const hourPrefix = slotHour.split(':')[0].trim() + ' h';
                state.reservations.push({
                    date: bookingDate,
                    hour: hourPrefix,
                    time: slotHour,
                    code: machine.code,
                    bg: machine.bg,
                    textColor: machine.text,
                    user: state.isAdmin ? 'R. Omari' : 'Alex Rivera',
                    durationHours: 1
                });
            }

            if (!state.isAdmin) {
                state.user.weeklyUsed = Math.min(state.weeklyLimit, state.user.weeklyUsed + hoursCount);
            }

            const remainingAfter = Math.max(0, state.weeklyLimit - state.user.weeklyUsed);
            const quotaMsg = state.isAdmin 
                ? '(Régime Administrateur - Quota Illimité)' 
                : `(Quota restant : ${remainingAfter}h / ${state.weeklyLimit}h cette semaine)`;
            const slotSummary = selectedHours.join(', ');
            const msg = encodeURIComponent(`Réservation validée pour la machine ${machine.code} (${hoursCount} heure${hoursCount > 1 ? 's' : ''} : ${slotSummary}) le ${bookingDate} ! Décompte : ${hoursCount} heure${hoursCount > 1 ? 's' : ''}. ${quotaMsg}`);

            res.writeHead(302, { 'Location': `/calendrier?flash=${msg}` });
            return res.end();
        });
        return;
    }

    // Dedicated reservation page
    if (pathname === '/reserver' && req.method === 'GET') {
        const preselectedMachine = urlObj.searchParams.get('machine') || 'ML1-OM';
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(renderLayout('Réserver une machine', renderDedicatedReservationPage(preselectedMachine), '/reserver'));
    }

    // Authenticated Routes:
    if (pathname === '/' || pathname === '/calendrier' || pathname === '/admin/reservation/calendrier') {
        const flash = urlObj.searchParams.get('flash') || '';
        const date = urlObj.searchParams.get('date') || '2026-09-30';
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(renderLayout('Calendrier des réservations', renderCalendarPage(date), '/calendrier', flash));
    }

    if (pathname === '/dashboard') {
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(renderLayout('Tableau de bord', renderDashboardPage(), '/dashboard'));
    }

    if (pathname === '/reservations') {
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(renderLayout('Réservations', renderReservationsPage(), '/reservations'));
    }

    if (pathname === '/machines') {
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(renderLayout('Machines', renderMachinesPage(), '/machines'));
    }

    if (pathname === '/utilisateurs') {
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(renderLayout('Gestion des utilisateurs', renderUsersPage(), '/utilisateurs'));
    }

    if (pathname === '/reclamations') {
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(renderLayout('Réclamations', renderComplaintsPage(), '/reclamations'));
    }

    if (pathname === '/parametres') {
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(renderLayout('Paramètres', renderSettingsPage(), '/parametres'));
    }

    // Fallback redirect to /calendrier
    res.writeHead(302, { 'Location': '/calendrier' });
    res.end();
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`ECC Laundry Centrale Casablanca Server is running at http://localhost:${PORT}`);
});
