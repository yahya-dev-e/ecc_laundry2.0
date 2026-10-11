<?php

namespace App\Jobs;

use App\Models\Machine;
use App\Services\BookingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckActiveCycles implements ShouldQueue
{
    use Queueable;

    public function handle(BookingService $service): void
    {
        Machine::whereNotNull('active_reservation_id')->each(fn ($machine) => $service->completeCycle($machine));
    }
}
