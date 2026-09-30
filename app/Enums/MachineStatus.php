<?php

namespace App\Enums;

enum MachineStatus: string
{
    case AVAILABLE    = 'available';
    case RESERVED     = 'reserved';
    case IN_USE       = 'in_use';
    case MAINTENANCE  = 'maintenance';
    case OUT_OF_ORDER = 'out_of_order';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE    => 'Available',
            self::RESERVED     => 'Reserved',
            self::IN_USE       => 'In Use',
            self::MAINTENANCE  => 'Maintenance',
            self::OUT_OF_ORDER => 'Out of Order',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::AVAILABLE    => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            self::RESERVED     => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
            self::IN_USE       => 'bg-amber-500/10 text-amber-400 border-amber-500/30 animate-pulse',
            self::MAINTENANCE  => 'bg-orange-500/10 text-orange-400 border-orange-500/30',
            self::OUT_OF_ORDER => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::AVAILABLE;
    }

    public function isOperable(): bool
    {
        return !in_array($this, [self::MAINTENANCE, self::OUT_OF_ORDER]);
    }
}
