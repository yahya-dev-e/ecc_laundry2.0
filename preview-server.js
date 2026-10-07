import http from 'http';
import fs from 'fs';
import path from 'path';
import zlib from 'zlib';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const PORT = process.env.PORT ? parseInt(process.env.PORT, 10) : 3000;

// Asset manifest cache
let cachedAssets = null;
function getAssets() {
    if (cachedAssets) return cachedAssets;
    let cssFile = 'assets/app-BaKgmCTs.css';
    let jsFile = 'assets/app-CEzoOuxG.js';
    try {
        const manifest = JSON.parse(fs.readFileSync(path.join(__dirname, 'public/build/manifest.json'), 'utf8'));
        if (manifest['resources/css/app.css']) cssFile = manifest['resources/css/app.css'].file;
        if (manifest['resources/js/app.js']) jsFile = manifest['resources/js/app.js'].file;
    } catch (e) {
        console.warn('Fallback manifest assets');
    }
    cachedAssets = { cssFile, jsFile };
    return cachedAssets;
}

// In-memory static file cache for high-speed delivery
const staticCache = new Map();

function sendCompressedResponse(req, res, statusCode, headers, content) {
    const buffer = Buffer.isBuffer(content) ? content : Buffer.from(content, 'utf8');
    const acceptEncoding = req.headers['accept-encoding'] || '';

    // Simple hash-based ETag for 304 Not Modified
    const etag = `"${buffer.length}-${buffer.subarray(0, 32).reduce((acc, byte) => acc + byte, 0)}"`;
    headers['ETag'] = etag;

    if (req.headers['if-none-match'] === etag) {
        res.writeHead(304, headers);
        return res.end();
    }

    if (acceptEncoding.includes('gzip')) {
        const compressed = zlib.gzipSync(buffer);
        headers['Content-Encoding'] = 'gzip';
        headers['Vary'] = 'Accept-Encoding';
        headers['Content-Length'] = compressed.length;
        res.writeHead(statusCode, headers);
        return res.end(compressed);
    }

    headers['Content-Length'] = buffer.length;
    res.writeHead(statusCode, headers);
    return res.end(buffer);
}

// 24 standard 1-hour slots for campus laundry
const ALL_HOURLY_SLOTS = [
    '00:00 - 01:00', '01:00 - 02:00', '02:00 - 03:00', '03:00 - 04:00', '04:00 - 05:00', '05:00 - 06:00',
    '06:00 - 07:00', '07:00 - 08:00', '08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '11:00 - 12:00',
    '12:00 - 13:00', '13:00 - 14:00', '14:00 - 15:00', '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00',
    '18:00 - 19:00', '19:00 - 20:00', '20:00 - 21:00', '21:00 - 22:00', '22:00 - 23:00', '23:00 - 00:00'
];

function parseTimeToMinutes(t) {
    if (!t) return 0;
    const parts = String(t).trim().replace('h', ':00').split(':');
    const h = parseInt(parts[0], 10) || 0;
    const m = parts[1] ? (parseInt(parts[1], 10) || 0) : 0;
    return h * 60 + m;
}

function parseReservationTimes(timeStr, defaultDurationHours = 1) {
    if (!timeStr) {
        return { startMinutes: 0, endMinutes: 60, durationMinutes: 60, formattedTime: '00:00 - 01:00' };
    }
    const clean = String(timeStr).trim();
    if (clean.includes('-')) {
        const parts = clean.split('-').map(s => s.trim());
        const startM = parseTimeToMinutes(parts[0]);
        let endM = parseTimeToMinutes(parts[1]);
        if (isNaN(endM) || (endM === 0 && startM > 0)) {
            endM = (parts[1] && (parts[1].startsWith('00') || parts[1].startsWith('24'))) ? 24 * 60 : startM + 60;
        }
        if (endM <= startM) endM = startM + 60;
        const dur = endM - startM;
        const sH = String(Math.floor(startM / 60)).padStart(2, '0') + ':' + String(startM % 60).padStart(2, '0');
        const eH = String(Math.floor(endM / 60)).padStart(2, '0') + ':' + String(endM % 60).padStart(2, '0');
        return { startMinutes: startM, endMinutes: endM, durationMinutes: dur, formattedTime: `${sH} - ${eH}` };
    } else {
        const startM = parseTimeToMinutes(clean);
        const endM = Math.min(24 * 60, startM + (defaultDurationHours * 60));
        const sH = String(Math.floor(startM / 60)).padStart(2, '0') + ':' + String(startM % 60).padStart(2, '0');
        const eH = String(Math.floor(endM / 60)).padStart(2, '0') + ':' + String(endM % 60).padStart(2, '0');
        return { startMinutes: startM, endMinutes: endM, durationMinutes: endM - startM, formattedTime: `${sH} - ${eH}` };
    }
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

// Persistent Database for Users
const DB_USERS_PATH = path.join(__dirname, 'database/users.json');

function loadUsersFromDb() {
    try {
        if (fs.existsSync(DB_USERS_PATH)) {
            const data = fs.readFileSync(DB_USERS_PATH, 'utf8');
            return JSON.parse(data);
        }
    } catch (e) {
        console.error('Error reading database/users.json:', e);
    }
    return [
        { id: 1, name: 'R. Omari', email: 'r.omari@fecc.ma', role: 'admin', student_id: 'ADM-001', room_number: 'Direction Campus', weeklyUsed: 0, weeklyLimit: 100, credits: 100 },
        { id: 2, name: 'Alex Rivera', email: 'alex.rivera@fecc.ma', role: 'student', student_id: 'STU-98241', room_number: 'Bât. Omar, Ch. 214', weeklyUsed: 1, weeklyLimit: 8, credits: 7 },
        { id: 3, name: 'Sara Bennani', email: 'sara.bennani@fecc.ma', role: 'student', student_id: 'STU-88219', room_number: 'Bât. Petit, Ch. 108', weeklyUsed: 2, weeklyLimit: 8, credits: 6 },
        { id: 4, name: 'Mehdi Tazi', email: 'mehdi.tazi@fecc.ma', role: 'student', student_id: 'STU-77312', room_number: 'Bât. Omar, Ch. 105', weeklyUsed: 3, weeklyLimit: 8, credits: 5 },
        { id: 5, name: 'Khadija Mansour', email: 'khadija.mansour@fecc.ma', role: 'student', student_id: 'STU-66104', room_number: 'Bât. Petit, Ch. 312', weeklyUsed: 0, weeklyLimit: 8, credits: 8 },
        { id: 6, name: 'Youssef Alami', email: 'youssef.alami@fecc.ma', role: 'student', student_id: 'STU-55209', room_number: 'Bât. Omar, Ch. 402', weeklyUsed: 1, weeklyLimit: 8, credits: 7 }
    ];
}

function saveUsersToDb(users) {
    try {
        fs.writeFileSync(DB_USERS_PATH, JSON.stringify(users, null, 2), 'utf8');
    } catch (e) {
        console.error('Error writing to database/users.json:', e);
    }
}

// In-memory state with 8-hour weekly reservation quota (1h = 1 credit)
const state = {
    isAuthenticated: true, // Auto-authenticated for instant development preview
    isAdmin: true, // Role switcher for testing
    weeklyLimit: 100, // 100 credits for admin, 8 for normal user
    users: loadUsersFromDb(), // Dynamic Database-driven users list
    user: {
        name: 'El Omari',
        email: 'r.omari@fecc.ma',
        weeklyUsed: 2, // 2 credits used
    },
    machines: [
        { id: 1, code: 'ML1-OM', name: 'ML1-OM', type: 'washer', bg: '#4338ca', text: 'text-white', status: 'available' },
        { id: 2, code: 'ML2-OM', name: 'ML2-OM', type: 'washer', bg: '#0d9488', text: 'text-white', status: 'in_use' },
        { id: 3, code: 'ML1-PE', name: 'ML1-PE', type: 'washer', bg: '#2563eb', text: 'text-white', status: 'available' },
        { id: 4, code: 'ML2-PE', name: 'ML2-PE', type: 'washer', bg: '#d97706', text: 'text-white', status: 'available' },
        { id: 5, code: 'ML3-PE', name: 'ML3-PE', type: 'washer', bg: '#db2777', text: 'text-white', status: 'available' },
        { id: 6, code: 'ML4-PE', name: 'ML4-PE', type: 'washer', bg: '#ea580c', text: 'text-white', status: 'available' },
        { id: 7, code: 'ML3-OM', name: 'ML3-OM', type: 'washer', bg: '#059669', text: 'text-white', status: 'available' },
        
        { id: 8, code: 'SL1-OM', name: 'SL1-OM', type: 'dryer', bg: '#b45309', text: 'text-white', status: 'available' },
        { id: 9, code: 'SL2-OM', name: 'SL2-OM', type: 'dryer', bg: '#16a34a', text: 'text-white', status: 'available' },
        { id: 10, code: 'SL1-PE', name: 'SL1-PE', type: 'dryer', bg: '#475569', text: 'text-white', status: 'in_use' },
        { id: 11, code: 'SL2-PE', name: 'SL2-PE', type: 'dryer', bg: '#65a30d', text: 'text-white', status: 'available' },
        { id: 12, code: 'SL3-PE', name: 'SL3-PE', type: 'dryer', bg: '#9333ea', text: 'text-white', status: 'available' },
        { id: 13, code: 'SL3-OM', name: 'SL3-OM', type: 'dryer', bg: '#52525b', text: 'text-white', status: 'available' },
    ],
    reservations: [
        // ML2-OM on Mercredi 30 Septembre extended to 2am (00:00 - 02:00, 2h)
        { date: '2026-09-30', hour: '00 h', time: '00:00 - 02:00', code: 'ML2-OM', bg: '#0d9488', textColor: 'text-white', user: 'Alex Rivera', durationHours: 2 },
        { date: '2026-09-30', hour: '06 h', time: '06:00 - 07:00', code: 'SL1-PE', bg: '#475569', textColor: 'text-white', user: 'Youssef Alami', durationHours: 1 },
        { 
            date: '2026-09-30',
            hour: '07 h', 
            multi: [
                { time: '07:00 - 09:00', code: 'ML2-PE', bg: '#d97706', textColor: 'text-white', user: 'Sara Bennani' },
                { time: '07:00 - 09:00', code: 'ML3-PE', bg: '#db2777', textColor: 'text-white', user: 'Mehdi Tazi' },
                { time: '07:00 - 09:00', code: 'ML2-OM', bg: '#0d9488', textColor: 'text-white', user: 'Sara Bennani' }
            ]
        },
        { date: '2026-09-30', hour: '09 h', time: '09:00 - 11:00', code: 'ML1-OM', bg: '#4338ca', textColor: 'text-white', user: 'R. Omari', durationHours: 2 },
        { date: '2026-09-30', hour: '11 h', time: '11:00 - 12:00', code: 'SL1-OM', bg: '#b45309', textColor: 'text-white', user: 'Amine Chraibi', durationHours: 1 },
        { date: '2026-09-30', hour: '12 h', time: '12:00 - 14:00', code: 'ML4-PE', bg: '#ea580c', textColor: 'text-white', user: 'Leila Benjelloun', durationHours: 2 },
        { date: '2026-09-30', hour: '14 h', time: '14:00 - 15:00', code: 'ML1-PE', bg: '#2563eb', textColor: 'text-white', user: 'Mehdi Tazi', durationHours: 1 },
        { date: '2026-09-30', hour: '15 h', time: '15:00 - 17:00', code: 'SL3-PE', bg: '#9333ea', textColor: 'text-white', user: 'Khadija Mansour', durationHours: 2 },
        { date: '2026-09-30', hour: '17 h', time: '17:00 - 18:00', code: 'ML3-OM', bg: '#059669', textColor: 'text-white', user: 'Omar Fassi', durationHours: 1 },
        { date: '2026-09-30', hour: '18 h', time: '18:00 - 20:00', code: 'ML2-OM', bg: '#0d9488', textColor: 'text-white', user: 'Sara Bennani', durationHours: 2 },
        { date: '2026-09-30', hour: '20 h', time: '20:00 - 21:00', code: 'SL2-PE', bg: '#65a30d', textColor: 'text-white', user: 'Alex Rivera', durationHours: 1 },
        { date: '2026-09-30', hour: '21 h', time: '21:00 - 23:00', code: 'ML1-PE', bg: '#2563eb', textColor: 'text-white', user: 'Youssef Alami', durationHours: 2 },
        // Pre-seeded reservations for 2026-10-01 (Jeudi, 1 Octobre 2026)
        { date: '2026-10-01', hour: '00 h', time: '00:00 - 01:00', code: 'SL3-PE', bg: '#9333ea', textColor: 'text-white', user: 'Coulibaly', durationHours: 1 },
        { date: '2026-10-01', hour: '00 h', time: '00:00 - 02:00', code: 'ML1-PE', bg: '#2563eb', textColor: 'text-white', user: 'ghadi', durationHours: 2 },
        { date: '2026-10-01', hour: '02 h', time: '02:00 - 04:00', code: 'SL2-PE', bg: '#65a30d', textColor: 'text-white', user: 'ghadi', durationHours: 2 }
    ]
};

function renderLayout(title, content, currentPath = '/', flash = '') {
    const { cssFile, jsFile } = getAssets();
    state.weeklyLimit = state.isAdmin ? 100 : 8;
    const remaining = Math.max(0, state.weeklyLimit - state.user.weeklyUsed);

    return `<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${title} - Centrale Casablanca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@500;600;700&display=swap" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@500;600;700&display=swap">
    </noscript>
    <link rel="stylesheet" href="/build/${cssFile}">
    <script defer src="/build/${jsFile}"></script>
</head>
<body x-data="{ mobileMenuOpen: false }" class="min-h-screen bg-[#f4f7f6] text-slate-800 flex font-sans antialiased">

    <!-- Desktop Sidebar (visible on screens >= lg) -->
    <aside class="hidden lg:flex w-64 admin-sidebar min-h-screen flex-col justify-between shrink-0 shadow-lg select-none">
        <div>
            <!-- Header -->
            <div class="px-5 py-5 flex items-center space-x-3 border-b border-[#00695c]">
                <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center text-[#00796b] shadow font-black text-sm">
                    ${state.isAdmin 
                        ? '<svg class="w-5 h-5 text-[#00796b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>' 
                        : '<svg class="w-5 h-5 text-[#00796b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>'}
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

                <a href="/calendrier" class="sidebar-link ${currentPath === '/calendrier' ? 'active' : ''}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Calendrier des réservations</span>
                </a>

                <a href="/reserver" class="sidebar-link ${currentPath === '/reserver' ? 'active' : ''}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Réserver une machine</span>
                </a>

                ${state.isAdmin ? `
                    <a href="/utilisateurs" class="sidebar-link ${currentPath === '/utilisateurs' ? 'active' : ''}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>Gestion des utilisateurs</span>
                    </a>
                ` : ''}
            </nav>
        </div>

        <div class="p-4 border-t border-[#00695c] flex items-center justify-between">
            <a href="/logout" title="Se déconnecter" class="flex items-center space-x-2 text-xs text-emerald-200/80 hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span>Déconnexion</span>
            </a>
            <span class="text-[10px] text-emerald-200/50">v2.0 FECC</span>
        </div>
    </aside>

    <!-- Mobile Slide-over Drawer (visible on screens < lg when toggled) -->
    <div x-show="mobileMenuOpen" class="fixed inset-0 z-50 lg:hidden" style="display: none;" x-cloak>
        <!-- Backdrop -->
        <div @click="mobileMenuOpen = false" 
             x-show="mobileMenuOpen" 
             x-transition:enter="transition-opacity ease-out duration-200" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="transition-opacity ease-in duration-150" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"></div>

        <!-- Mobile Navigation Panel -->
        <aside x-show="mobileMenuOpen" 
               x-transition:enter="transition ease-out duration-200 transform" 
               x-transition:enter-start="-translate-x-full" 
               x-transition:enter-end="translate-x-0" 
               x-transition:leave="transition ease-in duration-150 transform" 
               x-transition:leave-start="translate-x-0" 
               x-transition:leave-end="-translate-x-full" 
               class="relative w-72 max-w-[85vw] admin-sidebar h-full min-h-screen flex flex-col justify-between shadow-2xl z-50">
            <div>
                <!-- Header with Close Button -->
                <div class="px-5 py-4 flex items-center justify-between border-b border-[#00695c]">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center text-[#00796b] shadow font-black text-sm">
                            ${state.isAdmin 
                                ? '<svg class="w-5 h-5 text-[#00796b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>' 
                                : '<svg class="w-5 h-5 text-[#00796b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>'}
                        </div>
                        <div>
                            <span class="text-xs font-black tracking-wider uppercase text-white block">
                                ${state.isAdmin ? 'ADMIN PANNEAU' : 'ESPACE ÉTUDIANT'}
                            </span>
                            <span class="text-[10px] text-emerald-200/70 font-semibold block">Centrale Casablanca</span>
                        </div>
                    </div>
                    <button type="button" @click="mobileMenuOpen = false" class="p-1.5 text-white/80 hover:text-white rounded-lg hover:bg-[#00695c]" aria-label="Fermer le menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Navigation Links -->
                <nav class="mt-3 space-y-0.5 px-2">
                    <a href="/dashboard" @click="mobileMenuOpen = false" class="sidebar-link rounded-lg ${currentPath === '/dashboard' ? 'active' : ''}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span>Tableau de bord</span>
                    </a>

                    <a href="/calendrier" @click="mobileMenuOpen = false" class="sidebar-link rounded-lg ${currentPath === '/calendrier' ? 'active' : ''}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Calendrier des réservations</span>
                    </a>

                    <a href="/reserver" @click="mobileMenuOpen = false" class="sidebar-link rounded-lg ${currentPath === '/reserver' ? 'active' : ''}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Réserver une machine</span>
                    </a>

                    ${state.isAdmin ? `
                        <a href="/utilisateurs" @click="mobileMenuOpen = false" class="sidebar-link rounded-lg ${currentPath === '/utilisateurs' ? 'active' : ''}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span>Gestion des utilisateurs</span>
                        </a>
                    ` : ''}
                </nav>
            </div>

            <!-- Role switcher and Logout -->
            <div class="p-4 border-t border-[#00695c] space-y-3">
                <a href="/toggle-role" class="block text-center py-2 px-3 rounded-lg text-xs font-bold ${state.isAdmin ? 'bg-amber-500/20 text-amber-200 border border-amber-400/30' : 'bg-blue-500/20 text-blue-200 border border-blue-400/30'}">
                    ${state.isAdmin ? 'Mode: ADMIN (Basculer)' : 'Mode: ÉTUDIANT (Basculer)'}
                </a>
                <div class="flex items-center justify-between text-xs pt-1">
                    <a href="/logout" class="flex items-center space-x-1.5 text-emerald-200/80 hover:text-white transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Déconnexion</span>
                    </a>
                    <span class="text-[10px] text-emerald-200/50">v2.0 FECC</span>
                </div>
            </div>
        </aside>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto w-full">
        
        <!-- Top Navbar -->
        <header class="h-14 bg-white border-b border-slate-200 px-3 sm:px-6 flex items-center justify-between shrink-0 sticky top-0 z-30 shadow-xs">
            <div class="flex items-center space-x-2 sm:space-x-4">
                <!-- Mobile Hamburger Toggle Button -->
                <button type="button" 
                        @click="mobileMenuOpen = true" 
                        class="lg:hidden p-1.5 -ml-1 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#00796b]" 
                        aria-label="Ouvrir le menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <span class="text-xs text-slate-400 font-medium hidden xl:inline">laundry.fecc.ma${currentPath}</span>
                <span class="text-slate-300 text-xs hidden xl:inline">•</span>

                <!-- Server Time Indicator (Adaptive on Mobile) -->
                <div id="server-time-indicator"
                     x-data="serverClock('${new Date().toISOString()}', 'UTC')" 
                     class="flex items-center space-x-1.5 sm:space-x-2 px-2.5 sm:px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-xs font-medium text-slate-700 shadow-2xs select-none shrink-0"
                     title="Heure actuelle du serveur (UTC)">
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-[11px] font-semibold text-slate-500 whitespace-nowrap hidden sm:inline">Serveur :</span>
                    <span class="font-mono font-bold text-slate-800 tracking-tight whitespace-nowrap" x-text="timeFormatted">
                        ${new Date().toISOString().substring(11, 19)}
                    </span>
                    <span class="text-[9px] font-bold text-slate-500 bg-white px-1.5 py-0.5 rounded border border-slate-200 hidden md:inline">
                        UTC
                    </span>
                </div>
                
                <a href="/toggle-role" class="hidden md:inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border transition-all ${state.isAdmin ? 'bg-amber-50 text-amber-800 border-amber-300' : 'bg-blue-50 text-blue-800 border-blue-300'}" title="Cliquez pour basculer de rôle">
                    ${state.isAdmin ? 'Admin (100h)' : 'Étudiant (8h)'}
                </a>
            </div>

            <div class="flex items-center space-x-2 sm:space-x-4">
                <!-- Quota Indicator (Adaptive on Mobile) -->
                <div class="flex items-center space-x-1.5 sm:space-x-2 px-2.5 sm:px-3 py-1 rounded-full ${state.isAdmin ? 'bg-amber-50 text-amber-900 border border-amber-200' : (remaining > 0 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200')} text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full shrink-0 ${state.isAdmin ? 'bg-amber-500' : (remaining > 0 ? 'bg-emerald-500' : 'bg-rose-500')}"></span>
                    <span class="hidden sm:inline">
                        ${state.isAdmin ? `Quota Admin : ${state.user.weeklyUsed} / 100 crédits (${remaining} restants)` : `Quota : ${state.user.weeklyUsed} / 8 crédits (${remaining} restants)`}
                    </span>
                    <span class="inline sm:hidden font-mono font-bold">
                        ${state.isAdmin ? `${remaining}/100 cr.` : `${remaining}/8 cr.`}
                    </span>
                </div>

                <div class="hidden xs:flex items-center space-x-1 cursor-pointer">
                    <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">FR</span>
                </div>

                <div class="flex items-center space-x-2">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-slate-200 border border-slate-300 flex items-center justify-center overflow-hidden shrink-0">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-slate-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 hidden md:inline truncate max-w-[100px]">${state.isAdmin ? 'El Omari' : 'Alex Rivera'}</span>
                </div>

                <!-- Disconnect Button in Header -->
                <a href="/logout" class="flex items-center space-x-1 px-2.5 py-1 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 text-xs font-medium transition-all shadow-2xs" title="Se déconnecter">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span class="hidden sm:inline">Déconnexion</span>
                </a>
            </div>
        </header>

        <!-- Flash Toast Notification if exists -->
        ${flash ? `
        <div class="px-3 sm:px-6 pt-3 sm:pt-4">
            <div class="p-3 sm:p-3.5 rounded bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-xs flex items-center justify-between shadow-xs">
                <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-emerald-600 inline-block shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    <span>${flash}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-700 font-bold">&times;</button>
            </div>
        </div>` : ''}

        <main class="p-3 sm:p-6 md:p-8 flex-1 w-full max-w-full overflow-x-hidden">
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
    const currentUserName = state.isAdmin ? 'El Omari' : 'Alex Rivera';
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
        const parsed = parseReservationTimes(r.time, r.durationHours || 1);
        return {
            ...r,
            startMinutes: parsed.startMinutes,
            endMinutes: parsed.endMinutes,
            durationMinutes: parsed.durationMinutes,
            time: parsed.formattedTime,
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
        <div class="bg-white border-l-4 border-[#00897b] p-3.5 rounded shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center space-x-3">
                <div class="w-5 h-5 rounded-full bg-[#00897b]/10 text-[#00897b] flex items-center justify-center font-bold text-xs shrink-0">i</div>
                <span class="text-xs text-slate-700">Cliquez sur une machine pour voir ses créneaux et réserver directement.</span>
            </div>
            <div class="text-xs font-semibold text-[#00897b]">
                ${state.isAdmin ? `Quota Admin restant : ${remaining}h sur ${state.weeklyLimit}h cette semaine (1h = 1 crédit)` : `Quota restant : ${remaining}h sur ${state.weeklyLimit}h cette semaine (1h = 1 crédit)`}
            </div>
        </div>

        <div class="max-w-md mx-auto">
            <input type="text" placeholder="Rechercher une machine..." 
                   class="w-full px-4 py-2 bg-white border border-slate-300 rounded text-xs placeholder-slate-400 focus:outline-none focus:border-[#00897b] shadow-xs">
        </div>

        <!-- Machine Selection Matrix matching Image 2 -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden max-w-2xl mx-auto">
            <div class="grid grid-cols-2 bg-[#00897b] text-white text-xs font-bold text-center py-2.5">
                <div>Machines à laver (${washers.length})</div>
                <div>Sèche-linge (${dryers.length})</div>
            </div>

            <div class="grid grid-cols-2 divide-x divide-slate-200 p-3 sm:p-4">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 sm:gap-2 pr-2 sm:pr-3">
                    ${washers.map(m => `
                        <button type="button" 
                                onclick="pickMachine('${m.code}')" 
                                data-code="${m.code}"
                                style="background-color: ${m.bg};"
                                class="badge-machine badge-machine-btn ${m.text} py-1.5 px-2 sm:px-3 text-[11px] sm:text-xs justify-center text-center ${m.code === 'ML1-OM' ? 'ring-2 ring-slate-900 scale-105 shadow-md' : ''}">
                            <span>${m.code}</span>
                        </button>
                    `).join('')}
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-2 gap-1.5 sm:gap-2 pl-2 sm:pl-3">
                    ${dryers.map(m => `
                        <button type="button" 
                                onclick="pickMachine('${m.code}')" 
                                data-code="${m.code}"
                                style="background-color: ${m.bg};"
                                class="badge-machine badge-machine-btn ${m.text} py-1.5 px-2 sm:px-3 text-[11px] sm:text-xs justify-center text-center">
                            <span>${m.code}</span>
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
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base sm:text-xl font-bold text-slate-800 capitalize">${dayName}, ${dateFormatted}</h2>
                    <input type="date" value="${selectedDateStr}" 
                           onchange="window.location.href = '/calendrier?date=' + this.value"
                           class="px-2.5 py-1 text-xs border border-slate-300 rounded bg-white text-slate-700 hover:border-[#00897b] focus:outline-none focus:border-[#00897b] cursor-pointer shadow-xs font-medium"
                           title="Choisir une date quelconque">
                </div>
                <div class="inline-flex rounded shadow-xs text-xs self-start sm:self-auto">
                    <a href="/calendrier?date=${todayStr}" class="px-3.5 py-2 ${selectedDateStr === todayStr ? 'bg-[#00897b] text-white font-bold' : 'bg-[#546e7a] hover:bg-[#455a64] text-white font-medium'} rounded-l transition-colors flex items-center">Aujourd'hui</a>
                    <a href="/calendrier?date=${prevDateStr}" class="px-3 py-2 bg-[#37474f] hover:bg-[#263238] text-white font-medium transition-colors flex items-center space-x-1" title="Jour précédent (${prevDateStr})">
                        <span>&larr;</span>
                        <span>Précédent</span>
                    </a>
                    <a href="/calendrier?date=${nextDateStr}" class="px-3 py-2 bg-[#263238] hover:bg-black text-white font-medium rounded-r transition-colors flex items-center space-x-1" title="Jour suivant (${nextDateStr})">
                        <span>Suivant</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </div>

            <!-- 7-Day Quick Jump Strip -->
            <div class="grid grid-cols-7 gap-1 sm:gap-2 p-1.5 bg-white rounded-lg border border-slate-200 shadow-xs">
                ${weekDays.map(w => `
                    <a href="/calendrier?date=${w.date}" 
                       class="py-2.5 px-1 text-center rounded transition-all flex flex-col items-center justify-center min-h-[48px] ${w.isSelected ? 'bg-[#00897b] text-white font-bold shadow-xs scale-102' : 'hover:bg-slate-100 text-slate-700'}">
                        <span class="text-[10px] uppercase font-semibold ${w.isSelected ? 'text-emerald-100' : 'text-slate-400'}">${w.shortName}</span>
                        <span class="text-sm font-bold ${w.isSelected ? 'text-white' : (w.isToday ? 'text-[#00897b]' : 'text-slate-800')}">${w.dayNumber}</span>
                        ${w.isToday ? `<span class="w-1.5 h-1.5 rounded-full ${w.isSelected ? 'bg-white' : 'bg-[#00897b]'} mt-0.5"></span>` : '<span class="w-1.5 h-1.5 mt-0.5"></span>'}
                    </a>
                `).join('')}
            </div>
        </div>

        <!-- Mobile Horizontal Scroll Cue -->
        <div class="sm:hidden flex items-center justify-end space-x-1.5 text-[11px] text-slate-500 px-1 -mb-2 select-none">
            <svg class="w-3.5 h-3.5 text-[#00897b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            <span class="font-medium">Glissez vers la droite pour voir les machines</span>
        </div>

        <!-- Timetable / Calendar Timeline Grid with Continuous Blocks & Limitor Lines -->
        <div class="bg-white rounded-lg border border-slate-300 shadow-xs overflow-hidden">
            <div class="flex border-b border-slate-300 bg-[#f9f9e8] text-xs font-semibold text-slate-700">
                <div class="w-14 sm:w-20 p-2 sm:p-2.5 text-center border-r border-slate-300 text-[10px] sm:text-[11px] text-slate-500 font-semibold tracking-tight sticky left-0 z-30 bg-[#f9f9e8] shadow-xs">Horaires</div>
                <div class="flex-1 p-2.5 text-center font-bold text-slate-800 capitalize flex items-center justify-center space-x-2">
                    <span>${dayName} (${dateFormatted})</span>
                    ${calendarBlocks.length > 0 ? `<span class="text-[10px] font-normal text-slate-500 bg-white/80 px-2 py-0.5 rounded border border-slate-200">${calendarBlocks.length} réservation${calendarBlocks.length > 1 ? 's' : ''}</span>` : ''}
                </div>
            </div>

            <div class="relative overflow-x-auto">
                <div class="flex min-w-[620px] relative select-none">
                    
                    <!-- Left Axis: Hours of the Day directly ON the line as limitor indicators (sticky on mobile horizontal scroll) -->
                    <div class="w-14 sm:w-20 shrink-0 border-r border-slate-300 bg-slate-50/95 sticky left-0 z-30 shadow-xs select-none" style="height: ${24 * 52}px;">
                        ${Array.from({ length: 25 }, (_, h) => {
                            const top = h * 52;
                            const hourLabel = String(h === 24 ? 24 : h).padStart(2, '0') + ':00';
                            return `
                            <div class="absolute right-0 pr-1.5 sm:pr-3 flex items-center -translate-y-1/2 pointer-events-none" style="top: ${top}px;">
                                <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 font-mono tracking-tight">${hourLabel}</span>
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

                        <!-- Current System Time Indicator (Points to the local system hour) -->
                        <div id="system-time-pointer"
                             x-data="systemTimePointer('${selectedDateStr}', '${todayStr}')"
                             x-show="isVisible"
                             class="absolute left-0 right-0 z-30 pointer-events-none flex items-center transition-all duration-300"
                             :style="'top: ' + topPx + 'px;'"
                             style="top: ${((new Date().getHours() * 60 + new Date().getMinutes()) / 60) * 52}px;"
                             title="Heure actuelle du système">
                            <div class="w-2.5 h-2.5 rounded-full bg-rose-500 shadow -ml-1.5 shrink-0 ring-2 ring-white"></div>
                            <div class="flex-1 border-t-2 border-rose-500 shadow-xs"></div>
                            <span class="bg-rose-500 text-white font-mono text-[9px] font-bold px-1.5 py-0.5 rounded shadow -mr-1 flex items-center space-x-1"
                                  x-text="timeFormatted">
                                ${String(new Date().getHours()).padStart(2, '0')}:${String(new Date().getMinutes()).padStart(2, '0')}
                            </span>
                        </div>

                        <!-- Continuous Blocks -->
                        ${calendarBlocks.map(block => {
                            const isMultiHour = block.durationMinutes > 60;
                            const machineColor = block.bg || '#4338ca';
                            const isDarkText = block.textColor === 'text-slate-900';
                            const badgeBg = isDarkText ? 'bg-black/15 text-slate-900' : 'bg-black/25 text-white';
                            const subText = isDarkText ? 'text-slate-800' : 'text-white/90';
                            const mach = state.machines.find(m => m.code === block.code) || {
                                code: block.code,
                                type: block.code.startsWith('SL') ? 'dryer' : 'washer'
                            };
                            const machineType = mach.type === 'dryer' ? 'Sèche-linge' : 'Machine à laver';
                            const durationStr = block.durationFormatted || (block.durationMinutes ? `${block.durationMinutes / 60} h` : '1 h');
                            const foundUser = state.users.find(u => u.name && u.name.toLowerCase() === (block.user || '').toLowerCase()) ||
                                              state.users.find(u => u.email && u.email.toLowerCase() === (block.user || '').toLowerCase());
                            const userEmail = block.email || (foundUser ? foundUser.email : (block.user ? `${block.user.toLowerCase().replace(/[^a-z0-9]/g, '.')}@fecc.ma` : 'etudiant@fecc.ma'));
                            const resData = {
                                code: block.code,
                                type: machineType,
                                bg: machineColor,
                                textColor: block.textColor || 'text-white',
                                user: userEmail,
                                email: userEmail,
                                date: selectedDateStr,
                                dateFormatted: `${dayName}, ${dateFormatted}`,
                                time: block.time,
                                duration: durationStr
                            };
                            const resJson = encodeURIComponent(JSON.stringify(resData));

                            return `
                            <div style="background-color: ${machineColor}; top: ${block.top + 1}px; height: ${block.height - 2}px; left: calc(${block.leftPct}% + 4px); width: calc(${block.widthPct}% - 8px);"
                                 class="absolute rounded-md ${block.textColor || 'text-white'} overflow-hidden transition-all cursor-pointer border border-white/25 select-none shadow-sm hover:shadow-md hover:scale-[1.01] hover:brightness-105 z-20"
                                 onclick="openReservationModal('${resJson}')"
                                 title="${block.code} • ${block.time} - Cliquer pour voir les détails">
                                
                                <div class="h-full p-2 flex flex-col justify-center">
                                    <div class="flex items-center justify-between text-xs leading-tight">
                                        <div class="flex items-center space-x-1.5 truncate">
                                            <span class="font-mono font-bold text-[11px] ${badgeBg} px-1.5 py-0.5 rounded">${block.time}</span>
                                            <span class="opacity-60">•</span>
                                            <span class="font-bold text-[12px] truncate">${block.code}</span>
                                            ${isMultiHour ? `<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded ${badgeBg} uppercase tracking-wider">${block.durationFormatted}</span>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                        }).join('')}

                        ${calendarBlocks.length === 0 ? `
                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                <div class="bg-white/90 border border-slate-200 shadow-sm rounded-lg p-4 text-center max-w-sm">
                                    <svg class="w-8 h-8 mx-auto mb-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span class="text-xs font-bold text-slate-700 block">Aucune réservation pour cette journée</span>
                                    <span class="text-[11px] text-slate-500 block mt-1">Cliquez sur un créneau horaire ou sélectionnez une machine ci-dessus pour réserver.</span>
                                </div>
                            </div>
                        ` : ''}

                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL : DÉTAILS DE LA RÉSERVATION -->
        <div id="reservationDetailsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md overflow-hidden transform transition-all flex flex-col">
                <!-- Modal Header -->
                <div style="background: linear-gradient(135deg, #004d40 0%, #00796b 100%); color: white;" class="px-5 py-4 text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center space-x-3">
                        <span id="resModalBadge" class="px-2.5 py-1 rounded font-mono font-bold text-xs shadow-xs text-white bg-white/20"></span>
                        <div>
                            <h3 class="text-sm font-bold">Détails de la réservation</h3>
                            <p class="text-[11px] text-emerald-100">Machine, utilisateur et créneau réservé</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeReservationModal()" class="w-8 h-8 rounded-full hover:bg-white/20 text-white flex items-center justify-center transition-colors text-lg">
                        &times;
                    </button>
                </div>

                <!-- Modal Body with 3 distinct cards -->
                <div class="p-5 space-y-3.5">
                    <!-- 1. Machine Card -->
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center space-x-3">
                        <div id="resModalMachineIconBox" class="w-10 h-10 rounded-lg flex items-center justify-center text-white shrink-0 font-bold shadow-xs">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Machine réservée</span>
                            <h4 id="resModalMachineName" class="text-sm font-bold text-slate-800 font-mono tracking-wide"></h4>
                            <span id="resModalMachineType" class="text-xs text-slate-500 font-medium"></span>
                        </div>
                    </div>

                    <!-- 2. Bénéficiaire / User Card -->
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center space-x-3">
                        <div id="resModalUserAvatar" class="w-10 h-10 rounded-full bg-[#00897b] text-white flex items-center justify-center font-bold text-sm shadow-xs shrink-0">
                            U
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Utilisateur bénéficiaire</span>
                            <h4 id="resModalUserName" class="text-sm font-bold text-slate-800 truncate font-mono"></h4>
                        </div>
                    </div>

                    <!-- 3. Time Reserved Card -->
                    <div class="bg-emerald-50/60 border border-emerald-200 rounded-xl p-3.5 space-y-2">
                        <span class="text-[10px] uppercase font-bold tracking-wider text-[#00695c] block">Temps Réservé</span>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-slate-500 text-[10px] block">Date de la séance</span>
                                <span id="resModalDate" class="font-bold text-slate-800 capitalize"></span>
                            </div>
                            <div>
                                <span class="text-slate-500 text-[10px] block">Créneau horaire</span>
                                <span id="resModalTime" class="font-bold text-[#00897b] font-mono"></span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-emerald-200/60 flex items-center justify-between text-xs">
                            <span class="text-slate-500">Durée décomptée :</span>
                            <span id="resModalDuration" class="font-bold text-slate-800 font-mono"></span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <div>
                        <form id="resModalDeleteForm" method="POST" action="/bookings/cancel" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette réservation ? Votre quota vous sera restitué.');" class="inline">
                            <input type="hidden" id="resModalDelDate" name="date" value="">
                            <input type="hidden" id="resModalDelCode" name="code" value="">
                            <input type="hidden" id="resModalDelTime" name="time" value="">
                            <input type="hidden" id="resModalDelRedirect" name="redirect_to" value="">
                            <button type="submit" id="resModalDeleteBtn" class="px-3.5 py-2 bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white border border-rose-200 hover:border-rose-600 text-xs font-bold rounded-lg transition-colors flex items-center space-x-1.5 shadow-xs cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                <span>Supprimer la réservation</span>
                            </button>
                        </form>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button type="button" onclick="closeReservationModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-lg transition-colors">
                            Fermer
                        </button>
                        <a id="resModalBookMachineLink" href="/reserver" class="px-4 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center space-x-1.5">
                            <span>Réserver cette machine</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function openReservationModal(encodedData) {
            try {
                const data = JSON.parse(decodeURIComponent(encodedData));
                const badge = document.getElementById('resModalBadge');
                if (badge) {
                    badge.textContent = data.code;
                    badge.style.backgroundColor = data.bg || '#00897b';
                    badge.className = 'px-2.5 py-1 rounded font-mono font-bold text-xs shadow-xs ' + (data.textColor || 'text-white');
                }
                const iconBox = document.getElementById('resModalMachineIconBox');
                if (iconBox) iconBox.style.backgroundColor = data.bg || '#00897b';
                
                document.getElementById('resModalMachineName').textContent = data.code;
                document.getElementById('resModalMachineType').textContent = data.type || (data.code.startsWith('SL') ? 'Sèche-linge' : 'Machine à laver');
                
                const userEmail = data.email || data.user || 'etudiant@fecc.ma';
                const initial = userEmail.charAt(0).toUpperCase();
                const avatar = document.getElementById('resModalUserAvatar');
                if (avatar) avatar.textContent = initial;
                document.getElementById('resModalUserName').textContent = userEmail;

                document.getElementById('resModalDate').textContent = data.dateFormatted || data.date;
                document.getElementById('resModalTime').textContent = data.time || '';
                document.getElementById('resModalDuration').textContent = data.duration || '1 h';

                const bookLink = document.getElementById('resModalBookMachineLink');
                if (bookLink) bookLink.href = '/reserver?machine=' + encodeURIComponent(data.code) + '&date=' + encodeURIComponent(data.date);

                const deleteForm = document.getElementById('resModalDeleteForm');
                const canCancel = (data.user === '${currentUserName}' || ${state.isAdmin});
                if (deleteForm) {
                    deleteForm.style.display = canCancel ? 'inline' : 'none';
                    document.getElementById('resModalDelDate').value = data.date || '';
                    document.getElementById('resModalDelCode').value = data.code || '';
                    document.getElementById('resModalDelTime').value = data.time || '';
                    document.getElementById('resModalDelRedirect').value = '/calendrier?date=' + encodeURIComponent(data.date);
                }

                const modal = document.getElementById('reservationDetailsModal');
                if (modal) modal.classList.remove('hidden');
            } catch (e) {
                console.error('Error opening reservation modal:', e);
            }
        }

        function closeReservationModal() {
            const modal = document.getElementById('reservationDetailsModal');
            if (modal) modal.classList.add('hidden');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeReservationModal();
        });
        document.getElementById('reservationDetailsModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'reservationDetailsModal') closeReservationModal();
        });
        </script>
    </div>`;
}

// Dedicated full page for reservation (Multi-slot selection + 8h quota system)
function renderDedicatedReservationPage(selectedMachine = 'ML1-OM') {
    const washers = [...state.machines.filter(m => m.type === 'washer')].sort((a, b) => a.code.localeCompare(b.code));
    const dryers = [...state.machines.filter(m => m.type === 'dryer')].sort((a, b) => a.code.localeCompare(b.code));
    const remaining = Math.max(0, state.weeklyLimit - state.user.weeklyUsed);
    
    // Initial available slots for selected machine
    const initDate = '2026-09-30';
    const initAvailable = ALL_HOURLY_SLOTS.filter(s => !isSlotBooked(state.reservations, selectedMachine, initDate, s));

    return `
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div>
                <h1 class="text-xl font-bold text-slate-800 tracking-tight">Réserver une machine</h1>
                <p class="text-xs text-slate-500">Planification des créneaux horaires disponibles par machine (24h/24)</p>
            </div>
            <a href="/calendrier" class="text-xs text-[#00897b] hover:underline font-semibold flex items-center space-x-1 self-start sm:self-auto">
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
                        <label for="machineSelect" class="block font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Machine
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
                            <svg class="w-4 h-4 text-emerald-700 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span>Surveillance du Quota Hebdomadaire (${state.isAdmin ? '100 crédits / sem' : '8 crédits / sem'})</span>
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
                            <span id="statCurrentBalance" class="font-bold text-slate-800 text-sm font-mono">${remaining} / ${state.weeklyLimit} crédits</span>
                        </div>

                        <div class="bg-white/80 p-2.5 rounded border border-emerald-100">
                            <span class="text-[10px] uppercase text-slate-400 font-bold block">Solde après</span>
                            <span id="statBalanceAfter" class="font-bold text-emerald-700 text-sm font-mono">${remaining} / ${state.weeklyLimit} crédits</span>
                        </div>
                    </div>

                    <!-- Insufficient Quota Alert -->
                    <div id="quotaExceededAlert" class="hidden p-2.5 bg-rose-50 border border-rose-200 text-rose-800 rounded font-semibold text-[11px] flex items-center space-x-2">
                        <svg class="w-4 h-4 text-rose-600 shrink-0 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>Dépassement de quota : vous avez sélectionné plus d'heures que votre solde hebdomadaire restant (<span id="alertRemainingSpan">${remaining}</span>h disponibles sur ${state.weeklyLimit}h).</span>
                    </div>
                </div>

                <div class="pt-3 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 sm:space-x-3 border-t border-slate-200">
                    <a href="/calendrier" 
                       class="px-4 py-2.5 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded text-center">
                        Annuler
                    </a>
                    <button type="submit" id="submitBookingBtn" disabled
                            class="px-6 py-2.5 opacity-50 cursor-not-allowed bg-slate-400 text-white text-xs font-bold rounded transition-all shadow-xs text-center">
                        Sélectionnez au moins 1 créneau
                    </button>
                </div>

                <!-- Sticky Mobile Bottom Bar (Visible on mobile screens < sm) -->
                <div id="mobileStickyBar" style="display: none;" 
                     class="sm:hidden fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-slate-200 px-4 py-3 shadow-2xl z-40 flex items-center justify-between safe-pb">
                    <div>
                        <div id="stickySelectedCount" class="text-xs font-bold text-slate-800">0 créneau</div>
                        <div id="stickyCost" class="text-[11px] text-[#00897b] font-semibold font-mono">0 crédit</div>
                    </div>
                    <button type="button" onclick="document.getElementById('bookingForm').submit()" id="stickySubmitBtn" disabled
                            class="px-5 py-2.5 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded-lg shadow-md transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                        Confirmer
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
            return '<label id="card-' + idx + '" class="flex items-center justify-between p-2.5 rounded border border-slate-200 bg-white text-slate-700 hover:border-[#00897b] transition-all cursor-pointer select-none text-xs min-h-[44px]">' +
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
            check.innerHTML = '<svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';
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

        const afterBalance = remaining - count;
        if (afterBalance < 0) {
            balanceAfterEl.innerText = afterBalance + 'h / ' + WEEKLY_LIMIT + 'h (Dépassé)';
            balanceAfterEl.className = 'font-bold text-rose-600 text-sm font-mono';
            alertEl.classList.remove('hidden');
            document.getElementById('alertRemainingSpan').innerText = remaining;
            btn.disabled = true;
            btn.className = 'px-6 py-2.5 opacity-50 cursor-not-allowed bg-rose-500 text-white text-xs font-bold rounded transition-all shadow-xs';
            btn.innerText = 'Quota insuffisant (' + remaining + 'h restantes sur ' + WEEKLY_LIMIT + 'h)';
        } else {
            balanceAfterEl.innerText = afterBalance + 'h / ' + WEEKLY_LIMIT + 'h';
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

        const mobileBar = document.getElementById('mobileStickyBar');
        const stickyCount = document.getElementById('stickySelectedCount');
        const stickyCost = document.getElementById('stickyCost');
        const stickyBtn = document.getElementById('stickySubmitBtn');

        if (mobileBar && stickyCount && stickyCost && stickyBtn) {
            if (count > 0 && afterBalance >= 0) {
                mobileBar.style.display = 'flex';
                stickyCount.innerText = count + ' créneau' + (count > 1 ? 'x' : '');
                stickyCost.innerText = count + ' crédit' + (count > 1 ? 's' : '');
                stickyBtn.disabled = false;
            } else if (count > 0 && afterBalance < 0) {
                mobileBar.style.display = 'flex';
                stickyCount.innerText = 'Quota dépassé';
                stickyCost.innerText = remaining + 'h restants';
                stickyBtn.disabled = true;
            } else {
                mobileBar.style.display = 'none';
                stickyBtn.disabled = true;
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
    const limit = state.isAdmin ? 100 : 8;
    const remaining = Math.max(0, limit - state.user.weeklyUsed);
    const userName = state.isAdmin ? 'El Omari' : 'Alex Rivera';

    // Extract user reservations
    const userReservations = [];
    state.reservations.forEach(r => {
        if (r.multi) {
            r.multi.forEach(m => {
                if (m.user === userName || (!state.isAdmin && m.user === 'Alex Rivera') || (state.isAdmin && (m.user === 'El Omari' || m.user === 'R. Omari'))) {
                    userReservations.push({
                        date: r.date || '2026-09-30',
                        time: m.time,
                        code: m.code,
                        bg: m.bg,
                        textColor: m.textColor || 'text-white',
                        user: m.user,
                        durationHours: 1
                    });
                }
            });
        } else {
            if (r.user === userName || (!state.isAdmin && r.user === 'Alex Rivera') || (state.isAdmin && (r.user === 'El Omari' || r.user === 'R. Omari'))) {
                userReservations.push({
                    date: r.date || '2026-09-30',
                    time: r.time,
                    code: r.code,
                    bg: r.bg,
                    textColor: r.textColor || 'text-white',
                    user: r.user,
                    durationHours: r.durationHours || 1
                });
            }
        }
    });

    return `
    <div class="space-y-6 max-w-6xl mx-auto">
        <!-- 1. Welcome Message Banner -->
        <div style="background: linear-gradient(135deg, #004d40 0%, #00695c 50%, #00796b 100%);" class="rounded-xl p-6 text-white shadow-md flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2 mb-1.5">
                    <span class="px-2 py-0.5 rounded bg-white/20 text-[10px] font-bold tracking-wider uppercase">Tableau de bord</span>
                    <span class="text-emerald-200 text-xs font-medium">Buanderie Centrale Casablanca</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight" style="text-wrap: balance;">Bonjour, ${userName} !</h1>
                <p class="text-xs text-emerald-100/90 mt-1 max-w-xl leading-relaxed" style="text-wrap: pretty;">
                    Bienvenue sur votre espace buanderie. Consultez ci-dessous vos crédits disponibles ainsi que l'historique complet de vos créneaux.
                </p>
            </div>
            <div class="flex items-center space-x-3 shrink-0">
                <a href="/reserver" class="px-4 py-2.5 bg-white hover:bg-emerald-50 text-[#00695c] rounded-lg text-xs font-bold transition-all shadow-sm flex items-center space-x-1.5 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none">
                    <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Réserver une machine</span>
                </a>
                <a href="/calendrier" class="px-4 py-2.5 bg-white/10 hover:bg-white/20 border border-white/30 text-white rounded-lg text-xs font-bold transition-all focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none">
                    <span>Voir le calendrier</span>
                </a>
            </div>
        </div>

        <!-- 2. Quota & Information Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Quota Remaining Card (Highlight) -->
            <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Heures réservables</span>
                        <span class="px-2.5 py-0.5 rounded text-[11px] font-mono font-bold ${state.isAdmin ? 'bg-amber-100 text-amber-800' : (remaining > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800')}">
                            ${state.isAdmin ? 'Admin (100 crédits / sem)' : 'Étudiant (8 crédits / sem)'}
                        </span>
                    </div>

                    <div class="mt-4 flex items-baseline space-x-2">
                        <span class="text-3xl font-extrabold ${remaining > 0 ? (state.isAdmin ? 'text-amber-700' : 'text-emerald-600') : 'text-rose-600'} font-mono">
                            ${remaining}h
                        </span>
                        <span class="text-xs text-slate-500 font-medium">restantes sur ${limit}h cette semaine</span>
                    </div>

                    <!-- Visual Progress Bar -->
                    <div class="mt-3.5">
                        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                            <div class="h-2.5 rounded-full transition-all duration-500 ${remaining === 0 ? 'bg-rose-500' : (state.isAdmin ? 'bg-amber-500' : 'bg-[#00897b]')}"
                                 style="width: ${Math.round((remaining / limit) * 100)}%"></div>
                        </div>
                        <div class="flex justify-between items-center text-[10px] text-slate-400 mt-1.5 font-medium">
                            <span>${state.user.weeklyUsed}h utilisées sur ${limit}h</span>
                            <span>${remaining}h disponibles</span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">1 heure = 1 crédit</span>
                    <a href="/reserver" class="text-[#00897b] font-bold hover:underline flex items-center space-x-1">
                        <span>Réserver un créneau</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </div>

            <!-- Rules & Quota Policy Card (No machine count, no total reservations count) -->
            <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Règles & Utilisation des crédits</span>
                        <span class="px-2.5 py-0.5 rounded text-[11px] font-mono font-bold bg-slate-100 text-slate-700">Campus ECC</span>
                    </div>

                    <div class="mt-4 space-y-2.5 text-xs text-slate-600">
                        <div class="flex items-start space-x-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#00897b] mt-1.5 shrink-0"></span>
                            <span><strong>Renouvellement hebdomadaire :</strong> Vos crédits se réinitialisent chaque lundi à 00h00.</span>
                        </div>
                        <div class="flex items-start space-x-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#00897b] mt-1.5 shrink-0"></span>
                            <span><strong>Disponibilité 24h/24 :</strong> Choisissez librement n'importe quel créneau ouvert sur toute la journée.</span>
                        </div>
                        <div class="flex items-start space-x-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#00897b] mt-1.5 shrink-0"></span>
                            <span><strong>Réservation multi-créneaux :</strong> Vous pouvez sélectionner plusieurs heures en une seule étape.</span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-400">Campus Centrale Casablanca</span>
                    <a href="/calendrier" class="text-[#00897b] font-bold hover:underline flex items-center space-x-1">
                        <span>Ouvrir le calendrier</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- 3. Reservation History Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 bg-slate-50/80 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Historique de vos réservations</h2>
                    <p class="text-[11px] text-slate-500">Liste complète de vos créneaux réservés et validés</p>
                </div>
                <a href="/reserver" class="px-3.5 py-1.5 bg-[#00897b] hover:bg-[#00796b] text-white rounded text-xs font-bold shadow-xs flex items-center space-x-1">
                    <span>+</span>
                    <span>Nouveau créneau</span>
                </a>
            </div>

            ${userReservations.length === 0 ? `
                <div class="p-8 text-center">
                    <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-xs font-bold text-slate-600">Aucune réservation pour le moment</p>
                    <p class="text-[11px] text-slate-400 mt-1">Vous n'avez pas encore réservé de créneau cette semaine.</p>
                    <a href="/reserver" class="inline-block mt-3 px-4 py-2 bg-[#00897b] text-white text-xs font-bold rounded">
                        Réserver votre premier créneau
                    </a>
                </div>
            ` : `
                <!-- Mobile Reservation Cards (Visible on mobile screens < sm) -->
                <div class="sm:hidden p-3 space-y-3">
                    ${userReservations.map(res => `
                        <div class="bg-white border border-slate-200 rounded-xl p-3.5 space-y-2.5 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span style="background-color: ${res.bg || '#4338ca'};" class="px-2.5 py-1 rounded text-xs font-mono font-bold ${res.textColor || 'text-white'} shadow-xs inline-block">
                                    ${res.code}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] inline-flex items-center space-x-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                    <span>Confirmé</span>
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                                <div class="flex items-center space-x-1.5 text-slate-700">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span class="font-medium">${res.date}</span>
                                </div>
                                <div class="flex items-center space-x-1.5 text-slate-800 font-mono font-bold">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>${res.time}</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                                <span class="text-[11px] text-slate-600 font-semibold">
                                    ${res.durationHours} h (${res.durationHours} crédit${res.durationHours > 1 ? 's' : ''})
                                </span>
                                <div class="flex items-center space-x-1.5">
                                    <a href="/calendrier?date=${res.date}" class="px-2.5 py-1 text-xs font-semibold text-[#00897b] bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition-colors">
                                        Voir
                                    </a>
                                    <form method="POST" action="/bookings/cancel" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette réservation ? Votre quota vous sera restitué.');" class="inline">
                                        <input type="hidden" name="date" value="${res.date}">
                                        <input type="hidden" name="code" value="${res.code}">
                                        <input type="hidden" name="time" value="${res.time}">
                                        <input type="hidden" name="redirect_to" value="/dashboard">
                                        <button type="submit" class="px-2.5 py-1 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-lg border border-rose-200 transition-colors inline-flex items-center space-x-1" title="Annuler cette réservation">
                                            <svg class="w-3 h-3 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            <span>Annuler</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>

                <!-- Desktop Table View (Visible on >= sm) -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100/75 text-slate-700 font-bold border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Machine</th>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4">Créneau horaire</th>
                                <th class="py-3 px-4">Durée & Crédits</th>
                                <th class="py-3 px-4">Statut</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            ${userReservations.map(res => `
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-4">
                                        <span style="background-color: ${res.bg || '#4338ca'};" class="px-2.5 py-1 rounded text-xs font-mono font-bold ${res.textColor || 'text-white'} shadow-xs inline-block">
                                            ${res.code}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-700 font-medium">
                                        ${res.date}
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold text-slate-800">
                                        ${res.time}
                                    </td>
                                    <td class="py-3 px-4 text-slate-600 font-semibold">
                                        ${res.durationHours} h (${res.durationHours} crédit${res.durationHours > 1 ? 's' : ''})
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] inline-flex items-center space-x-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                            <span>Confirmé</span>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="inline-flex items-center space-x-1.5">
                                            <a href="/calendrier?date=${res.date}" class="px-2.5 py-1 text-[11px] font-semibold text-[#00897b] hover:bg-emerald-50 rounded border border-emerald-200 transition-colors inline-block" title="Voir sur le calendrier">
                                                Voir au calendrier &rarr;
                                            </a>
                                            <form method="POST" action="/bookings/cancel" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette réservation ? Votre quota vous sera restitué.');" class="inline">
                                                <input type="hidden" name="date" value="${res.date}">
                                                <input type="hidden" name="code" value="${res.code}">
                                                <input type="hidden" name="time" value="${res.time}">
                                                <input type="hidden" name="redirect_to" value="/dashboard">
                                                <button type="submit" class="px-2 py-1 text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 hover:border-rose-300 rounded border border-rose-200 transition-colors inline-flex items-center space-x-1" title="Annuler cette réservation">
                                                    <svg class="w-3 h-3 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    <span>Annuler</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `}
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

// 5. Gestion des utilisateurs (ADMIN ONLY - Dynamic Database)
function renderUsersPage(flash = '') {
    if (!state.isAdmin) {
        return `<div class="p-8 text-center text-rose-600 font-bold bg-white rounded border border-rose-200">Accès interdit : Cette page est réservée aux administrateurs.</div>`;
    }

    const avatarColors = ['#00897b', '#2563eb', '#9333ea', '#d97706', '#059669', '#4f46e5', '#e11d48', '#0d9488'];

    const mobileCards = state.users.map((u, idx) => {
        const initial = (u.name || 'U').charAt(0).toUpperCase();
        const avatarBg = avatarColors[idx % avatarColors.length];
        const isAdmin = u.role === 'admin';
        const limit = u.weeklyLimit || (isAdmin ? 100 : 8);
        const used = u.weeklyUsed || 0;
        const remaining = Math.max(0, limit - used);
        const searchKeywords = `${u.name} ${u.email} ${u.role === 'admin' ? 'admin administrateur' : 'etudiant étudiant student'}`.toLowerCase();
        const jsonEscaped = JSON.stringify(u).replace(/"/g, '&quot;');

        return `
        <div class="user-card-item bg-slate-50/70 border border-slate-200 rounded-xl p-3.5 space-y-2.5 transition-all" data-search="${searchKeywords}">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="w-7 h-7 rounded-full text-white flex items-center justify-center font-bold text-xs shadow-xs" style="background-color: ${avatarBg};">${initial}</span>
                    <span class="font-bold text-sm text-slate-800">${u.name}</span>
                </div>
                <span class="px-2.5 py-0.5 rounded ${isAdmin ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'} font-bold text-[10px]">${isAdmin ? 'Administrateur' : 'Étudiant'}</span>
            </div>
            <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200/60 font-mono">
                <span class="text-slate-500 truncate max-w-[180px]">${u.email}</span>
                <span class="font-bold ${isAdmin ? 'text-[#00897b]' : (used > 2 ? 'text-amber-600' : 'text-emerald-600')}">
                    ${isAdmin ? 'Quota : 100 crédits' : `${used}h / ${limit}h (${remaining}h rest.)`}
                </span>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-end space-x-2">
                ${!isAdmin ? `<a href="/reset-user-quota?id=${u.id}" class="px-3 py-1 bg-emerald-50 text-[#00897b] border border-emerald-200 text-xs font-semibold rounded shadow-2xs hover:bg-emerald-100 transition-colors">Réinitialiser quota</a>` : ''}
                <button type="button" onclick="openEditUserModal(${jsonEscaped})" class="px-3 py-1 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded shadow-2xs hover:bg-slate-50 hover:border-slate-400 transition-colors flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Modifier</span>
                </button>
            </div>
        </div>`;
    }).join('');

    const desktopRows = state.users.map((u, idx) => {
        const initial = (u.name || 'U').charAt(0).toUpperCase();
        const avatarBg = avatarColors[idx % avatarColors.length];
        const isAdmin = u.role === 'admin';
        const limit = u.weeklyLimit || (isAdmin ? 100 : 8);
        const used = u.weeklyUsed || 0;
        const remaining = Math.max(0, limit - used);
        const searchKeywords = `${u.name} ${u.email} ${u.role === 'admin' ? 'admin administrateur' : 'etudiant étudiant student'}`.toLowerCase();
        const jsonEscaped = JSON.stringify(u).replace(/"/g, '&quot;');

        return `
        <tr class="user-row-item hover:bg-slate-50 transition-colors" data-search="${searchKeywords}">
            <td class="p-3.5 font-bold text-slate-800 flex items-center space-x-2">
                <span class="w-7 h-7 rounded-full text-white flex items-center justify-center font-bold text-[11px] shadow-xs shrink-0" style="background-color: ${avatarBg};">${initial}</span>
                <span class="truncate">${u.name}</span>
            </td>
            <td class="p-3.5 text-slate-600 font-mono">${u.email}</td>
            <td class="p-3.5">
                <span class="px-2.5 py-0.5 rounded ${isAdmin ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'} font-bold text-[10px]">${isAdmin ? 'Administrateur' : 'Étudiant'}</span>
            </td>
            <td class="p-3.5 font-bold ${isAdmin ? 'text-[#00897b]' : (used > 2 ? 'text-amber-600' : 'text-emerald-600')}">
                ${isAdmin ? 'Illimité (100 crédits / sem)' : `${used}h / ${limit}h utilisées (${remaining}h restantes)`}
            </td>
            <td class="p-3.5 text-right space-x-2 whitespace-nowrap">
                ${!isAdmin ? `<a href="/reset-user-quota?id=${u.id}" class="text-[#00897b] hover:underline font-semibold cursor-pointer text-xs">Réinitialiser quota</a>` : ''}
                <button type="button" onclick="openEditUserModal(${jsonEscaped})" class="inline-flex items-center space-x-1 text-slate-600 hover:text-[#00897b] font-semibold cursor-pointer text-xs px-2 py-1 rounded hover:bg-slate-100 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Modifier</span>
                </button>
            </td>
        </tr>`;
    }).join('');

    return `
    <div class="space-y-6 max-w-6xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Gestion des Utilisateurs</h1>
                <p class="text-xs text-slate-500">Supervision des comptes réels de la base de données (<span id="userTotalCount">${state.users.length}</span> comptes enregistrés) et suivi des quotas</p>
            </div>
            <a href="/register" class="px-4 py-2 bg-[#00897b] hover:bg-[#00796b] text-white rounded text-xs font-bold shadow-xs self-start sm:self-auto transition-colors flex items-center space-x-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Ajouter un utilisateur</span>
            </a>
        </div>

        <!-- Search Bar & Stats -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div class="relative flex-1 max-w-lg">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" id="userSearchInput" oninput="filterUsers(this.value)" placeholder="Rechercher par nom, email, rôle..."
                       class="w-full pl-10 pr-9 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:bg-white focus:border-[#00897b] focus:ring-1 focus:ring-[#00897b] transition-all">
                <button type="button" id="clearSearchBtn" onclick="clearUserSearch()" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 text-sm font-bold">
                    &times;
                </button>
            </div>
            <div class="text-xs text-slate-500 font-medium shrink-0 flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                <span><strong id="userMatchCount" class="text-slate-800 font-bold">${state.users.length}</strong> utilisateur(s) affiché(s)</span>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <!-- Mobile User Cards (< sm) -->
            <div id="mobileCardsContainer" class="sm:hidden p-3 space-y-3">
                ${mobileCards}
            </div>

            <!-- Desktop Table (>= sm) -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left text-xs min-w-[620px]">
                    <thead class="bg-[#00897b] text-white font-bold">
                        <tr>
                            <th class="p-3.5">Nom</th>
                            <th class="p-3.5">Email</th>
                            <th class="p-3.5">Rôle</th>
                            <th class="p-3.5">Quota Hebdomadaire</th>
                            <th class="p-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="desktopTableBody" class="divide-y divide-slate-100">
                        ${desktopRows}
                    </tbody>
                </table>
            </div>

            <!-- Empty Search Results State -->
            <div id="noUsersMatchMsg" class="hidden p-8 text-center bg-slate-50/50">
                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <p class="text-xs font-bold text-slate-700">Aucun utilisateur ne correspond à votre recherche</p>
                <p class="text-[11px] text-slate-400 mt-1">Essayez un autre mot-clé ou réinitialisez le filtre.</p>
                <button type="button" onclick="clearUserSearch()" class="mt-3 px-3.5 py-1.5 bg-[#00897b] text-white text-xs font-bold rounded shadow-xs hover:bg-[#00796b] transition-colors">
                    Effacer la recherche
                </button>
            </div>
        </div>

        <!-- MODAL : MODIFIER UN UTILISATEUR -->
        <div id="editUserModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg overflow-hidden transform transition-all flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div style="background: linear-gradient(135deg, #004d40 0%, #00796b 100%); color: white;" class="px-6 py-4 text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold">Modifier l'utilisateur</h3>
                            <p class="text-[11px] text-emerald-100">Modifiez les informations du profil et le quota</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeEditUserModal()" class="w-8 h-8 rounded-full hover:bg-white/20 text-white flex items-center justify-center transition-colors text-lg">
                        &times;
                    </button>
                </div>

                <!-- Modal Body / Form -->
                <form id="editUserForm" action="/admin/users/update" method="POST" class="p-6 space-y-4 overflow-y-auto">
                    <input type="hidden" id="editUserId" name="id" value="">

                    <div>
                        <label for="editUserName" class="block text-xs font-bold text-slate-700 mb-1">Nom complet</label>
                        <input type="text" id="editUserName" name="name" required
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-[#00897b] focus:ring-1 focus:ring-[#00897b]">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="editUserEmail" class="block text-xs font-bold text-slate-700 mb-1">Email institutionnel</label>
                            <input type="email" id="editUserEmail" name="email" required
                                   class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-[#00897b] focus:ring-1 focus:ring-[#00897b]">
                        </div>
                        <div>
                            <label for="editUserRole" class="block text-xs font-bold text-slate-700 mb-1">Rôle</label>
                            <select id="editUserRole" name="role"
                                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-[#00897b] focus:ring-1 focus:ring-[#00897b] bg-white">
                                <option value="student">Étudiant</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                        <div>
                            <label for="editUserLimit" class="block text-xs font-bold text-slate-700 mb-1">Quota hebdomadaire (heures)</label>
                            <input type="number" id="editUserLimit" name="weeklyLimit" min="1" max="200" required
                                   class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-[#00897b] focus:ring-1 focus:ring-[#00897b]">
                            <span class="text-[10px] text-slate-400">Standard étudiant : 8h / sem</span>
                        </div>
                        <div>
                            <label for="editUserUsed" class="block text-xs font-bold text-slate-700 mb-1">Heures consommées</label>
                            <input type="number" id="editUserUsed" name="weeklyUsed" min="0" max="200" required
                                   class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-[#00897b] focus:ring-1 focus:ring-[#00897b]">
                            <span class="text-[10px] text-slate-400">Heures réservées cette semaine</span>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 flex items-center justify-end space-x-2 shrink-0">
                        <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-colors">
                            Annuler
                        </button>
                        <button type="submit" class="px-5 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center space-x-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Enregistrer les modifications</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function filterUsers(query) {
            const q = (query || '').trim().toLowerCase();
            const clearBtn = document.getElementById('clearSearchBtn');
            if (clearBtn) clearBtn.style.display = q ? 'flex' : 'none';

            let matchCount = 0;
            const cards = document.querySelectorAll('.user-card-item');
            cards.forEach(card => {
                const search = (card.getAttribute('data-search') || '').toLowerCase();
                const matched = !q || search.includes(q);
                card.style.display = matched ? '' : 'none';
            });

            const rows = document.querySelectorAll('.user-row-item');
            rows.forEach(row => {
                const search = (row.getAttribute('data-search') || '').toLowerCase();
                const matched = !q || search.includes(q);
                row.style.display = matched ? '' : 'none';
                if (matched) matchCount++;
            });

            const countDisplay = document.getElementById('userMatchCount');
            if (countDisplay) countDisplay.textContent = matchCount;

            const emptyMsg = document.getElementById('noUsersMatchMsg');
            if (emptyMsg) emptyMsg.style.display = (matchCount === 0) ? 'block' : 'none';
        }

        function clearUserSearch() {
            const input = document.getElementById('userSearchInput');
            if (input) {
                input.value = '';
                filterUsers('');
                input.focus();
            }
        }

        function openEditUserModal(user) {
            if (!user) return;
            document.getElementById('editUserId').value = user.id || '';
            document.getElementById('editUserName').value = user.name || '';
            document.getElementById('editUserEmail').value = user.email || '';
            document.getElementById('editUserRole').value = user.role || 'student';
            document.getElementById('editUserLimit').value = user.weeklyLimit !== undefined ? user.weeklyLimit : (user.role === 'admin' ? 100 : 8);
            document.getElementById('editUserUsed').value = user.weeklyUsed !== undefined ? user.weeklyUsed : 0;

            const modal = document.getElementById('editUserModal');
            if (modal) modal.classList.remove('hidden');
        }

        function closeEditUserModal() {
            const modal = document.getElementById('editUserModal');
            if (modal) modal.classList.add('hidden');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeEditUserModal();
        });
        document.getElementById('editUserModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'editUserModal') closeEditUserModal();
        });
        </script>
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
function renderLoginPage(flashMessage = '') {
    let html = fs.readFileSync(path.join(__dirname, 'resources/views/auth/login.blade.php'), 'utf8')
        .replace("@vite(['resources/css/app.css', 'resources/js/app.js'])", `<link rel="stylesheet" href="/build/${getAssets().cssFile}"><script defer src="/build/${getAssets().jsFile}"></script>`)
        .replace("{{ route('login') }}", "/login")
        .replaceAll("{{ route('register') }}", "/register")
        .replace("{{ route('password.request') }}", "/forgot-password")
        .replace("{{ old('email', 'admin@fecc.ma') }}", "admin@fecc.ma")
        .replace("@csrf", "");

    if (flashMessage) {
        html = html
            .replace("@if (session('status'))", '')
            .replace("{{ session('status') }}", flashMessage)
            .replace("@endif", '')
            .replace("@if (session('success'))", '<!--')
            .replace("{{ session('success') }}", '')
            .replace("@endif", '-->');
    } else {
        html = html.replace(/@if \(session\('(?:status|success)'\)\)[\s\S]*?@endif/g, '');
    }
    return html;
}

// Forgot Password Page
function renderForgotPasswordPage(statusMessage = '') {
    let html = fs.readFileSync(path.join(__dirname, 'resources/views/auth/forgot-password.blade.php'), 'utf8')
        .replace("@vite(['resources/css/app.css', 'resources/js/app.js'])", `<link rel="stylesheet" href="/build/${getAssets().cssFile}"><script defer src="/build/${getAssets().jsFile}"></script>`)
        .replace("{{ route('password.email') }}", "/forgot-password")
        .replace("{{ route('login') }}", "/login")
        .replace("{{ old('email') }}", "")
        .replace("@csrf", "");

    if (statusMessage) {
        html = html
            .replace("@if (session('status'))", '')
            .replace("{{ session('status') }}", statusMessage)
            .replace("@endif", '');
    } else {
        html = html.replace(/@if \(session\('status'\)\)[\s\S]*?@endif/g, '');
    }

    html = html.replace(/@if \(\$errors->any\(\)\)[\s\S]*?@endif/g, '');
    return html;
}

// Reset Password Page
function renderResetPasswordPage(token = 'demo-token', email = '') {
    let html = fs.readFileSync(path.join(__dirname, 'resources/views/auth/reset-password.blade.php'), 'utf8')
        .replace("@vite(['resources/css/app.css', 'resources/js/app.js'])", `<link rel="stylesheet" href="/build/${getAssets().cssFile}"><script defer src="/build/${getAssets().jsFile}"></script>`)
        .replace("{{ route('password.update') }}", "/reset-password")
        .replace("{{ route('login') }}", "/login")
        .replace("{{ $token }}", token)
        .replace("{{ old('email', $email) }}", email)
        .replace("@csrf", "");
    return html;
}

// Register Page (Centrale Casablanca Design with Email notification)
function renderRegisterPage(flashMessage = '', errorMessage = '') {
    const assets = getAssets();
    return `<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Créer un compte - Buanderie Centrale Casablanca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="/build/${assets.cssFile}">
    <script defer src="/build/${assets.jsFile}"></script>
</head>
<body class="min-h-screen login-bg flex items-center justify-center p-4">
    <!-- Split Register Card -->
    <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row min-h-[580px]">
        
        <!-- Left Side: Registration Form -->
        <div class="w-full md:w-1/2 p-6 md:p-10 flex flex-col justify-center items-center">
            
            <!-- Centrale Casablanca Logo -->
            <div class="flex flex-col items-center mb-5">
                <div class="w-12 h-9 relative flex items-center justify-center">
                    <svg aria-hidden="true" viewBox="0 0 100 70" class="w-12 h-9 text-[#00897b]" fill="currentColor">
                        <path d="M 50 10 C 25 10 15 25 15 40 C 15 55 30 65 60 65 C 75 65 85 58 85 58 L 80 50 C 80 50 72 55 60 55 C 38 55 27 47 27 38 C 27 28 35 20 50 20 C 65 20 78 27 82 32 L 88 24 C 82 17 68 10 50 10 Z"/>
                        <path d="M 45 4 C 65 4 80 14 85 20 L 78 26 C 74 21 62 13 45 13 Z" fill="#2e7d32"/>
                    </svg>
                </div>
                <div class="text-center mt-1">
                    <span class="text-sm font-bold text-slate-700 tracking-tight block">Centrale</span>
                    <span class="text-[8px] uppercase tracking-widest text-slate-500 font-semibold block -mt-1">Casablanca</span>
                </div>
            </div>

            <!-- Title -->
            <h1 class="text-xl font-extrabold text-slate-900 mb-1 text-center tracking-tight">
                Créer un compte étudiant
            </h1>
            <p class="text-xs text-slate-500 mb-4 text-center">Un e-mail de confirmation vous sera envoyé dès l'inscription</p>

            ${errorMessage ? `
                <div class="w-full max-w-sm p-3 mb-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center space-x-2">
                    <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>${errorMessage}</span>
                </div>` : ''}

            ${flashMessage ? `
                <div class="w-full max-w-sm p-3 mb-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center space-x-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>${flashMessage}</span>
                </div>` : ''}

            <!-- Form -->
            <form method="POST" action="/register" class="w-full max-w-sm space-y-3">
                <div>
                    <label for="name" class="block text-[11px] font-bold text-slate-600 mb-1">Nom complet</label>
                    <input type="text" id="name" name="name" placeholder="Ex: Jordan Miller" required autofocus
                           class="w-full px-3.5 py-2.5 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none transition-all">
                </div>

                <div>
                    <label for="email" class="block text-[11px] font-bold text-slate-600 mb-1">Email Institutionnel (@fecc.ma)</label>
                    <input type="email" id="email" name="email" placeholder="etudiant@fecc.ma" required
                           class="w-full px-3.5 py-2.5 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none transition-all">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="student_id" class="block text-[11px] font-bold text-slate-600 mb-1">Identifiant Étudiant</label>
                        <input type="text" id="student_id" name="student_id" placeholder="STU-99120" required
                               class="w-full px-3.5 py-2.5 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none transition-all">
                    </div>
                    <div>
                        <label for="room_number" class="block text-[11px] font-bold text-slate-600 mb-1">Bâtiment / Chambre</label>
                        <input type="text" id="room_number" name="room_number" placeholder="Bât. Omar, Ch. 102" required
                               class="w-full px-3.5 py-2.5 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="password" class="block text-[11px] font-bold text-slate-600 mb-1">Mot de passe</label>
                        <input type="password" id="password" name="password" placeholder="Min. 8 car." required
                               class="w-full px-3.5 py-2.5 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none transition-all">
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-[11px] font-bold text-slate-600 mb-1">Confirmation</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirmer" required
                               class="w-full px-3.5 py-2.5 rounded-md bg-[#f1f3f4] text-slate-800 placeholder-slate-400 text-xs border border-transparent focus:border-[#00b4a7] focus:bg-white focus:outline-none transition-all">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 rounded-full bg-[#00b4a7] hover:bg-[#009b8f] text-white font-bold text-xs uppercase tracking-wider shadow-md hover:shadow-lg transition-all active:scale-[0.98]">
                        Créer mon compte (Envoi email)
                    </button>
                </div>

                <div class="text-center pt-2">
                    <p class="text-xs text-slate-500">
                        Déjà inscrit ? 
                        <a href="/login" class="font-bold text-[#00897b] hover:underline">Se connecter</a>
                    </p>
                </div>
            </form>
        </div>

        <!-- Right Side: Welcome Banner -->
        <div class="hidden md:flex md:w-1/2 p-8 md:p-12 bg-gradient-to-br from-[#2e7d32] via-[#00897b] to-[#00695c] flex-col items-center justify-center text-white text-center">
            <h2 class="text-2xl font-extrabold mb-3 tracking-tight">
                Rejoignez la Buanderie
            </h2>
            <p class="text-xs text-white/90 mb-6 max-w-xs font-medium">
                Accédez au calendrier en temps réel et réservez vos machines à laver et sèche-linges sans attente.
            </p>

            <div class="w-28 h-32 bg-white rounded-2xl shadow-xl p-3 flex flex-col justify-between mb-6 relative">
                <div class="flex items-center justify-between border-b border-slate-200 pb-1 px-1">
                    <div class="flex space-x-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                    </div>
                    <span class="w-3 h-3 rounded-full bg-slate-400"></span>
                </div>
                <div class="w-16 h-16 mx-auto rounded-full bg-slate-200 p-1 flex items-center justify-center shadow-inner">
                    <div class="w-full h-full rounded-full bg-gradient-to-tr from-[#0288d1] via-[#29b6f6] to-[#039be5] relative overflow-hidden flex items-center justify-center border-2 border-slate-300">
                        <div class="absolute inset-0 bg-slate-800/30"></div>
                        <div class="w-5 h-5 rounded-full bg-white/20"></div>
                    </div>
                </div>
                <div class="flex justify-between px-2">
                    <span class="w-2 h-1 bg-slate-400 rounded-b"></span>
                    <span class="w-2 h-1 bg-slate-400 rounded-b"></span>
                </div>
            </div>

            <p class="text-xs text-white/90 mb-4 font-semibold">
                8 heures de réservation gratuites par semaine
            </p>

            <a href="/login" class="px-8 py-2 rounded-full border border-white text-white hover:bg-white/10 font-bold text-xs uppercase tracking-wider transition-all">
                Se connecter
            </a>
        </div>
    </div>
</body>
</html>`;
}


const server = http.createServer((req, res) => {
    const urlObj = new URL(req.url, `http://${req.headers.host}`);
    const pathname = urlObj.pathname;

    // Static compiled assets with compression and long-term caching
    if (pathname.startsWith('/build/')) {
        const filePath = path.join(__dirname, 'public', pathname);
        if (fs.existsSync(filePath)) {
            const ext = path.extname(filePath);
            const contentType = ext === '.css' ? 'text/css' : ext === '.js' ? 'application/javascript' : 'application/octet-stream';
            let content = staticCache.get(filePath);
            if (!content) {
                content = fs.readFileSync(filePath);
                staticCache.set(filePath, content);
            }
            return sendCompressedResponse(req, res, 200, {
                'Content-Type': contentType,
                'Cache-Control': 'public, max-age=31536000, immutable'
            }, content);
        }
    }

    // Role Toggle
    if (pathname === '/toggle-role') {
        state.isAdmin = !state.isAdmin;
        state.weeklyLimit = state.isAdmin ? 100 : 8;
        res.writeHead(302, { 'Location': '/dashboard' });
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
        const flash = urlObj.searchParams.get('flash') || '';
        return sendCompressedResponse(req, res, 200, { 'Content-Type': 'text/html; charset=utf-8' }, renderLoginPage(flash));
    }

    // Forgot Password Flow (Sends simulated email with token)
    if (pathname === '/forgot-password') {
        if (req.method === 'POST') {
            let body = '';
            req.on('data', chunk => body += chunk);
            req.on('end', () => {
                const params = new URLSearchParams(body);
                const email = params.get('email') || 'etudiant@fecc.ma';
                console.log(`\n📧 [EMAIL ENVOYÉ]`);
                console.log(`De: no-reply@ecclaundry.edu`);
                console.log(`À: ${email}`);
                console.log(`Objet: Réinitialisation de votre mot de passe - Buanderie Centrale Casablanca`);
                console.log(`Lien sécurisé: http://localhost:${PORT}/reset-password/demo-token?email=${encodeURIComponent(email)}\n`);
                
                const msg = encodeURIComponent(`Un e-mail de réinitialisation vous a été envoyé à ${email}. (Lien démo : /reset-password/demo-token?email=${encodeURIComponent(email)})`);
                res.writeHead(302, { 'Location': `/forgot-password?status=${msg}` });
                return res.end();
            });
            return;
        }
        const status = urlObj.searchParams.get('status') || '';
        return sendCompressedResponse(req, res, 200, { 'Content-Type': 'text/html; charset=utf-8' }, renderForgotPasswordPage(status));
    }

    // Reset Password Submission & Form
    if (pathname.startsWith('/reset-password')) {
        if (req.method === 'POST') {
            let body = '';
            req.on('data', chunk => body += chunk);
            req.on('end', () => {
                const params = new URLSearchParams(body);
                const email = params.get('email') || '';
                console.log(`🔑 [MOT DE PASSE MIS À JOUR] Compte: ${email}`);
                const msg = encodeURIComponent('Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
                res.writeHead(302, { 'Location': `/login?flash=${msg}` });
                return res.end();
            });
            return;
        }
        const email = urlObj.searchParams.get('email') || '';
        const token = pathname.split('/')[2] || 'demo-token';
        return sendCompressedResponse(req, res, 200, { 'Content-Type': 'text/html; charset=utf-8' }, renderResetPasswordPage(token, email));
    }

    // Register Student Account Flow (Creates user in DB and sends confirmation email)
    if (pathname === '/register') {
        if (req.method === 'POST') {
            let body = '';
            req.on('data', chunk => body += chunk);
            req.on('end', () => {
                const params = new URLSearchParams(body);
                const name = (params.get('name') || '').trim();
                const email = (params.get('email') || '').trim();
                const studentId = (params.get('student_id') || '').trim() || `STU-${Math.floor(10000 + Math.random() * 90000)}`;
                const roomNumber = (params.get('room_number') || '').trim() || 'Bât. Omar, Ch. 101';
                const password = params.get('password') || '';

                if (!email || !name) {
                    const msg = encodeURIComponent("Veuillez renseigner votre nom et votre adresse email institutionnelle.");
                    res.writeHead(302, { 'Location': `/register?error=${msg}` });
                    return res.end();
                }

                // Check if email already registered
                const exists = state.users.find(u => u.email.toLowerCase() === email.toLowerCase());
                if (exists) {
                    const msg = encodeURIComponent(`Un compte associé à l'adresse email ${email} existe déjà.`);
                    res.writeHead(302, { 'Location': `/register?error=${msg}` });
                    return res.end();
                }

                // Create new student in database
                const nextId = state.users.length > 0 ? Math.max(...state.users.map(u => u.id || 0)) + 1 : 1;
                const newUser = {
                    id: nextId,
                    name: name,
                    email: email,
                    role: 'student',
                    student_id: studentId,
                    room_number: roomNumber,
                    weeklyUsed: 0,
                    weeklyLimit: 8,
                    credits: 8
                };

                state.users.push(newUser);
                saveUsersToDb(state.users);

                // EMAIL CONFIRMATION DISPATCH (Simulated Mailer logging)
                console.log(`\n======================================================================`);
                console.log(`📧 [EMAIL DE CONFIRMATION ENVOYÉ: CRÉATION DE COMPTE BUANDERIE]`);
                console.log(`======================================================================`);
                console.log(`De: no-reply@ecclaundry.edu (École Centrale Casablanca - Buanderie)`);
                console.log(`À: ${email}`);
                console.log(`Objet: Bienvenue sur le portail Buanderie - Centrale Casablanca`);
                console.log(`Date: ${new Date().toISOString()}`);
                console.log(`----------------------------------------------------------------------`);
                console.log(`Bonjour ${name},`);
                console.log(`\nFélicitations ! Votre compte buanderie a été créé avec succès sur le portail officiel.`);
                console.log(`Informations de votre profil étudiant :`);
                console.log(`  • Nom complet         : ${name}`);
                console.log(`  • Email institutionnel: ${email}`);
                console.log(`  • Numéro Étudiant     : ${studentId}`);
                console.log(`  • Résidence / Chambre : ${roomNumber}`);
                console.log(`  • Quota de bienvenue  : 8 heures de réservation par semaine (1h = 1 crédit)`);
                console.log(`  • Statut              : Compte Actif et Prêt à l'emploi`);
                console.log(`\nVous pouvez dès à présent vous connecter et réserver vos créneaux en ligne.`);
                console.log(`Lien de connexion : http://localhost:${PORT}/login`);
                console.log(`======================================================================\n`);

                const successMsg = encodeURIComponent(`Votre compte a été créé avec succès ! Un e-mail de confirmation et de bienvenue a été envoyé à ${email}.`);
                res.writeHead(302, { 'Location': `/login?flash=${successMsg}` });
                return res.end();
            });
            return;
        }
        const error = urlObj.searchParams.get('error') || '';
        const flash = urlObj.searchParams.get('flash') || '';
        return sendCompressedResponse(req, res, 200, { 'Content-Type': 'text/html; charset=utf-8' }, renderRegisterPage(flash, error));
    }

    // Logout Action (Clears authentication session and redirects with feedback)
    if (pathname === '/logout') {
        state.isAuthenticated = false;
        console.log(`\n🚪 [DÉCONNEXION RÉUSSIE] Session utilisateur clôturée.`);
        const msg = encodeURIComponent('Vous avez été déconnecté avec succès. À bientôt !');
        res.writeHead(302, { 'Location': `/login?flash=${msg}` });
        return res.end();
    }

    // MANDATORY REQUIREMENT: IF NOT AUTHENTICATED, FIRST THING SHOWN IS LOGIN!
    if (!state.isAuthenticated) {
        res.writeHead(302, { 'Location': '/login' });
        return res.end();
    }

    // Reset User Quota Action (Admin only)
    if (pathname === '/reset-user-quota') {
        const id = parseInt(urlObj.searchParams.get('id'), 10);
        const targetUser = state.users.find(u => u.id === id);
        if (targetUser) {
            targetUser.weeklyUsed = 0;
            targetUser.credits = targetUser.weeklyLimit || 8;
            saveUsersToDb(state.users);
            console.log(`\n🔄 [QUOTA RÉINITIALISÉ] ${targetUser.name} (${targetUser.email}) -> Quota remis à zéro (8h disponibles).`);
            const msg = encodeURIComponent(`Quota hebdomadaire réinitialisé avec succès pour ${targetUser.name}.`);
            res.writeHead(302, { 'Location': `/utilisateurs?flash=${msg}` });
            return res.end();
        }
        res.writeHead(302, { 'Location': '/utilisateurs' });
        return res.end();
    }

    // Admin Edit User Action (Save modifications to database/users.json)
    if (pathname === '/admin/users/update' && req.method === 'POST') {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => {
            const params = new URLSearchParams(body);
            const id = parseInt(params.get('id'), 10);
            const targetUser = state.users.find(u => u.id === id);
            if (targetUser) {
                targetUser.name = params.get('name') || targetUser.name;
                targetUser.email = params.get('email') || targetUser.email;
                targetUser.student_id = params.get('student_id') || targetUser.student_id;
                targetUser.room_number = params.get('room_number') || targetUser.room_number;
                targetUser.role = params.get('role') || targetUser.role;
                const newLimit = parseInt(params.get('weeklyLimit'), 10);
                if (!isNaN(newLimit) && newLimit >= 0) {
                    targetUser.weeklyLimit = newLimit;
                }
                const newUsed = parseInt(params.get('weeklyUsed'), 10);
                if (!isNaN(newUsed) && newUsed >= 0) {
                    targetUser.weeklyUsed = newUsed;
                }
                targetUser.credits = Math.max(0, (targetUser.weeklyLimit || 8) - (targetUser.weeklyUsed || 0));
                saveUsersToDb(state.users);
                console.log(`\n✏️ [UTILISATEUR MIS À JOUR] ${targetUser.name} (${targetUser.email}) - Modifié avec succès.`);
                const msg = encodeURIComponent(`Utilisateur ${targetUser.name} mis à jour avec succès.`);
                res.writeHead(302, { 'Location': `/utilisateurs?flash=${msg}` });
                return res.end();
            }
            res.writeHead(302, { 'Location': '/utilisateurs' });
            return res.end();
        });
        return;
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

            // Check quota for both admin (100 credits) and user (8 credits)
            const limit = state.isAdmin ? 100 : 8;
            const remaining = Math.max(0, limit - state.user.weeklyUsed);
            if (hoursCount > remaining) {
                const msg = encodeURIComponent(`Quota insuffisant : Vous avez sélectionné ${hoursCount} créneau(x) mais il ne vous reste que ${remaining} crédit(s) sur vos ${limit} crédits cette semaine.`);
                res.writeHead(302, { 'Location': `/reserver?machine=${machineCode}&flash=${msg}` });
                return res.end();
            }

            // Create reservation for each selected slot on the same machine
            for (const slotHour of selectedHours) {
                const parsed = parseReservationTimes(slotHour, 1);
                const hourPrefix = parsed.formattedTime.split(':')[0].trim() + ' h';
                state.reservations.push({
                    date: bookingDate,
                    hour: hourPrefix,
                    time: parsed.formattedTime,
                    code: machine.code,
                    bg: machine.bg,
                    textColor: machine.text,
                    user: state.isAdmin ? 'El Omari' : 'Alex Rivera',
                    durationHours: parsed.durationMinutes / 60
                });
            }

            state.user.weeklyUsed = Math.min(limit, state.user.weeklyUsed + hoursCount);

            const remainingAfter = Math.max(0, limit - state.user.weeklyUsed);
            const quotaMsg = `(Quota restant : ${remainingAfter} / ${limit} crédits cette semaine)`;
            const slotSummary = selectedHours.join(', ');
            const msg = encodeURIComponent(`Réservation validée pour la machine ${machine.code} (${hoursCount} heure${hoursCount > 1 ? 's' : ''} : ${slotSummary}) le ${bookingDate} ! Décompte : ${hoursCount} heure${hoursCount > 1 ? 's' : ''}. ${quotaMsg}`);

            res.writeHead(302, { 'Location': `/calendrier?flash=${msg}` });
            return res.end();
        });
        return;
    }

    // Dedicated reservation page
    if ((pathname === '/reserver' || pathname === '/bookings/create') && req.method === 'GET') {
        let preselectedMachine = urlObj.searchParams.get('machine') || urlObj.searchParams.get('machine_id') || 'ML1-OM';
        const found = state.machines.find(m => String(m.id) === String(preselectedMachine) || m.code === preselectedMachine);
        if (found) preselectedMachine = found.code;
        return sendCompressedResponse(req, res, 200, { 'Content-Type': 'text/html; charset=utf-8' }, renderLayout('Réserver une machine', renderDedicatedReservationPage(preselectedMachine), '/reserver'));
    }

    // Cancel / Remove reservation action
    if ((pathname === '/bookings/cancel' || (pathname.startsWith('/bookings/') && pathname.endsWith('/cancel')) || (pathname.startsWith('/bookings/') && pathname.endsWith('/delete')) || pathname === '/reservations/cancel') && req.method === 'POST') {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => {
            const params = new URLSearchParams(body);
            const bookingDate = params.get('date');
            const bookingCode = params.get('code');
            const bookingTime = params.get('time');
            const redirectTo = params.get('redirect_to') || '/dashboard';

            // Find matching reservation in state.reservations
            let refundedHours = 1;
            const idx = state.reservations.findIndex(r => {
                if (bookingDate && r.date !== bookingDate) return false;
                if (bookingCode && r.code !== bookingCode) return false;
                if (bookingTime && r.time !== bookingTime) return false;
                return true;
            });

            if (idx !== -1) {
                const removed = state.reservations.splice(idx, 1)[0];
                refundedHours = removed.durationHours || 1;
                state.user.weeklyUsed = Math.max(0, state.user.weeklyUsed - refundedHours);
            }

            const msg = encodeURIComponent(`La réservation a été supprimée avec succès. Votre quota a été restitué (+${refundedHours} crédit${refundedHours > 1 ? 's' : ''}).`);
            const targetUrl = redirectTo.includes('?') ? `${redirectTo}&flash=${msg}` : `${redirectTo}?flash=${msg}`;
            res.writeHead(302, { 'Location': targetUrl });
            return res.end();
        });
        return;
    }

    // Authenticated Routes:
    if (pathname === '/' || pathname === '/calendrier' || pathname === '/admin/reservation/calendrier') {
        const flash = urlObj.searchParams.get('flash') || '';
        const date = urlObj.searchParams.get('date') || '2026-09-30';
        return sendCompressedResponse(req, res, 200, { 'Content-Type': 'text/html; charset=utf-8' }, renderLayout('Calendrier des réservations', renderCalendarPage(date), '/calendrier', flash));
    }

    if (pathname === '/dashboard') {
        const flash = urlObj.searchParams.get('flash') || '';
        return sendCompressedResponse(req, res, 200, { 'Content-Type': 'text/html; charset=utf-8' }, renderLayout('Tableau de bord', renderDashboardPage(), '/dashboard', flash));
    }

    if (pathname === '/utilisateurs') {
        const flash = urlObj.searchParams.get('flash') || '';
        return sendCompressedResponse(req, res, 200, { 'Content-Type': 'text/html; charset=utf-8' }, renderLayout('Gestion des utilisateurs', renderUsersPage(flash), '/utilisateurs', flash));
    }

    // Removed sections: redirect to dashboard
    if (pathname === '/reservations' || pathname === '/machines' || pathname === '/reclamations' || pathname === '/parametres') {
        res.writeHead(302, { 'Location': '/dashboard' });
        return res.end();
    }

    // Fallback redirect to /calendrier
    res.writeHead(302, { 'Location': '/calendrier' });
    res.end();
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`ECC Laundry Centrale Casablanca Server is running at http://localhost:${PORT}`);
});
