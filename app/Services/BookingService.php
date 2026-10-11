<?php

namespace App\Services;

use App\Enums\MachineStatus;
use App\Events\CycleCompleted;
use App\Events\CycleStarted;
use App\Models\Machine;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingService
{
    public function __construct(protected MachineSchedulerService $scheduler) {}

    public function createBooking(User $user, Machine $machine, Carbon $startTime, ?int $durationMinutes = null): Reservation
    {
        return $this->createBookings($user, $machine, [$startTime], $durationMinutes)[0];
    }

    public function createBookings(User $user, Machine $machine, array $starts, ?int $durationMinutes = null): array
    {
        return DB::transaction(function () use ($user, $machine, $starts, $durationMinutes) {
            // All writers take user then machine locks in this order. SQLite serializes writers;
            // MySQL/Postgres acquire row locks, including availability/quota rechecks below.
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $machine = Machine::whereKey($machine->id)->lockForUpdate()->firstOrFail();
            $duration = $durationMinutes ?? $machine->default_duration_minutes;
            if ($duration < 20 || $duration > 120 || ! $starts || count($starts) > 24) {
                throw new InvalidArgumentException('Sélection de créneaux invalide.');
            }
            if (! $machine->status->isOperable()) {
                throw new InvalidArgumentException('Cette machine est hors service.');
            }
            $times = array_map(fn ($t) => Carbon::parse($t)->setTimezone(config('app.timezone')), $starts);
            usort($times, fn ($a, $b) => $a->getTimestamp() <=> $b->getTimestamp());
            $max = BookingWindow::end();
            $previousEnd = null;
            foreach ($times as $start) {
                $end = $start->copy()->addMinutes($duration);
                if ($start->lt(now()) || $end->gt($max)) {
                    throw new InvalidArgumentException('Ce créneau est passé ou hors de la période de réservation.');
                }
                if (($previousEnd && $start->lt($previousEnd)) || ! $this->scheduler->isMachineAvailableForSlot($machine, $start, $end)) {
                    throw new InvalidArgumentException('Un créneau sélectionné est déjà réservé.');
                }
                $previousEnd = $end;
            }
            foreach (collect($times)->groupBy(fn ($time) => $time->copy()->startOfWeek()->toDateString()) as $week => $weekTimes) {
                if ($user->weeklyRemainingMinutes(Carbon::parse($week)) < $weekTimes->count() * $duration) {
                    throw new InvalidArgumentException('Quota hebdomadaire insuffisant.');
                }
            }
            $activeCount = $user->reservations()->activeOrUpcoming()->count();
            if ($activeCount + count($times) > config('laundry.max_simultaneous_reservations', 3)) {
                throw new InvalidArgumentException('Vous avez atteint le nombre maximal de réservations actives.');
            }
            $bookings = [];
            foreach ($times as $start) {
                $booking = Reservation::create([
                    'user_id' => $user->id, 'machine_id' => $machine->id, 'start_time' => $start,
                    'end_time' => $start->copy()->addMinutes($duration), 'quota_minutes' => $duration,
                    'weekly_session_limit_remaining' => (int) floor($user->weeklyRemainingMinutes($start) / 60),
                ]);
                Transaction::create(['user_id' => $user->id, 'booking_id' => $booking->id,
                    'amount' => -$duration, 'type' => 'booking_charge', 'event_key' => 'charge:'.$booking->id,
                    'description' => 'Réservation de '.$machine->name.' (minutes)']);
                $bookings[] = $booking;
            }

            return $bookings;
        }, 5);
    }

    public function startCycle(Reservation $booking): Machine
    {
        return DB::transaction(function () use ($booking) {
            User::whereKey($booking->user_id)->lockForUpdate()->firstOrFail();
            $machine = Machine::whereKey($booking->machine_id)->lockForUpdate()->firstOrFail();
            $booking = Reservation::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (! $booking->canBeStarted() || ! $machine->status->isOperable() || $machine->active_reservation_id || $machine->isInUse()) {
                throw new InvalidArgumentException('Le cycle ne peut pas être démarré : vérifiez le créneau et l’état de la machine.');
            }
            $booking->update(['started_at' => now()]);
            $machine->update(['status' => MachineStatus::IN_USE, 'active_reservation_id' => $booking->id]);
            DB::afterCommit(fn () => event(new CycleStarted($machine->fresh(), $booking->fresh())));

            return $machine;
        }, 5);
    }

    public function completeCycle(Machine $machine): void
    {
        DB::transaction(function () use ($machine) {
            $machine = Machine::whereKey($machine->id)->lockForUpdate()->firstOrFail();
            if (! $machine->active_reservation_id) {
                return;
            }
            $booking = Reservation::whereKey($machine->active_reservation_id)->lockForUpdate()->first();
            if (! $booking || $booking->completed_at || ! $booking->started_at || $booking->end_time->isFuture()) {
                return;
            }
            $booking->update(['completed_at' => now()]);
            $machine->update(['active_reservation_id' => null,
                'status' => $machine->status->isOperable() ? MachineStatus::AVAILABLE : $machine->status]);
            DB::afterCommit(fn () => event(new CycleCompleted($machine->fresh(), $booking->fresh())));
        }, 5);
    }

    public function cancelBooking(Reservation $booking, string $reason = 'Annulé par utilisateur', bool $isAdmin = false): bool
    {
        return DB::transaction(function () use ($booking, $reason) {
            User::whereKey($booking->user_id)->lockForUpdate()->firstOrFail();
            $machine = Machine::whereKey($booking->machine_id)->lockForUpdate()->firstOrFail();
            $booking = Reservation::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($booking->cancelled_at) {
                return false;
            }
            if (! $booking->canBeCancelled()) {
                throw new InvalidArgumentException('Seules les réservations futures non démarrées peuvent être annulées.');
            }
            $booking->update(['cancelled_at' => now(), 'cancellation_reason' => $reason]);
            Transaction::create(['user_id' => $booking->user_id, 'booking_id' => $booking->id,
                'amount' => $booking->quota_minutes, 'type' => 'refund', 'event_key' => 'refund:'.$booking->id,
                'description' => $reason]);
            if ($machine->active_reservation_id === $booking->id) {
                $machine->update(['active_reservation_id' => null,
                    'status' => $machine->status->isOperable() ? MachineStatus::AVAILABLE : $machine->status]);
            }

            return true;
        }, 5);
    }

    public function releaseExpiredBookings(): int
    {
        $ids = Reservation::occupying()->whereNull('started_at')->whereNull('completed_at')
            ->where('start_time', '<=', now()->subMinutes(config('laundry.grace_period_minutes', 15)))->pluck('id');
        $released = 0;
        foreach ($ids as $id) {
            $released += DB::transaction(function () use ($id) {
                $snapshot = Reservation::find($id);
                if (! $snapshot) {
                    return 0;
                }
                User::whereKey($snapshot->user_id)->lockForUpdate()->firstOrFail();
                $machine = Machine::whereKey($snapshot->machine_id)->lockForUpdate()->firstOrFail();
                $booking = Reservation::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($booking->started_at || $booking->cancelled_at || $booking->released_at || $booking->completed_at) {
                    return 0;
                }
                $booking->update(['released_at' => now(), 'cancellation_reason' => 'Absence après le délai de présence']);
                Transaction::create(['user_id' => $booking->user_id, 'booking_id' => $booking->id,
                    'amount' => $booking->quota_minutes, 'type' => 'refund', 'event_key' => 'refund:'.$booking->id,
                    'description' => 'Libération pour absence (minutes)']);
                if (! $machine->active_reservation_id && $machine->isReserved()) {
                    $machine->update(['status' => MachineStatus::AVAILABLE]);
                }

                return 1;
            }, 5);
        }

        return $released;
    }
}
