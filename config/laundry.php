<?php

return [
    'weekly_hours_limit' => (int) env('LAUNDRY_WEEKLY_HOURS_LIMIT', 8),
    'admin_weekly_hours_limit' => (int) env('LAUNDRY_ADMIN_WEEKLY_HOURS_LIMIT', 100),
    'grace_period_minutes' => null, // No-show auto-release disabled
    'max_email_delay_minutes' => (int) env('LAUNDRY_MAX_EMAIL_DELAY_MINUTES', 15),
];
