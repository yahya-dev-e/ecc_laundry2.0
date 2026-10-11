<?php

namespace App\Services;

use Carbon\Carbon;

class BookingWindow
{
    // Exclusive upper bound: a reservation may end exactly at this midnight.
    public static function end(): Carbon
    {
        return config('laundry.restrict_to_current_week', false)
            ? now()->startOfWeek()->addWeek()
            : now()->startOfDay()->addDays((int) config('laundry.max_advance_booking_days', 7) + 1);
    }

    public static function lastDate(): string
    {
        return self::end()->subDay()->toDateString();
    }
}
