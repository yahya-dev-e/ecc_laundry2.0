import http from 'http';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const PORT = 3000;

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

// In-memory state with weekly reservation quota
const state = {
    isAuthenticated: false, // FIRST SCREEN IS LOGIN
    isAdmin: true, // Role switcher for testing
    weeklyLimit: 3, // 3 reservations per week limit
    user: {
        name: 'R. Omari',
        email: 'r.omari@fecc.ma',
        weeklyUsed: 1, // 1 used out of 3
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
        { hour: '00 h', time: '0:00 - 1:00', code: 'ML2-OM', bg: '#00e676', textColor: 'text-slate-900', user: 'Alex Rivera' },
        { hour: '06 h', time: '6:00 - 7:00', code: 'SL1-PE', bg: '#1a237e', textColor: 'text-white', user: 'Youssef Alami' },
        { 
            hour: '07 h', 
            multi: [
                { time: '7:00 - 9:00', code: 'ML2-PE', bg: '#ffd600', textColor: 'text-slate-900', user: 'Sara Bennani' },
                { time: '7:00 - 9:00', code: 'ML3-PE', bg: '#ff007f', textColor: 'text-white', user: 'Mehdi Tazi' },
                { time: '7:00 - 9:00', code: 'ML2-OM', bg: '#00e676', textColor: 'text-slate-900', user: 'Sara Bennani' }
            ]
        }
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
                
                <!-- Interactive Role Switcher -->
                <a href="/toggle-role" class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition-all ${state.isAdmin ? 'bg-amber-50 text-amber-800 border-amber-300' : 'bg-blue-50 text-blue-800 border-blue-300'}" title="Cliquez pour basculer entre vue Administrateur et vue Étudiant">
                    ${state.isAdmin ? '👑 Mode: ADMIN (Cliquez pour tester vue Étudiant)' : '🎓 Mode: ÉTUDIANT (Cliquez pour tester vue Admin)'}
                </a>
            </div>

            <div class="flex items-center space-x-5">
                <!-- Weekly Quota Badge (NO CREDITS!) -->
                <div class="flex items-center space-x-2 px-3 py-1 rounded-full ${state.isAdmin ? 'bg-amber-50 text-amber-900 border border-amber-200' : (remaining > 0 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200')} text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full ${state.isAdmin ? 'bg-amber-500' : (remaining > 0 ? 'bg-emerald-500' : 'bg-rose-500')}"></span>
                    <span>
                        ${state.isAdmin ? 'Quota : Illimité (Admin)' : `Quota : ${state.user.weeklyUsed} / ${state.weeklyLimit} cette semaine (${remaining} restante${remaining > 1 ? 's' : ''})`}
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
</body>
</html>`;
}

// 1. Calendrier Page (Image 2) with fixed interactive modal
function renderCalendarPage() {
    const washers = state.machines.filter(m => m.type === 'washer');
    const dryers = state.machines.filter(m => m.type === 'dryer');
    const hours = ['00 h', '01 h', '02 h', '03 h', '04 h', '05 h', '06 h', '07 h', '08 h', '09 h', '10 h', '11 h', '12 h', '13 h', '14 h', '15 h', '16 h', '17 h', '18 h', '19 h', '20 h', '21 h', '22 h', '23 h'];
    const remaining = Math.max(0, state.weeklyLimit - state.user.weeklyUsed);

    return `
    <div class="space-y-6 max-w-6xl mx-auto" x-data="{
        selectedMachine: 'ML1-OM',
        showModal: false,
        modalMachine: 'ML1-OM',
        modalDate: '2026-09-30',
        modalHour: '14:00',
        openReservation(code) {
            this.modalMachine = code || this.selectedMachine || 'ML1-OM';
            this.showModal = true;
        }
    }">
        <!-- Green Info Banner matching Image 2 with weekly quota counter -->
        <div class="bg-white border-l-4 border-[#00897b] p-3.5 rounded shadow-xs flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-5 h-5 rounded-full bg-[#00897b]/10 text-[#00897b] flex items-center justify-center font-bold text-xs shrink-0">i</div>
                <span class="text-xs text-slate-700">Cliquez sur une machine pour voir les créneaux déjà réservés.</span>
            </div>
            <div class="text-xs font-semibold text-[#00897b]">
                ${state.isAdmin ? 'Régime Administrateur (Illimité)' : `Quota restant : ${remaining} sur ${state.weeklyLimit} cette semaine`}
            </div>
        </div>

        <!-- Search Bar matching Image 2 -->
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
                <!-- Washers List -->
                <div class="grid grid-cols-3 gap-2 pr-3">
                    ${washers.map(m => `
                        <button type="button" @click="selectedMachine = '${m.code}'" 
                                :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === '${m.code}'}"
                                style="background-color: ${m.bg};"
                                class="badge-machine ${m.text}">
                            <span>${m.code}</span>
                            <span class="text-sm">${m.icon}</span>
                        </button>
                    `).join('')}
                </div>

                <!-- Dryers List -->
                <div class="grid grid-cols-2 gap-2 pl-3">
                    ${dryers.map(m => `
                        <button type="button" @click="selectedMachine = '${m.code}'"
                                :class="{'ring-2 ring-slate-900 scale-105 shadow-md': selectedMachine === '${m.code}'}"
                                style="background-color: ${m.bg};"
                                class="badge-machine ${m.text}">
                            <span>${m.code}</span>
                            <span class="text-xs">${m.icon}</span>
                        </button>
                    `).join('')}
                </div>
            </div>
        </div>

        <!-- Action Button Réserver (OPENS MODAL DIRECTLY - NO SHUFFLE!) -->
        <div class="flex justify-between items-center max-w-2xl mx-auto">
            <div class="text-xs text-slate-500">
                Machine sélectionnée : <span class="font-bold text-[#00897b]" x-text="selectedMachine"></span>
            </div>
            <button type="button" @click="openReservation(selectedMachine)" 
                    class="px-6 py-2 rounded bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold transition-all shadow-xs cursor-pointer active:scale-95">
                Réserver
            </button>
        </div>

        <!-- Date Header & Navigation matching Image 2 -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-4 border-t border-slate-200">
            <h2 class="text-xl font-normal text-slate-700">30 septembre 2026</h2>
            <div class="inline-flex rounded shadow-xs text-xs">
                <button class="px-3.5 py-1.5 bg-[#546e7a] hover:bg-[#455a64] text-white font-medium rounded-l">Aujourd'hui</button>
                <button class="px-3.5 py-1.5 bg-[#37474f] hover:bg-[#263238] text-white font-medium">Précédent</button>
                <button class="px-3.5 py-1.5 bg-[#263238] hover:bg-black text-white font-medium rounded-r">Suivant</button>
            </div>
        </div>

        <!-- Timetable Grid matching Image 2 -->
        <div class="bg-white rounded-lg border border-slate-300 shadow-xs overflow-hidden">
            <div class="flex border-b border-slate-300 bg-[#f9f9e8] text-xs font-semibold text-slate-700">
                <div class="w-16 p-2 text-center border-r border-slate-300 text-[11px] text-slate-500">Toute la journée</div>
                <div class="flex-1 p-2 text-center font-bold text-slate-800">mercredi</div>
            </div>

            <div class="divide-y divide-slate-200 text-xs">
                ${hours.map(h => {
                    const res = state.reservations.find(r => r.hour === h);
                    if (res && res.multi) {
                        return `
                        <div class="flex items-center h-10 hover:bg-slate-50/50">
                            <div class="timeline-hour">${h}</div>
                            <div class="flex-1 px-1 h-full flex items-center space-x-1">
                                ${res.multi.map(slot => `
                                    <div style="background-color: ${slot.bg};" class="flex-1 h-7 rounded ${slot.textColor} font-bold text-[11px] px-2 flex items-center truncate shadow-xs">
                                        ${slot.time} • ${slot.code}
                                    </div>
                                `).join('')}
                            </div>
                        </div>`;
                    }
                    if (res) {
                        return `
                        <div class="flex items-center h-10 hover:bg-slate-50/50">
                            <div class="timeline-hour">${h}</div>
                            <div class="flex-1 px-1 h-full flex items-center">
                                <div style="background-color: ${res.bg};" class="w-full h-7 rounded ${res.textColor} font-bold text-[11px] px-3 flex items-center shadow-xs">
                                    ${res.time} • ${res.code}
                                </div>
                            </div>
                        </div>`;
                    }
                    return `
                    <div class="flex items-center h-9 hover:bg-slate-50/50">
                        <div class="timeline-hour">${h}</div>
                        <div class="flex-1 h-full border-t border-dashed border-slate-200"></div>
                    </div>`;
                }).join('')}
            </div>
        </div>

        <!-- MODAL DE RÉSERVATION (FIX FOR THE SHUFFLE ISSUE) -->
        <div x-show="showModal" style="display: none;" 
             class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            
            <div @click.away="showModal = false" 
                 class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-150">
                
                <div class="bg-[#00897b] px-5 py-4 text-white flex items-center justify-between">
                    <span class="text-base font-bold">Réserver une machine</span>
                    <button type="button" @click="showModal = false" class="text-white/80 hover:text-white text-xl font-bold cursor-pointer">
                        &times;
                    </button>
                </div>

                <form method="POST" action="/reserver" class="p-6 space-y-4">
                    <!-- Machine Choice -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Sélectionner la machine
                        </label>
                        <select name="machine" x-model="modalMachine" 
                                class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:border-[#00897b] focus:outline-none bg-slate-50">
                            <optgroup label="Machines à laver">
                                ${washers.map(w => `<option value="${w.code}">${w.code} (${w.name} - ${w.cap})</option>`).join('')}
                            </optgroup>
                            <optgroup label="Sèche-linge">
                                ${dryers.map(d => `<option value="${d.code}">${d.code} (${d.name} - ${d.cap})</option>`).join('')}
                            </optgroup>
                        </select>
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Date
                        </label>
                        <input type="date" name="date" x-model="modalDate" value="2026-09-30" 
                               class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:border-[#00897b] focus:outline-none">
                    </div>

                    <!-- Hour Slot -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Créneau horaire
                        </label>
                        <select name="hour" x-model="modalHour" 
                                class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:border-[#00897b] focus:outline-none bg-slate-50">
                            <option value="08:00 - 09:00">08:00 - 09:00</option>
                            <option value="09:00 - 10:00">09:00 - 10:00</option>
                            <option value="10:00 - 11:00">10:00 - 11:00</option>
                            <option value="11:00 - 12:00">11:00 - 12:00</option>
                            <option value="13:00 - 14:00">13:00 - 14:00</option>
                            <option value="14:00 - 15:00" selected>14:00 - 15:00</option>
                            <option value="15:00 - 16:00">15:00 - 16:00</option>
                            <option value="16:00 - 17:00">16:00 - 17:00</option>
                            <option value="17:00 - 18:00">17:00 - 18:00</option>
                            <option value="18:00 - 19:00">18:00 - 19:00</option>
                            <option value="19:00 - 20:00">19:00 - 20:00</option>
                            <option value="20:00 - 21:00">20:00 - 21:00</option>
                        </select>
                    </div>

                    <!-- Quota Notice (NO CREDITS!) -->
                    <div class="p-3 rounded bg-emerald-50 border border-emerald-200 text-xs space-y-1">
                        <div class="flex items-center justify-between font-semibold text-emerald-900">
                            <span>Quota hebdomadaire :</span>
                            <span>${state.isAdmin ? 'Illimité (Admin)' : `${remaining} réservation(s) restante(s) sur ${state.weeklyLimit}`}</span>
                        </div>
                        <p class="text-[11px] text-emerald-700">
                            ${state.isAdmin ? 'En tant qu\'administrateur, vos réservations ne sont pas décomptées.' : 'Cette réservation sera décomptée de votre quota de 3 réservations autorisées cette semaine.'}
                        </p>
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-3">
                        <button type="button" @click="showModal = false" 
                                class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded">
                            Annuler
                        </button>
                        <button type="submit" 
                                class="px-5 py-2 bg-[#00897b] hover:bg-[#00796b] text-white text-xs font-bold rounded shadow-xs cursor-pointer">
                            Confirmer la réservation
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>`;
}

// 2. Tableau de bord Page (NO CREDITS)
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
                    ${state.isAdmin ? 'Illimité' : `${remaining} / ${state.weeklyLimit}`}
                </p>
                <span class="text-[11px] text-slate-500">${state.isAdmin ? 'Admin' : 'Réservations restantes'}</span>
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

// 3. Réservations Page (NO CREDITS)
function renderReservationsPage() {
    return `
    <div class="space-y-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Gestion des Réservations</h1>
                <p class="text-xs text-slate-500">Historique et créneaux planifiés cette semaine</p>
            </div>
            <a href="/calendrier" class="px-4 py-2 bg-[#00897b] text-white rounded text-xs font-bold shadow-xs">Réserver sur le calendrier</a>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 shadow-xs overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#00897b] text-white font-bold">
                    <tr>
                        <th class="p-3.5">Machine</th>
                        <th class="p-3.5">Bénéficiaire</th>
                        <th class="p-3.5">Créneau</th>
                        <th class="p-3.5">Décompte Quota</th>
                        <th class="p-3.5">Statut</th>
                        <th class="p-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="p-3.5 font-bold text-[#e53935]">ML1-OM (Lave-linge)</td>
                        <td class="p-3.5">Alex Rivera (STU-98241)</td>
                        <td class="p-3.5">30 sept 2026 • 00:00 - 01:00</td>
                        <td class="p-3.5 text-slate-600 font-semibold">1 rés. semaine</td>
                        <td class="p-3.5"><span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">Actif</span></td>
                        <td class="p-3.5 text-right"><button class="text-rose-600 hover:underline font-semibold">Annuler</button></td>
                    </tr>
                    <tr>
                        <td class="p-3.5 font-bold text-[#ffd600] text-slate-900">ML2-PE (Lave-linge)</td>
                        <td class="p-3.5">Sara Bennani (STU-88219)</td>
                        <td class="p-3.5">30 sept 2026 • 07:00 - 09:00</td>
                        <td class="p-3.5 text-slate-600 font-semibold">1 rés. semaine</td>
                        <td class="p-3.5"><span class="px-2 py-0.5 rounded bg-sky-100 text-sky-800 font-bold text-[10px]">Confirmé</span></td>
                        <td class="p-3.5 text-right"><button class="text-rose-600 hover:underline font-semibold">Annuler</button></td>
                    </tr>
                    <tr>
                        <td class="p-3.5 font-bold text-[#1a237e] text-white">SL1-PE (Sèche-linge)</td>
                        <td class="p-3.5">Youssef Alami (STU-99014)</td>
                        <td class="p-3.5">30 sept 2026 • 06:00 - 07:00</td>
                        <td class="p-3.5 text-slate-600 font-semibold">1 rés. semaine</td>
                        <td class="p-3.5"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">En cours</span></td>
                        <td class="p-3.5 text-right"><span class="text-slate-400">Verrouillé</span></td>
                    </tr>
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

// 5. Gestion des utilisateurs (ADMIN ONLY - NO CREDITS)
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
                        <td class="p-3.5 font-bold text-emerald-600">${state.user.weeklyUsed} / ${state.weeklyLimit} utilisée (${state.weeklyLimit - state.user.weeklyUsed} restantes)</td>
                        <td class="p-3.5 text-right space-x-2"><a href="/reset-quota" class="text-[#00897b] hover:underline font-semibold">Réinitialiser quota</a></td>
                    </tr>
                    <tr>
                        <td class="p-3.5 font-bold text-slate-800">Sara Bennani</td>
                        <td class="p-3.5 text-slate-600">sara.bennani@fecc.ma</td>
                        <td class="p-3.5 text-slate-500 font-mono">STU-88219 (Bât. Petit, Ch. 108)</td>
                        <td class="p-3.5"><span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">Étudiant</span></td>
                        <td class="p-3.5 font-bold text-amber-600">2 / 3 utilisées (1 restante)</td>
                        <td class="p-3.5 text-right space-x-2"><button class="text-[#00897b] hover:underline font-semibold">Réinitialiser quota</button></td>
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

// 7. Paramètres Page (ADMIN ONLY - NO CREDITS)
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
                        <label class="block font-semibold text-slate-700 mb-1">Nombre maximal de réservations par semaine</label>
                        <div class="flex items-center space-x-2"><input type="number" value="${state.weeklyLimit}" class="w-24 px-3 py-2 border border-slate-300 rounded"><span class="text-slate-500">réservations / étudiant</span></div>
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

    // HANDLE RESERVATION ACTION (NO PAGE SHUFFLE!)
    if (pathname === '/reserver' && req.method === 'POST') {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => {
            const params = new URLSearchParams(body);
            const machineCode = params.get('machine') || 'ML1-OM';
            const slotHour = params.get('hour') || '14:00 - 15:00';
            const machine = state.machines.find(m => m.code === machineCode) || state.machines[0];

            // Parse hour for schedule table (e.g. "14:00 - 15:00" -> "14 h")
            const hourPrefix = slotHour.split(':')[0] + ' h';

            // Add reservation to calendar timetable
            state.reservations.push({
                hour: hourPrefix,
                time: slotHour,
                code: machine.code,
                bg: machine.bg,
                textColor: machine.text,
                user: state.isAdmin ? 'R. Omari' : 'Alex Rivera'
            });

            // Increment weekly quota if student
            if (!state.isAdmin) {
                state.user.weeklyUsed = Math.min(state.weeklyLimit, state.user.weeklyUsed + 1);
            }

            const remaining = Math.max(0, state.weeklyLimit - state.user.weeklyUsed);
            const quotaMsg = state.isAdmin ? '(Quota Illimité - Admin)' : `(Quota restant : ${remaining}/${state.weeklyLimit} cette semaine)`;
            const msg = encodeURIComponent(`Réservation confirmée pour la machine ${machine.code} (${slotHour}) ! ${quotaMsg}`);

            res.writeHead(302, { 'Location': `/calendrier?flash=${msg}` });
            return res.end();
        });
        return;
    }

    // Authenticated Routes:
    if (pathname === '/' || pathname === '/calendrier' || pathname === '/admin/reservation/calendrier') {
        const flash = urlObj.searchParams.get('flash') || '';
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(renderLayout('Calendrier des réservations', renderCalendarPage(), '/calendrier', flash));
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
