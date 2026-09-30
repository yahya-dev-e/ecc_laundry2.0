<?php

namespace App\Jobs;

use App\Services\BookingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoReleaseExpiredBookings implements ShouldQueue
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
        $releasedCount = $bookingService->releaseExpiredBookings();

        if ($releasedCount > 0) {
            Log::info("AutoReleaseExpiredBookings: Automatically released {$releasedCount} expired booking reservations.");
        }
    }
}
