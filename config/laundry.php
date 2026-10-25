<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Centrale Casablanca Laundry Operational Settings
    |--------------------------------------------------------------------------
    |
    | Configuration options for campus laundry machines, weekly reservation
    | quota per student, cycle durations, and operating hours.
    |
    */

    // Maximum number of hours allowed per student per calendar week (8 hours / week)
    'weekly_reservation_limit' => (int) env('LAUNDRY_WEEKLY_LIMIT', 8),
    'weekly_hours_limit' => (int) env('LAUNDRY_WEEKLY_HOURS_LIMIT', 8),
    'admin_weekly_hours_limit' => (int) env('LAUNDRY_ADMIN_WEEKLY_HOURS_LIMIT', 100),
    'cost_per_hour_slot' => 1,

    // No reservation-count cap; the weekly hours allowance is the limit.
    'max_simultaneous_reservations' => null,

    // Cycle durations (minutes)
    'cycle_durations' => [
        'washer' => 60,
        'dryer'  => 60,
    ],

    // How many minutes a user has to start before auto-release
    'grace_period_minutes' => 15,

    // Reservations may be made for any future week.
    'restrict_to_current_week' => false,
    'max_advance_booking_days' => null,

    // Operating hours (Full 24h day - no artificial limiters)
    'operating_hours' => [
        'start' => '00:00',
        'end'   => '24:00',
    ],

    // Quota reset day (every Monday 00:00:00)
    'quota_reset_day' => 'Monday',
];
