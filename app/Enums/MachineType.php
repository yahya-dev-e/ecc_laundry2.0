<?php

namespace App\Enums;

enum MachineType: string
{
    case WASHING_MACHINE = 'washing-machine';
    case DRYER           = 'dryer';

    // Backwards compatibility alias
    public const WASHER = self::WASHING_MACHINE;

    public function label(): string
    {
        return match ($this) {
            self::WASHING_MACHINE => 'Washing Machine',
            self::DRYER           => 'Tumble Dryer',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::WASHING_MACHINE => 'Washer',
            self::DRYER           => 'Dryer',
        };
    }

    public function defaultDurationMinutes(): int
    {
        return match ($this) {
            self::WASHING_MACHINE => config('laundry.cycle_durations.washer', 45),
            self::DRYER           => config('laundry.cycle_durations.dryer', 40),
        };
    }

    public function defaultCost(): int
    {
        return match ($this) {
            self::WASHING_MACHINE => config('laundry.credit_costs.washer', 2),
            self::DRYER           => config('laundry.credit_costs.dryer', 2),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::WASHING_MACHINE => 'washer',
            self::DRYER           => 'wind',
        };
    }
}
