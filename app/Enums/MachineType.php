<?php

namespace App\Enums;

enum MachineType: string
{
    case WASHER = 'washer';
    case DRYER  = 'dryer';

    public function label(): string
    {
        return match ($this) {
            self::WASHER => 'Washing Machine',
            self::DRYER  => 'Tumble Dryer',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::WASHER => 'Washer',
            self::DRYER  => 'Dryer',
        };
    }

    public function defaultDurationMinutes(): int
    {
        return match ($this) {
            self::WASHER => config('laundry.cycle_durations.washer', 45),
            self::DRYER  => config('laundry.cycle_durations.dryer', 40),
        };
    }

    public function defaultCost(): int
    {
        return match ($this) {
            self::WASHER => config('laundry.credit_costs.washer', 2),
            self::DRYER  => config('laundry.credit_costs.dryer', 2),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::WASHER => 'washer',
            self::DRYER  => 'wind',
        };
    }
}
