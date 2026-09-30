<?php

namespace App\Listeners;

use App\Events\CycleCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendCycleAlertNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CycleCompleted $event): void
    {
        $machine = $event->machine;
        $booking = $event->booking;

        if ($booking && $booking->user) {
            $user = $booking->user;

            Log::info("Cycle alert: Machine {$machine->code} has completed its cycle for user {$user->name} ({$user->email}).");

            // In production, integrate SMS/Push notifications (e.g. Firebase, Twilio, WebPush)
            // or send email reminder:
            // $user->notify(new LaundryCycleFinishedNotification($machine));
        } else {
            Log::info("Cycle alert: Machine {$machine->code} has completed its cycle without active booking.");
        }
    }
}
