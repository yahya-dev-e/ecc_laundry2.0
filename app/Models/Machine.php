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

    protected $fillable = [
        'name',
        'code',
        'type',
        'status',
        'capacity_kg',
        'cost_per_cycle',
        'default_duration_minutes',
        'location',
        'current_cycle_ends_at',
        'last_maintenance_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => MachineType::class,
            'status' => MachineStatus::class,
            'capacity_kg' => 'decimal:1',
            'cost_per_cycle' => 'integer',
            'default_duration_minutes' => 'integer',
            'current_cycle_ends_at' => 'datetime',
            'last_maintenance_at' => 'datetime',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function activeBooking(): HasOne
    {
        $now = Carbon::now();
        return $this->hasOne(Reservation::class)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->latestOfMany('start_time');
    }

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

    public function remainingCycleSeconds(): int
    {
        if (!$this->current_cycle_ends_at) {
            return 0;
        }

        $diff = Carbon::now()->diffInSeconds($this->current_cycle_ends_at, false);
        return max(0, (int) $diff);
    }

    public function cycleProgressPercentage(): int
    {
        if (!$this->isInUse() || !$this->current_cycle_ends_at) {
            return 0;
        }

        $totalSeconds = $this->default_duration_minutes * 60;
        $remaining = $this->remainingCycleSeconds();
        $elapsed = max(0, $totalSeconds - $remaining);

        $percentage = (int) round(($elapsed / $totalSeconds) * 100);
        return min(100, max(0, $percentage));
    }
}
