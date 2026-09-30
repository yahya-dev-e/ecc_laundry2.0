@props(['endsAt', 'totalMinutes' => 45])

<div x-data="countdown('{{ $endsAt }}')" class="flex items-center space-x-3 bg-slate-950/80 border border-slate-800 rounded-xl px-3 py-2">
    <!-- Spinning drum or progress indicator -->
    <div class="relative w-8 h-8 flex items-center justify-center shrink-0">
        <svg class="w-8 h-8 text-slate-800" viewBox="0 0 36 36">
            <path stroke-dasharray="100, 100" class="text-slate-800" stroke-width="3" stroke="currentColor" fill="none"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
        </svg>
        <span class="absolute inset-0 flex items-center justify-center">
            <span class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-ping"></span>
        </span>
    </div>

    <!-- Time display -->
    <div>
        <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Remaining Time</div>
        <div class="text-sm font-extrabold tracking-tight font-mono text-cyan-400" x-text="formattedTime">
            Calculating...
        </div>
    </div>
</div>
