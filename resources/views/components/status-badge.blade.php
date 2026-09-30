@props(['status'])

@php
    $statusString = is_string($status) ? $status : ($status->value ?? 'unknown');
    
    $config = match ($statusString) {
        'available'                               => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/30', 'text' => 'text-emerald-400', 'dot' => 'bg-emerald-400', 'label' => 'Available'],
        'reserved'                                => ['bg' => 'bg-sky-500/10', 'border' => 'border-sky-500/30', 'text' => 'text-sky-400', 'dot' => 'bg-sky-400', 'label' => 'Reserved'],
        'in-use', 'in_use'                        => ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/30', 'text' => 'text-amber-400', 'dot' => 'bg-amber-400 animate-ping', 'label' => 'In Use'],
        'under maintenance', 'maintenance'        => ['bg' => 'bg-orange-500/10', 'border' => 'border-orange-500/30', 'text' => 'text-orange-400', 'dot' => 'bg-orange-400', 'label' => 'Under Maintenance'],
        'out of order', 'out_of_order'            => ['bg' => 'bg-rose-500/10', 'border' => 'border-rose-500/30', 'text' => 'text-rose-400', 'dot' => 'bg-rose-400', 'label' => 'Out of Order'],
        'in_progress'                             => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/30', 'text' => 'text-emerald-400', 'dot' => 'bg-emerald-400 animate-pulse', 'label' => 'In Progress'],
        'upcoming'                                => ['bg' => 'bg-cyan-500/10', 'border' => 'border-cyan-500/30', 'text' => 'text-cyan-400', 'dot' => 'bg-cyan-400', 'label' => 'Upcoming'],
        'confirmed'                               => ['bg' => 'bg-cyan-500/10', 'border' => 'border-cyan-500/30', 'text' => 'text-cyan-400', 'dot' => 'bg-cyan-400', 'label' => 'Confirmed'],
        'completed'                               => ['bg' => 'bg-slate-500/10', 'border' => 'border-slate-500/30', 'text' => 'text-slate-400', 'dot' => 'bg-slate-400', 'label' => 'Completed'],
        'cancelled'                               => ['bg' => 'bg-rose-500/10', 'border' => 'border-rose-500/30', 'text' => 'text-rose-400', 'dot' => 'bg-rose-400', 'label' => 'Cancelled'],
        'expired'                                 => ['bg' => 'bg-zinc-500/10', 'border' => 'border-zinc-500/30', 'text' => 'text-zinc-400', 'dot' => 'bg-zinc-400', 'label' => 'Expired'],
        default                                   => ['bg' => 'bg-slate-500/10', 'border' => 'border-slate-500/30', 'text' => 'text-slate-400', 'dot' => 'bg-slate-400', 'label' => ucwords(str_replace(['_', '-'], ' ', $statusString))],
    };
@endphp

<span class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $config['bg'] }} {{ $config['border'] }} {{ $config['text'] }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $config['dot'] }}"></span>
    <span>{{ $config['label'] }}</span>
</span>
