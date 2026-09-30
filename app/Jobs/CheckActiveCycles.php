<?php

namespace App\Jobs;

use App\Enums\MachineStatus;
use App\Models\Machine;
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
        $finishedMachines = Machine::where('status', MachineStatus::IN_USE)
            ->whereNotNull('current_cycle_ends_at')
            ->where('current_cycle_ends_at', '<=', Carbon::now())
            ->get();

        foreach ($finishedMachines as $machine) {
            $bookingService->completeCycle($machine);
            Log::info("CheckActiveCycles: Machine {$machine->code} cycle has naturally completed. Machine freed.");
        }
    }
}
