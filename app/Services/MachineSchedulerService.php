<?php

namespace App\Services;

use App\Enums\MachineStatus;
use App\Models\Machine;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MachineSchedulerService
{
    /**
     * Determine if a machine is free from conflicting reservations during [start, end].
     * PURELY TIME-BASED (NO 'status' COLUMN QUERIES!)
     */
    public function isMachineAvailableForSlot(Machine $machine, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId = null): bool
    {
        $query = Reservation::where('machine_id', $machine->id)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return !$query->exists();
    }

    /**
     * Generate list of available time slots for a specific machine on a given date.
     */
    public function getAvailableSlots(Machine $machine, Carbon $date): array
    {
        $duration = $machine->default_duration_minutes;
        $cursor = (clone $date)->startOfDay();
        $closingTime = (clone $date)->startOfDay()->addDay();

        $slots = [];

        while ((clone $cursor)->addMinutes($duration)->lessThanOrEqualTo($closingTime)) {
            $slotEnd = (clone $cursor)->addMinutes($duration);

            $isPast = $cursor->isPast();
            $isFree = !$isPast && $this->isMachineAvailableForSlot($machine, $cursor, $slotEnd);

            $slots[] = [
                'start_time' => $cursor->format('H:i'),
                'end_time'   => $slotEnd->format('H:i'),
                'datetime'   => $cursor->toIso8601String(),
                'is_available' => $isFree,
                'is_past'    => $isPast,
            ];

            // Buffer between slots
            $cursor->addMinutes($duration);
        }

        return $slots;
    }

    /**
     * Retrieve system-wide machine status metrics.
     */
    public function getMachineMetrics(): array
    {
        $counts = Machine::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalWashers = Machine::where('type', 'washing-machine')->count();
        $totalDryers = Machine::where('type', 'dryer')->count();

        $activeWashers = Machine::where('type', 'washing-machine')->where('status', MachineStatus::AVAILABLE)->count();
        $activeDryers = Machine::where('type', 'dryer')->where('status', MachineStatus::AVAILABLE)->count();

        return [
            'total' => array_sum($counts),
            'available' => $counts[MachineStatus::AVAILABLE->value] ?? 0,
            'in_use' => $counts[MachineStatus::IN_USE->value] ?? 0,
            'reserved' => $counts[MachineStatus::RESERVED->value] ?? 0,
            'under_maintenance' => $counts[MachineStatus::UNDER_MAINTENANCE->value] ?? 0,
            'out_of_order' => $counts[MachineStatus::OUT_OF_ORDER->value] ?? 0,
            'washers_available_ratio' => "{$activeWashers}/{$totalWashers}",
            'dryers_available_ratio' => "{$activeDryers}/{$totalDryers}",
        ];
    }
}
