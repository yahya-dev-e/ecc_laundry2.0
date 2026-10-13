<?php

namespace App\Support;

use Carbon\Carbon;

class MailDeliveryWindow
{
    public static function cutoff(): Carbon
    {
        return now()->subMinutes(max(1, (int) config('laundry.max_email_delay_minutes', 15)));
    }
}
