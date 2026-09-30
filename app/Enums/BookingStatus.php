<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING     = 'pending';
    case CONFIRMED   = 'confirmed';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED   = 'completed';
    case CANCELLED   = 'cancelled';
    case EXPIRED     = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING     => 'Pending',
            self::CONFIRMED   => 'Confirmed',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED   => 'Completed',
            self::CANCELLED   => 'Cancelled',
            self::EXPIRED     => 'Expired',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING     => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30',
            self::CONFIRMED   => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
            self::IN_PROGRESS => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 animate-pulse',
            self::COMPLETED   => 'bg-slate-500/10 text-slate-300 border-slate-500/30',
            self::CANCELLED   => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
            self::EXPIRED     => 'bg-gray-500/10 text-gray-400 border-gray-500/30',
        };
    }

    public function canBeStarted(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED]);
    }

    public function isActive(): bool
    {
        return $this === self::IN_PROGRESS;
    }
}
