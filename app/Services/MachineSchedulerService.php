<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\MachineStatus;
use App\Models\Booking;
use App\Models\Machine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MachineSchedulerService
{
    /**
     * Determine if a machine is free from conflicting reservations during [start, end].
     */
    public function isMachineAvailableForSlot(Machine $machine, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId = null): bool
    {
        $query = Booking::where('machine_id', $machine->id)
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED, BookingStatus::IN_PROGRESS])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                  ->orWhereBetween('end_time', [$startTime, $endTime])
                  ->orWhere(function ($sub) use ($startTime, $endTime) {
                      $sub->where('start_time', '<=', $startTime)
                          ->where('end_time', '>=', $endTime);
                  });
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
        $operatingStart = config('laundry.operating_hours.start', '06:00');
        $operatingEnd = config('laundry.operating_hours.end', '23:30');

        [$startHour, $startMinute] = explode(':', $operatingStart);
        [$endHour, $endMinute] = explode(':', $operatingEnd);

        $cursor = (clone $date)->setTime((int) $startHour, (int) $startMinute, 0);
        $closingTime = (clone $date)->setTime((int) $endHour, (int) $endMinute, 0);

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

            // 10-minute turnaround buffer between slots
            $cursor->addMinutes($duration + 10);
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

        $totalWashers = Machine::where('type', 'washer')->count();
        $totalDryers = Machine::where('type', 'dryer')->count();

        $activeWashers = Machine::where('type', 'washer')->where('status', MachineStatus::AVAILABLE)->count();
        $activeDryers = Machine::where('type', 'dryer')->where('status', MachineStatus::AVAILABLE)->count();

        return [
            'total' => array_sum($counts),
            'available' => $counts[MachineStatus::AVAILABLE->value] ?? 0,
            'in_use' => $counts[MachineStatus::IN_USE->value] ?? 0,
            'reserved' => $counts[MachineStatus::RESERVED->value] ?? 0,
            'maintenance' => $counts[MachineStatus::MAINTENANCE->value] ?? 0,
            'out_of_order' => $counts[MachineStatus::OUT_OF_ORDER->value] ?? 0,
            'washers_available_ratio' => "{$activeWashers}/{$totalWashers}",
            'dryers_available_ratio' => "{$activeDryers}/{$totalDryers}",
        ];
    }
}
