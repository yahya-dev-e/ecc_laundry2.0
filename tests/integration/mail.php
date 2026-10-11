<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Jobs\SendResetLink;
use App\Models\Machine;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'app.env' => 'testing', 'app.url' => 'https://laundry.school.test', 'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'database.default' => 'mail_test', 'database.connections.mail_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
    'queue.default' => 'database', 'cache.default' => 'array', 'session.driver' => 'array',
    'mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp', 'scheme' => 'smtp', 'host' => '127.0.0.1', 'port' => 8768, 'timeout' => 5],
    'mail.from' => ['address' => 'sender@example.test', 'name' => 'Integration test'],
]);
Artisan::call('migrate', ['--force' => true]);
Carbon::setTestNow(Carbon::parse('2026-10-14 09:00:00', 'Africa/Casablanca'));
function requestFor(array $data): Request
{
    $r = Request::create('https://laundry.school.test/test', 'POST', $data);
    $r->setLaravelSession(app('session')->driver());

    return $r;
}
function check($ok, $message)
{
    if (! $ok) {
        throw new RuntimeException($message);
    } echo "PASS: {$message}\n";
}
$password = 'Test-only-pass-42!';
app(AuthController::class)->register(requestFor([
    'name' => 'Mail Test', 'email' => 'student@example.test', 'student_id' => 'MAIL-1', 'room_number' => 'TEST',
    'password' => $password, 'password_confirmation' => $password,
]));
check(DB::table('jobs')->count() === 1, 'Welcome notification durably queued after commit');
Auth::logout();
$controller = app(PasswordResetController::class);
$controller->sendResetLinkEmail(requestFor(['email' => 'student@example.test']));
$payload = DB::table('jobs')->get()->map(fn ($row) => json_decode($row->payload, true))->first(fn ($data) => $data['data']['commandName'] === SendResetLink::class);
$job = unserialize(Crypt::decrypt($payload['data']['command']), ['allowed_classes' => true]);
check(! str_contains($payload['data']['command'], $job->token), 'Queued reset token encrypted at rest');
Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);
check(DB::table('jobs')->count() === 0 && DB::table('failed_jobs')->count() === 0, 'Welcome and reset delivered by actual database worker to local SMTP');
config(['queue.default' => 'sync', 'mail.mailers.smtp.port' => 8769]);
app('mail.manager')->purge('smtp');
$known = $controller->sendResetLinkEmail(requestFor(['email' => 'student@example.test']));
$unknown = $controller->sendResetLinkEmail(requestFor(['email' => 'missing@example.test']));
check($known->getStatusCode() === 302 && $unknown->getStatusCode() === 302, 'Refused SMTP connection produces the same public response for known and unknown accounts');
check(DB::table('password_reset_links')->where('token_hash', hash('sha256', $job->token))->whereNull('consumed_at')->exists(), 'Mail outage preserves previously delivered reset link');
$controller->reset(requestFor(['email' => 'student@example.test', 'token' => $job->token, 'password' => 'New-test-pass-42!', 'password_confirmation' => 'New-test-pass-42!']));
check(User::first()->session_version === 1, 'Previously delivered reset link still works after SMTP outage');
config(['queue.default' => 'database', 'mail.mailers.smtp.port' => 8768]);
app('mail.manager')->purge('smtp');
$user = User::firstOrFail();
$machine = Machine::factory()->create();
$booking = app(BookingService::class)->createBooking($user, $machine, now()->addHour());
Carbon::setTestNow(now()->addHour());
app(BookingService::class)->startCycle($booking);
Carbon::setTestNow(now()->addHour());
app(BookingService::class)->completeCycle($machine);
Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);
check($booking->fresh()->notified_end && DB::table('failed_jobs')->count() === 0, 'Completion event queues and delivers its real recipient notification');
// A failed subsequent dispatch must not invalidate an already-delivered reset link.
$originalToken = $job->token;
$controller->reset(requestFor(['email' => $user->email, 'token' => $originalToken, 'password' => 'New-test-pass-42!', 'password_confirmation' => 'New-test-pass-42!']));
check($user->fresh()->session_version === 1, 'Consumed/expired reset link cannot be reused');
