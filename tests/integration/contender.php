<?php

use App\Models\Machine;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing')) {
    throw new RuntimeException('Testing environment required.');
}
Carbon::setTestNow(Carbon::parse('2026-10-14 09:00:00', 'Africa/Casablanca'));
$ready = $argv[1];
$gate = $argv[2];
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 20;
while (! is_file($gate)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timed out');
    }
    usleep(10000);
}
try {
    app(BookingService::class)->createBooking(
        User::findOrFail((int) $argv[3]), Machine::findOrFail((int) $argv[4]),
        Carbon::parse($argv[5])
    );
    echo "BOOKED\n";
} catch (InvalidArgumentException $e) {
    echo "REJECTED\n";
}
