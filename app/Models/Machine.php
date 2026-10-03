<?php

namespace App\Models;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Machine extends Model
{
    use HasFactory;

    /**
     * Exact MySQL table name.
     *
     * @var string
     */
    protected $table = 'machines';

    /**
     * The attributes that are mass assignable.
     * Strictly matching frozen MySQL schema columns:
     * id, name, type, status, color, created_at, updated_at
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'status',
        'color',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MachineType::class,
            'status' => MachineStatus::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Active booking currently running on this machine (time-derived).
     */
    public function activeBooking(): HasOne
    {
        $now = Carbon::now();
        return $this->hasOne(Reservation::class)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->latestOfMany('start_time');
    }

    /*
    |--------------------------------------------------------------------------
    | Status Check Helpers
    |--------------------------------------------------------------------------
    */

    public function isAvailable(): bool
    {
        return $this->status === MachineStatus::AVAILABLE;
    }

    public function isInUse(): bool
    {
        return $this->status === MachineStatus::IN_USE;
    }

    public function isReserved(): bool
    {
        return $this->status === MachineStatus::RESERVED;
    }

    /*
    |--------------------------------------------------------------------------
    | Dynamic Accessors for Business Logic & Backward Compatibility
    |--------------------------------------------------------------------------
    */

    /**
     * Curated, accessible color palette for machines (neither too bright/neon nor too dull/dark).
     * Automatically maps machine names and converts legacy overly bright database colors.
     */
    public function getColorAttribute(?string $value): string
    {
        $palette = [
            'ML1-OM' => '#4338ca', // Indigo
            'ML2-OM' => '#0d9488', // Teal
            'ML1-PE' => '#2563eb', // Blue
            'ML2-PE' => '#d97706', // Warm Amber (replaces unreadable neon yellow)
            'ML3-PE' => '#db2777', // Rose/Pink (replaces neon magenta)
            'ML4-PE' => '#ea580c', // Orange
            'ML3-OM' => '#059669', // Emerald (replaces dull dark teal)
            'SL1-OM' => '#b45309', // Amber Brown
            'SL2-OM' => '#16a34a', // Green
            'SL1-PE' => '#475569', // Slate
            'SL2-PE' => '#65a30d', // Lime
            'SL3-PE' => '#9333ea', // Purple
            'SL3-OM' => '#52525b', // Zinc
        ];

        if (!empty($this->name) && isset($palette[$this->name])) {
            return $palette[$this->name];
        }

        // Map legacy overly bright hex colors to balanced modern tones
        $legacyMap = [
            '#e53935' => '#4338ca',
            '#00e676' => '#0d9488',
            '#2979ff' => '#2563eb',
            '#ffd600' => '#d97706',
            '#ff007f' => '#db2777',
            '#ff9100' => '#ea580c',
            '#004d40' => '#059669',
            '#4e342e' => '#b45309',
            '#4caf50' => '#16a34a',
            '#1a237e' => '#475569',
            '#827717' => '#65a30d',
            '#8e24aa' => '#9333ea',
            '#212121' => '#52525b',
            '#ff0000' => '#4338ca',
            '#00ff00' => '#0d9488',
            '#0000ff' => '#2563eb',
            '#ffff00' => '#d97706',
        ];

        if ($value && isset($legacyMap[strtolower($value)])) {
            return $legacyMap[strtolower($value)];
        }

        return $value ?: '#4338ca';
    }

    /**
     * Alias code to name so any legacy call to $machine->code safely returns name.
     */
    public function getCodeAttribute(): string
    {
        return $this->name;
    }

    /**
     * Dynamic cycle duration based on machine type.
     */
    public function getDefaultDurationMinutesAttribute(): int
    {
        return $this->type?->defaultDurationMinutes() ?? 45;
    }

    /**
     * Dynamic cycle credit cost based on machine type.
     */
    public function getCostPerCycleAttribute(): int
    {
        return $this->type?->defaultCost() ?? 2;
    }

    /**
     * Default load capacity in kg.
     */
    public function getCapacityKgAttribute(): float
    {
        return $this->type === MachineType::DRYER ? 8.5 : 8.0;
    }

    /**
     * Dynamically derive current cycle end time from active reservation.
     */
    public function getCurrentCycleEndsAtAttribute(): ?Carbon
    {
        return $this->activeBooking?->end_time;
    }

    /**
     * Dynamically calculate cycle progress percentage from active reservation.
     */
    public function cycleProgressPercentage(): int
    {
        if (!$this->isInUse()) {
            return 0;
        }

        $active = $this->activeBooking;
        if ($active && $active->start_time && $active->end_time) {
            $totalSeconds = $active->start_time->diffInSeconds($active->end_time);
            if ($totalSeconds <= 0) {
                return 0;
            }
            $elapsed = Carbon::now()->diffInSeconds($active->start_time);
            $percentage = (int) round(($elapsed / $totalSeconds) * 100);
            return min(100, max(0, $percentage));
        }

        return 0;
    }
}
