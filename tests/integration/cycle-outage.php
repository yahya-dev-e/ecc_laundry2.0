<?php

use App\Events\CycleCompleted;
use App\Events\CycleStarted;
use App\Jobs\CheckActiveCycles;
use App\Models\Machine;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'cycle_test', 'database.connections.cycle_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
]]);
Artisan::call('migrate', ['--force' => true]);
Carbon::setTestNow(Carbon::parse('2026-10-14 09:00', 'Africa/Casablanca'));
$user = User::create(['name' => 'Synthetic', 'email' => 'outage@example.test', 'password' => 'test-only-password', 'role' => 'student']);
$machine = Machine::create(['name' => 'OUTAGE', 'type' => 'washing-machine', 'status' => 'available', 'color' => '#00897b']);
$service = app(BookingService::class);
$booking = $service->createBooking($user, $machine, now()->addHour());
// Fail before any real event listener or mail transport is invoked.
Event::partialMock();
Event::shouldReceive('dispatch')->withArgs(fn ($event) => $event instanceof CycleStarted || $event instanceof CycleCompleted)
    ->andThrow(new RuntimeException('Synthetic queue unavailable'));
Carbon::setTestNow($booking->start_time);
$service->startCycle($booking);
if (! $booking->fresh()->started_at || $machine->fresh()->active_reservation_id !== $booking->id) {
    throw new RuntimeException('Start was not committed during enqueue failure');
}
Carbon::setTestNow($booking->end_time);
$service->completeCycle($machine);
if (! $booking->fresh()->completed_at || $machine->fresh()->active_reservation_id || $booking->fresh()->notified_end) {
    throw new RuntimeException('Completion state lost during enqueue failure');
}
echo "PASS: Failed post-commit event enqueue does not turn committed start/completion into a false failure\n";
Event::fake([CycleCompleted::class]);
(new CheckActiveCycles)->handle($service);
Event::assertDispatched(CycleCompleted::class, fn ($event) => $event->booking->id === $booking->id);
echo "PASS: Next scheduler check rediscovers the unnotified completed reservation\n";
