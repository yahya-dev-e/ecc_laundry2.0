<?php

namespace App\Jobs;

use App\Events\CycleCompleted;
use App\Models\Machine;
use App\Models\Reservation;
use App\Services\BookingService;
use App\Support\MailDeliveryWindow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckActiveCycles implements ShouldQueue
{
    use Queueable;

    public function handle(BookingService $service): void
    {
        Machine::whereNotNull('active_reservation_id')->each(fn ($machine) => $service->completeCycle($machine));
        // Recover a notification whose enqueue failed after completion committed.
        // Exclude inferred legacy completions that never recorded an actual start.
        Reservation::whereNotNull('completed_at')->whereNotNull('started_at')->where('notified_end', false)
            ->where('end_time', '>=', MailDeliveryWindow::cutoff())
            ->with('machine')->chunkById(100, function ($bookings) {
                foreach ($bookings as $booking) {
                    if ($booking->machine) {
                        try {
                            event(new CycleCompleted($booking->machine, $booking));
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                }
            });
    }
}
