<?php

use App\Models\Machine;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// MySQL requires an explicit disposable *_test database; otherwise use our own SQLite file.
$database = null;
putenv('APP_ENV=testing');
config(['app.env' => 'testing']);
if (getenv('LAUNDRY_TEST_DB') === 'mysql') {
    if (! str_ends_with((string) getenv('DB_DATABASE'), '_test')) {
        throw new RuntimeException('Disposable *_test database required.');
    }
    config(['database.default' => 'mysql']);
    putenv('DB_CONNECTION=mysql');
    DB::purge('mysql');
} else {
    $database = tempnam(sys_get_temp_dir(), 'laundry-concurrency-');
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE='.$database);
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
    DB::purge('sqlite');
}
Carbon::setTestNow(Carbon::parse('2026-10-14 09:00:00', 'Africa/Casablanca'));
Artisan::call('migrate:fresh', ['--force' => true]);
$one = User::factory()->create();
$two = User::factory()->create();
$machine = Machine::factory()->create();
$otherMachine = Machine::factory()->create();
$start = now()->addHours(2)->toIso8601String();

function race(array $people, array $machines, string $start): array
{
    $gate = tempnam(sys_get_temp_dir(), 'laundry-gate-');
    unlink($gate);
    $processes = [];
    foreach ($people as $i => $person) {
        $ready = tempnam(sys_get_temp_dir(), 'laundry-ready-');
        unlink($ready);
        $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __DIR__.'/contender.php', $ready, $gate, $person->id, $machines[$i]->id, $start],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        if (! is_resource($process)) {
            throw new RuntimeException('Cannot start contender');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes, $ready];
    }
    $deadline = microtime(true) + 20;
    while (! collect($processes)->every(fn ($p) => is_file($p[2]))) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Ready barrier timeout');
        }
        usleep(10000);
    }
    file_put_contents($gate, 'go');
    $results = [];
    foreach ($processes as [$process,$pipes,$ready]) {
        $results[] = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException($error);
        }
        unlink($ready);
    }
    unlink($gate);
    sort($results);

    return $results;
}
try {
    if (race([$one, $two], [$machine, $machine], $start) !== ['BOOKED', 'REJECTED']) {
        throw new RuntimeException('Overlapping bookings both succeeded');
    }
    if (Reservation::count() !== 1 || DB::table('transactions')->count() !== 1) {
        throw new RuntimeException('Overlap ledger mismatch');
    }
    echo "PASS: Separate PHP processes cannot double-book one machine\n";
    $three = User::factory()->create();
    $three->forceFill(['weekly_limit_minutes' => 60])->save();
    if (race([$three, $three], [$machine, $otherMachine], now()->addHours(4)->toIso8601String()) !== ['BOOKED', 'REJECTED']) {
        throw new RuntimeException('Quota overspent');
    }
    if ($three->weeklyRemainingMinutes() !== 0 || $three->reservations()->count() !== 1) {
        throw new RuntimeException('Quota accounting mismatch');
    }
    echo "PASS: Separate PHP processes cannot overspend one user quota on different machines\n";
} finally {
    DB::disconnect();
    if ($database) {
        unlink($database);
    }
}
