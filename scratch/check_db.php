<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$reservations = App\Models\Reservation::with(['machine', 'user'])->get();
echo "Total reservations in DB: " . $reservations->count() . "\n";
foreach ($reservations as $r) {
    echo "ID: {$r->id} | Machine: " . ($r->machine->name ?? 'N/A') . " ({$r->machine_id}) | User: " . ($r->user->name ?? 'N/A') . " ({$r->user_id}) | Start: {$r->start_time} | End: {$r->end_time}\n";
}
