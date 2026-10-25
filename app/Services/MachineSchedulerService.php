<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\Reservation;
use Carbon\Carbon;

class MachineSchedulerService
{
    public function isMachineAvailableForSlot(Machine $machine, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId = null): bool
    {
        return ! Reservation::occupying()->where('machine_id', $machine->id)
            ->where('start_time', '<', $endTime)->where('end_time', '>', $startTime)
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))->exists();
    }

    public function getAvailableSlots(Machine $machine, Carbon $date): array
    {
        $date = $date->copy()->setTimezone(config('app.timezone'))->startOfDay();
        $reservations = Reservation::occupying()->where('machine_id', $machine->id)
            ->where('start_time', '<', $date->copy()->addDay())->where('end_time', '>', $date)->get(['start_time', 'end_time']);
        $slots = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $start = $date->copy()->addHours($hour);
            $end = $start->copy()->addHour();
            $past = $start->lt(now());
            $free = $machine->status->isOperable() && ! $past
                && ! $reservations->contains(fn ($r) => $r->start_time->lt($end) && $r->end_time->gt($start));
            $slots[] = ['start_time' => $start->format('H:i'), 'end_time' => $hour === 23 ? '24:00' : $end->format('H:i'),
                'datetime' => $start->toIso8601String(), 'is_available' => $free, 'is_past' => $past];
        }

        return $slots;
    }

    public function getMachineMetrics(): array
    {
        $counts = Machine::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status')->all();

        return ['total' => array_sum($counts), 'available' => $counts['available'] ?? 0,
            'in_use' => $counts['in-use'] ?? 0, 'reserved' => $counts['reserved'] ?? 0,
            'under_maintenance' => $counts['under maintenance'] ?? 0, 'out_of_order' => $counts['out of order'] ?? 0];
    }
}
