<?php

use App\Models\Machine;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.default' => 'upgrade_test', 'database.connections.upgrade_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
]]);
Carbon::setTestNow(Carbon::parse('2026-10-14 09:00:00', 'Africa/Casablanca'));
foreach (glob(database_path('migrations/*.php')) as $file) {
    if (basename($file) >= '2026_10_11') {
        continue;
    }
    (require $file)->up();
}
Schema::table('transactions', function (Blueprint $t) {
    $t->dropForeign(['booking_id']);
    $t->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
});
$user = User::create(['name' => 'Legacy', 'email' => 'LEGACY@EXAMPLE.TEST', 'student_id' => 'OLD-1',
    'room_number' => 'OLD', 'password' => 'Test-only-password', 'role' => 'student']);
$machine = Machine::create(['name' => 'OLD-WASHER', 'type' => 'washing-machine', 'status' => 'in-use', 'color' => '#00897b']);
$past = Reservation::create(['user_id' => $user->id, 'machine_id' => $machine->id, 'start_time' => now()->subDays(2), 'end_time' => now()->subDays(2)->addMinutes(45)]);
$active = Reservation::create(['user_id' => $user->id, 'machine_id' => $machine->id, 'start_time' => now()->subMinutes(5), 'end_time' => now()->addMinutes(55)]);
if (in_array('--partial-index-failure', $argv, true)) {
    // Simulate MySQL's committed columns after the old index name was rejected.
    Schema::table('users', function (Blueprint $t) {
        $t->unsignedInteger('session_version')->default(0);
        $t->unsignedInteger('weekly_limit_minutes')->nullable();
    });
    Schema::table('reservations', function (Blueprint $t) {
        $t->unsignedInteger('quota_minutes')->default(60);
        $t->boolean('quota_waived')->default(false);
        foreach (['started_at', 'completed_at', 'cancelled_at', 'released_at'] as $column) {
            $t->timestamp($column)->nullable();
        }
        $t->string('cancellation_reason', 500)->nullable();
    });
}
foreach (glob(database_path('migrations/2026_10_11*.php')) as $file) {
    (require $file)->up();
}
$check = function ($ok, $message) {
    if (! $ok) {
        throw new RuntimeException($message);
    } echo "PASS: {$message}\n";
};
$check(Schema::getForeignKeys('transactions')[0]['foreign_table'] === 'reservations' || collect(Schema::getForeignKeys('transactions'))->contains(fn ($key) => $key['columns'] === ['booking_id'] && $key['foreign_table'] === 'reservations'), 'Legacy ledger FK repaired');
$check($past->fresh()->quota_minutes === 45 && $past->fresh()->completed_at !== null, 'Legacy duration/history preserved without retroactive no-show refund');
$check($machine->fresh()->active_reservation_id === $active->id && $active->fresh()->started_at !== null, 'Identifiable active cycle preserved');
$check($user->fresh()->email === 'legacy@example.test', 'Legacy email normalized');
$check(Schema::hasIndex('reservations', 'reservations_availability_index') && strlen('reservations_availability_index') <= 64, 'Explicit availability index fits MySQL identifier limit');
Artisan::call('laundry:reconcile-ledger');
$check(DB::table('transactions')->count() === 0, 'Ledger reconciliation is dry-run by default');
Artisan::call('laundry:reconcile-ledger', ['--apply' => true]);
Artisan::call('laundry:reconcile-ledger', ['--apply' => true]);
$check(DB::table('transactions')->count() === 2 && (int) DB::table('transactions')->sum('amount') === -105, 'Missing legacy charges repaired idempotently in exact minutes');
