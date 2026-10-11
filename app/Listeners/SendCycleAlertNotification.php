<?php

namespace App\Listeners;

use App\Events\CycleCompleted;
use App\Models\Reservation;
use App\Notifications\CycleFinishedNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\DB;

class SendCycleAlertNotification implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(CycleCompleted $event): void
    {
        if (! $event->booking) {
            return;
        }
        DB::transaction(function () use ($event) {
            $booking = Reservation::whereKey($event->booking->id)->lockForUpdate()->first();
            if (! $booking || $booking->notified_end || ! $booking->completed_at || ! $booking->user) {
                return;
            }
            $booking->user->notify(new CycleFinishedNotification($event->machine->name));
            $booking->update(['notified_end' => true]);
        }, 5);
    }
}
