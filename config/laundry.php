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

    // Maximum number of machine reservations allowed per student per calendar week
    'weekly_reservation_limit' => (int) env('LAUNDRY_WEEKLY_LIMIT', 3),

    // Maximum simultaneous active reservations allowed per student
    'max_simultaneous_reservations' => 1,

    // Cycle durations (minutes)
    'cycle_durations' => [
        'washer' => 45,
        'dryer'  => 40,
    ],

    // How many minutes a user has to start before auto-release
    'grace_period_minutes' => 15,

    // Maximum days in advance a student can book a slot
    'max_advance_booking_days' => 7,

    // Operating hours (24h format)
    'operating_hours' => [
        'start' => '06:00',
        'end'   => '23:30',
    ],

    // Quota reset day (every Monday)
    'quota_reset_day' => 'Monday',
];
