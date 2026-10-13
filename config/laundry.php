<?php

return [
    'weekly_hours_limit' => (int) env('LAUNDRY_WEEKLY_HOURS_LIMIT', 8),
    'admin_weekly_hours_limit' => (int) env('LAUNDRY_ADMIN_WEEKLY_HOURS_LIMIT', 100),
    'max_simultaneous_reservations' => (int) env('LAUNDRY_MAX_ACTIVE_RESERVATIONS', 3),
    'grace_period_minutes' => (int) env('LAUNDRY_GRACE_PERIOD_MINUTES', 15),
    'restrict_to_current_week' => (bool) env('LAUNDRY_RESTRICT_CURRENT_WEEK', false),
    'max_advance_booking_days' => (int) env('LAUNDRY_MAX_ADVANCE_DAYS', 7),
    'max_email_delay_minutes' => (int) env('LAUNDRY_MAX_EMAIL_DELAY_MINUTES', 15),
];
