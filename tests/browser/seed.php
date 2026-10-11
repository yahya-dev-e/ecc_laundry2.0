<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local', 'testing') || config('database.default') !== 'sqlite' || ! str_contains(config('database.connections.sqlite.database'), 'fixes-browser')) {
    throw new RuntimeException('This fixture only runs against the isolated fixes-browser SQLite database.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--force' => true]);
User::create(['name' => 'Browser Admin', 'email' => 'admin@example.test',
    'student_id' => 'TEST-ADMIN', 'room_number' => 'TEST', 'role' => 'admin', 'password' => 'Browser-test-pass-42!']);
echo "Isolated database migrated and seeded.\n";
