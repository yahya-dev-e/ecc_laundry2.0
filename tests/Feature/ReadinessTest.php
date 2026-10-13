<?php

namespace Tests\Feature;

use App\Events\CycleCompleted;
use App\Jobs\CheckActiveCycles;
use App\Models\Machine;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function signup(array $changes = []): array
    {
        return $changes + ['name' => 'New Student', 'email' => 'student@any-domain.test',
            'password' => 'A-private-password', 'password_confirmation' => 'A-private-password'];
    }

    public function test_signup_needs_no_student_id_or_room_and_duplicate_account_stays_in_form(): void
    {
        Notification::fake();
        $this->get('/register')->assertOk()->assertDontSee('name="student_id"', false)->assertDontSee('name="room_number"', false);
        $this->post('/register', $this->signup(['student_id' => 'injected', 'room_number' => 'injected']))->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['email' => 'student@any-domain.test', 'student_id' => null, 'room_number' => null]);
        $this->post('/logout');
        $this->from('/register')->post('/register', $this->signup())->assertRedirect('/register')->assertSessionHasErrors('email');
        $this->get('/register')->assertOk()->assertSee('Un compte existe déjà')->assertSee('Mot de passe oublié');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_bad_login_is_visible_and_malformed_email_does_not_crash_post_or_form(): void
    {
        $this->from('/login')->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->get('/login')->assertOk()->assertSee('Les identifiants ne correspondent pas');
        foreach (['/login', '/register', '/forgot-password', '/reset-password'] as $path) {
            $this->from($path)->post($path, $this->signup(['email' => ['bad'], 'name' => ['bad']]))->assertRedirect()->assertSessionHasErrors('email');
            if ($path !== '/reset-password') {
                $this->get($path)->assertOk();
            }
        }
        $this->getJson('/reset-password/'.str_repeat('a', 64).'?email[]=bad')->assertUnprocessable();
    }

    public function test_new_passwords_cannot_be_silently_truncated_or_contain_null_bytes(): void
    {
        foreach ([str_repeat('x', 73), str_repeat('é', 40), "password\0test"] as $password) {
            $this->post('/register', $this->signup(['password' => $password, 'password_confirmation' => $password]))->assertSessionHasErrors('password');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_error_pages_keep_branding_hide_exception_details_and_preserve_json(): void
    {
        config(['app.debug' => false]);
        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            Route::get('/test-error-'.$status, fn () => abort($status, 'secret database connection detail'));
            $response = $this->get('/test-error-'.$status)->assertStatus($status);
            $response->assertSee('Centrale Casablanca')->assertSee('Revenir au site')->assertDontSee('secret database');
        }
        $this->getJson('/not-a-real-page')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_admin_can_edit_accounts_created_without_optional_profile_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['student_id' => null, 'room_number' => null]);
        $this->actingAs($admin)->post('/admin/users/update', ['id' => $student->id, 'name' => $student->name,
            'email' => $student->email, 'role' => 'student', 'weeklyLimit' => 8])->assertSessionHasNoErrors();
    }

    public function test_expired_session_returns_json_401_for_availability_polling(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $this->actingAs($user);
        $user->increment('session_version');
        $this->getJson('/machines/'.$machine->id.'/slots')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_duplicate_signup_race_becomes_validation_not_a_server_error(): void
    {
        $dispatcher = User::getEventDispatcher();
        User::setEventDispatcher(clone $dispatcher);
        try {
            User::creating(function () {
                throw new UniqueConstraintViolationException('sqlite', 'insert into users', [], new \PDOException('duplicate'));
            });
            $this->from('/register')->post('/register', $this->signup())->assertRedirect('/register')->assertSessionHasErrors('email');
            $this->assertDatabaseCount('users', 0);
        } finally {
            User::setEventDispatcher($dispatcher);
        }
    }

    public function test_exact_start_deadline_is_reserved_for_no_show_release(): void
    {
        $user = User::factory()->create();
        $service = app(BookingService::class);
        $booking = $service->createBooking($user, Machine::factory()->create(), now()->addHour());
        $this->travelTo($booking->start_time->copy()->addMinutes(15));
        $this->assertFalse($booking->canBeStarted());
        $this->assertSame(1, $service->releaseExpiredBookings());
        $this->assertSame(480, $user->weeklyRemainingMinutes());
    }

    public function test_completion_notification_recovery_ignores_legacy_and_already_notified_rows(): void
    {
        $service = app(BookingService::class);
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $pending = $service->createBooking($user, $machine, now()->addHour());
        $legacy = $service->createBooking($user, $machine, now()->addHours(2));
        $sent = $service->createBooking($user, $machine, now()->addHours(3));
        $pending->update(['started_at' => now(), 'completed_at' => now()]);
        $legacy->update(['completed_at' => now()]);
        $sent->update(['started_at' => now(), 'completed_at' => now(), 'notified_end' => true]);
        Event::fake([CycleCompleted::class]);
        (new CheckActiveCycles)->handle($service);
        Event::assertDispatchedTimes(CycleCompleted::class, 1);
        Event::assertDispatched(CycleCompleted::class, fn ($event) => $event->booking->id === $pending->id);
    }

    public function test_calendar_displays_actual_twenty_minute_duration(): void
    {
        $user = User::factory()->create();
        app(BookingService::class)->createBooking($user, Machine::factory()->create(), now()->addHour(), 20);
        $this->actingAs($user)->get('/calendrier')->assertViewHas('calendarBlocks',
            fn ($blocks) => $blocks[0]['durationMinutes'] === 20 && $blocks[0]['durationFormatted'] === '20 min');
    }

    public function test_calendar_does_not_hide_future_cancellation_inside_a_running_block(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $service = app(BookingService::class);
        $first = $service->createBooking($user, $machine, now()->addHour());
        $second = $service->createBooking($user, $machine, now()->addHours(2));
        $this->travelTo($first->start_time);
        $service->startCycle($first);
        $this->actingAs($user)->get('/calendrier')->assertViewHas('calendarBlocks',
            fn ($blocks) => count($blocks) === 2 && $blocks[1]['reservation_ids'] === [$second->id] && $blocks[1]['cancellable']);
    }

    public function test_readiness_command_is_read_only_and_flags_test_configuration(): void
    {
        $before = User::count();
        $this->artisan('laundry:readiness')->expectsOutput('FAIL APP_ENV is production')->assertFailed();
        $this->assertSame($before, User::count());
    }
}
