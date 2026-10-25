<?php

namespace App\Services;

use App\Enums\MachineStatus;
use App\Events\CycleCompleted;
use App\Events\CycleStarted;
use App\Models\Machine;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingService
{
    public function __construct(
        protected MachineSchedulerService $scheduler
    ) {}

    /**
     * Create a new reservation for a user.
     * STRICTLY writes only the columns that exist in the database.
     */
    public function createBooking(User $user, Machine $machine, Carbon $startTime, ?int $durationMinutes = null): Reservation
    {
        return DB::transaction(function () use ($user, $machine, $startTime, $durationMinutes) {
        $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        $machine = Machine::whereKey($machine->id)->lockForUpdate()->firstOrFail();
        $duration = $durationMinutes ?? $machine->default_duration_minutes;
        $endTime = (clone $startTime)->addMinutes($duration);
        if ($duration <= 0 || $startTime->lt(Carbon::now()->subMinutes(15))) {
            throw new InvalidArgumentException('Créneau horaire invalide ou déjà passé.');
        }
        for ($week = $startTime->copy()->startOfWeek(); $week->lt($endTime); $week->addWeek()) {
            $minutes = $startTime->copy()->max($week)->diffInMinutes($endTime->copy()->min($week->copy()->addWeek()), false);
            if ($minutes > $user->weeklyRemainingLimit($week) * 60) {
                throw new InvalidArgumentException('Quota hebdomadaire de réservation dépassé.');
            }
        }

        if (!$machine->status->isOperable()) {
            throw new InvalidArgumentException("Machine {$machine->name} is currently out of service.");
        }

        if (!$this->scheduler->isMachineAvailableForSlot($machine, $startTime, $endTime)) {
            throw new InvalidArgumentException("Machine {$machine->name} already has a conflicting reservation during this slot.");
        }

        $cost = max(1, (int) round($duration / 60));
        if (!$user->hasCredits($cost, $startTime)) {
            $limit = $user->weeklyLimit();
            $remaining = $user->weeklyRemainingLimit($startTime);
            throw new InvalidArgumentException("Crédits insuffisants. Vous avez besoin de {$cost} crédit(s) pour réserver cette machine ({$remaining} crédit(s) restant(s) sur vos {$limit} crédits cette semaine).");
        }

        // Retain the existing grace period for starting the current slot.
        if (!$user->isAdmin()) {
            if ($startTime->lt(Carbon::now()->subMinutes(15))) {
                throw new InvalidArgumentException("Impossible de réserver un créneau horaire déjà passé.");
            }
        }

        return DB::transaction(function () use ($user, $machine, $startTime, $endTime, $cost) {
            $reservation = Reservation::create([
                'user_id' => $user->id,
                'machine_id' => $machine->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'notified_start' => false,
                'notified_end' => false,
                'weekly_session_limit_remaining' => $user->weeklyRemainingLimit(),
            ]);

            $user->deductCredits($cost, "Reservation for machine {$machine->name}", $reservation->id, true);

            // If reservation starts within 15 minutes, set machine status to RESERVED
            if ($startTime->lessThanOrEqualTo(Carbon::now()->addMinutes(15)) && $machine->isAvailable()) {
                $machine->update(['status' => MachineStatus::RESERVED]);
            }

            return $reservation;
        });
        });
    }

    /**
     * Start the physical washing or drying cycle.
     */
    public function startCycle(Reservation $booking): Machine
    {
        if (!$booking->canBeStarted()) {
            throw new InvalidArgumentException("This reservation cannot be started because it is not within the start window.");
        }

        $machine = $booking->machine;

        DB::transaction(function () use ($machine) {
            $machine->update([
                'status' => MachineStatus::IN_USE,
            ]);
        });

        event(new CycleStarted($machine->fresh(), $booking->fresh()));

        return $machine;
    }

    /**
     * Complete the cycle and make machine available.
     */
    public function completeCycle(Machine $machine): void
    {
        $now = Carbon::now();
        $activeBooking = Reservation::where('machine_id', $machine->id)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->latest('start_time')
            ->first();

        DB::transaction(function () use ($machine) {
            $machine->update([
                'status' => MachineStatus::AVAILABLE,
            ]);
        });

        event(new CycleCompleted($machine->fresh(), $activeBooking?->fresh()));
    }

    /**
     * Cancel an existing reservation and release slot.
     */
    public function cancelBooking(Reservation $booking, string $reason = 'Cancelled by user', bool $isAdmin = false): void
    {
        if (!$isAdmin && !$booking->canBeCancelled()) {
            throw new InvalidArgumentException("Cette réservation ne peut pas être annulée car elle est déjà terminée ou en cours.");
        }

        DB::transaction(function () use ($booking) {
            $machine = $booking->machine;
            $user = $booking->user;
            $creditsToRefund = $booking->credits_spent;

            // Delete the reservation row to release the time slot
            $booking->delete();

            // Refund credits to user
            if ($user) {
                $user->addCredits(
                    $creditsToRefund,
                    "Remboursement pour annulation de réservation sur " . ($machine?->name ?? 'machine')
                );
            }

            // Revert machine status to available if it was reserved or in use
            if ($machine && ($machine->status === MachineStatus::RESERVED || $machine->status === MachineStatus::IN_USE)) {
                $machine->update(['status' => MachineStatus::AVAILABLE]);
            }
        });
    }

    /**
     * Release expired reservations where user failed to show up.
     */
    public function releaseExpiredBookings(): int
    {
        $graceMinutes = config('laundry.grace_period_minutes', 15);
        $cutoff = Carbon::now()->subMinutes($graceMinutes);

        // Expired upcoming reservations where start_time <= cutoff and machine remained reserved
        $expiredBookings = Reservation::where('start_time', '<=', $cutoff)
            ->where('end_time', '>', Carbon::now())
            ->whereHas('machine', function ($q) {
                $q->where('status', MachineStatus::RESERVED);
            })
            ->get();

        $count = $expiredBookings->count();
        foreach ($expiredBookings as $booking) {
            $machine = $booking->machine;
            $booking->delete();
            if ($machine) {
                $machine->update(['status' => MachineStatus::AVAILABLE]);
            }
        }

        return $count;
    }
}
