<?php

namespace App\Enums;

enum MachineType: string
{
    case WASHING_MACHINE = 'washing-machine';
    case DRYER = 'dryer';

    // Backwards compatibility alias
    public const WASHER = self::WASHING_MACHINE;

    public function label(): string
    {
        return match ($this) {
            self::WASHING_MACHINE => 'Washing Machine',
            self::DRYER => 'Tumble Dryer',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::WASHING_MACHINE => 'Washer',
            self::DRYER => 'Dryer',
        };
    }

    public function defaultDurationMinutes(): int
    {
        return 60; // The standard browser reservation is one hour.
    }

    public function defaultCost(): int
    {
        return 1; // One quota hour; variable-duration API requests use exact minutes.
    }

    public function icon(): string
    {
        return match ($this) {
            self::WASHING_MACHINE => 'washer',
            self::DRYER => 'wind',
        };
    }
}
