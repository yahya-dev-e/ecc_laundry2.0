<?php

namespace App\Jobs;

use App\Enums\MachineStatus;
use App\Models\Machine;
use App\Models\Reservation;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckActiveCycles implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(BookingService $bookingService): void
    {
        $now = Carbon::now();
        // Machines marked 'in-use' where active reservations have elapsed
        $inUseMachines = Machine::where('status', MachineStatus::IN_USE)->get();

        foreach ($inUseMachines as $machine) {
            $hasActiveCycle = Reservation::where('machine_id', $machine->id)
                ->where('start_time', '<=', $now)
                ->where('end_time', '>', $now)
                ->exists();

            if (!$hasActiveCycle) {
                $bookingService->completeCycle($machine);
                Log::info("CheckActiveCycles: Machine {$machine->name} cycle has naturally completed. Machine freed.");
            }
        }
    }
}
