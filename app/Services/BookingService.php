<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\MachineStatus;
use App\Events\CycleCompleted;
use App\Events\CycleStarted;
use App\Models\Booking;
use App\Models\Machine;
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
     * Create a new booking reservation for a user.
     */
    public function createBooking(User $user, Machine $machine, Carbon $startTime, ?int $durationMinutes = null): Booking
    {
        $duration = $durationMinutes ?? $machine->default_duration_minutes;
        $endTime = (clone $startTime)->addMinutes($duration);

        if (!$machine->status->isOperable()) {
            throw new InvalidArgumentException("Machine {$machine->code} is currently out of service.");
        }

        if (!$this->scheduler->isMachineAvailableForSlot($machine, $startTime, $endTime)) {
            throw new InvalidArgumentException("Machine {$machine->code} already has a conflicting reservation during this slot.");
        }

        $cost = $machine->cost_per_cycle;
        if (!$user->hasCredits($cost)) {
            throw new InvalidArgumentException("Insufficient credits. You need {$cost} credits to reserve this machine.");
        }

        return DB::transaction(function () use ($user, $machine, $startTime, $endTime, $cost) {
            $booking = Booking::create([
                'user_id' => $user->id,
                'machine_id' => $machine->id,
                'status' => BookingStatus::CONFIRMED,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'credits_spent' => $cost,
            ]);

            $user->deductCredits($cost, "Reservation for machine {$machine->code}", $booking->id);

            // If reservation starts within 15 minutes, set machine status to RESERVED immediately
            if ($startTime->lessThanOrEqualTo(Carbon::now()->addMinutes(15)) && $machine->isAvailable()) {
                $machine->update(['status' => MachineStatus::RESERVED]);
            }

            return $booking;
        });
    }

    /**
     * Start the physical washing or drying cycle.
     */
    public function startCycle(Booking $booking): Machine
    {
        if (!$booking->status->canBeStarted()) {
            throw new InvalidArgumentException("This booking cannot be started because its status is: {$booking->status->label()}.");
        }

        $machine = $booking->machine;
        $duration = $machine->default_duration_minutes;
        $endsAt = Carbon::now()->addMinutes($duration);

        DB::transaction(function () use ($booking, $machine, $endsAt) {
            $booking->update([
                'status' => BookingStatus::IN_PROGRESS,
                'started_at' => Carbon::now(),
                'end_time' => $endsAt,
            ]);

            $machine->update([
                'status' => MachineStatus::IN_USE,
                'current_cycle_ends_at' => $endsAt,
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
        $activeBooking = Booking::where('machine_id', $machine->id)
            ->where('status', BookingStatus::IN_PROGRESS)
            ->latest()
            ->first();

        DB::transaction(function () use ($machine, $activeBooking) {
            if ($activeBooking) {
                $activeBooking->update([
                    'status' => BookingStatus::COMPLETED,
                    'completed_at' => Carbon::now(),
                ]);
            }

            $machine->update([
                'status' => MachineStatus::AVAILABLE,
                'current_cycle_ends_at' => null,
            ]);
        });

        event(new CycleCompleted($machine->fresh(), $activeBooking?->fresh()));
    }

    /**
     * Cancel a booking reservation and refund credits.
     */
    public function cancelBooking(Booking $booking, string $reason = 'Cancelled by user'): void
    {
        if (!$booking->status->canBeCancelled()) {
            throw new InvalidArgumentException("Cannot cancel a booking with status {$booking->status->label()}.");
        }

        DB::transaction(function () use ($booking, $reason) {
            $booking->update([
                'status' => BookingStatus::CANCELLED,
                'cancellation_reason' => $reason,
            ]);

            // Refund credits to user
            $booking->user->addCredits(
                $booking->credits_spent,
                "Refund for cancelled booking #{$booking->id} on {$booking->machine->code}",
                $booking->id
            );

            // Revert machine status to available if it was reserved
            $machine = $booking->machine;
            if ($machine->status === MachineStatus::RESERVED) {
                $machine->update(['status' => MachineStatus::AVAILABLE]);
            }
        });
    }

    /**
     * Release expired pending/confirmed bookings where user failed to show up.
     */
    public function releaseExpiredBookings(): int
    {
        $graceMinutes = config('laundry.grace_period_minutes', 15);
        $expiredBookings = Booking::expiredPending($graceMinutes)->get();

        $count = 0;
        foreach ($expiredBookings as $booking) {
            DB::transaction(function () use ($booking) {
                $booking->update([
                    'status' => BookingStatus::EXPIRED,
                    'cancellation_reason' => 'User did not show up within grace period.',
                ]);

                // Refund 50% or full credits according to college policy
                $refund = (int) floor($booking->credits_spent / 2);
                if ($refund > 0) {
                    $booking->user->addCredits(
                        $refund,
                        "Partial refund for expired booking #{$booking->id} (no-show penalty)",
                        $booking->id
                    );
                }

                $machine = $booking->machine;
                if ($machine->status === MachineStatus::RESERVED) {
                    $machine->update(['status' => MachineStatus::AVAILABLE]);
                }
            });
            $count++;
        }

        return $count;
    }
}
