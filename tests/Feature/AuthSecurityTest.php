<?php

namespace Tests\Feature;

use App\Jobs\SendResetLink;
use App\Models\Machine;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function accountData(array $overrides = []): array
    {
        return $overrides + ['name' => 'Test Student', 'email' => 'new@example.test', 'student_id' => 'NEW-1',
            'room_number' => 'A101', 'password' => 'Strong-pass-123', 'password_confirmation' => 'Strong-pass-123', 'role' => 'admin'];
    }

    public function test_signup_normalizes_email_ignores_admin_role_and_shows_validation_errors(): void
    {
        Notification::fake();
        $this->post('/register', $this->accountData(['email' => 'NEW@EXAMPLE.TEST']))->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('student', $user->role);
        $this->assertSame(480, $user->weeklyRemainingMinutes());
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, session('auth_version'));
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/register', $this->accountData(['password_confirmation' => 'wrong']))->assertSessionHasErrors('password');
        $this->get('/register')->assertSee('role="alert"', false)->assertSee('password_confirmation')->assertDontSee('R. Omari');
    }

    public function test_login_is_throttled_and_session_is_regenerated(): void
    {
        $user = User::factory()->create(['password' => 'Strong-pass-123']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertRedirect()->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
        Cache::flush();
        $this->get('/login');
        $previous = session()->getId();
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'Strong-pass-123', 'remember' => '1'])->assertRedirect('/dashboard');
        $this->assertNotSame($previous, session()->getId());
        $this->assertAuthenticatedAs($user);
        $this->get('/logout')->assertStatus(405);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_every_user_management_and_machine_write_endpoint_rejects_students(): void
    {
        $student = User::factory()->create();
        $victim = User::factory()->create();
        $machine = Machine::factory()->create();
        $this->actingAs($student);
        foreach (['/utilisateurs', '/admin/users/create', '/admin/users/'.$victim->id.'/edit', '/machines', '/admin/reservation/calendrier'] as $url) {
            $this->get($url)->assertForbidden();
        }
        foreach (['/admin/users', '/admin/users/update', '/admin/users/'.$victim->id.'/reset-quota'] as $url) {
            $this->post($url, $this->accountData(['id' => $victim->id]))->assertForbidden();
        }
        $this->patch('/machines/'.$machine->id.'/status', ['status' => 'available'])->assertForbidden();
        $this->assertSame('student', $student->fresh()->role);
        $this->assertNotSame('new@example.test', $victim->fresh()->email);
    }

    public function test_admin_can_create_edit_and_reset_the_real_enforced_quota(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/users', $this->accountData(['role' => 'student', 'weeklyLimit' => 2]))
            ->assertSessionHasNoErrors()->assertRedirect('/utilisateurs');
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame(120, $user->weeklyLimitMinutes());
        $machine = Machine::factory()->create();
        $first = app(BookingService::class)->createBooking($user, $machine, now()->addHour());
        $this->assertSame(60, $user->weeklyRemainingMinutes());
        $this->post('/admin/users/'.$user->id.'/reset-quota')->assertSessionHas('success');
        $this->assertSame(120, $user->fresh()->weeklyRemainingMinutes());
        app(BookingService::class)->cancelBooking($first);
        app(BookingService::class)->createBooking($user, $machine, now()->addHours(2));
        $this->assertSame(60, $user->fresh()->weeklyRemainingMinutes(), 'Cancelling an exempt booking must not exempt new bookings');
        $this->post('/admin/users/update', $this->accountData(['id' => $user->id, 'role' => 'student', 'weeklyLimit' => 3, 'password' => null, 'password_confirmation' => null]))
            ->assertSessionHasNoErrors()->assertRedirect('/utilisateurs');
        $this->assertSame(180, $user->fresh()->weeklyLimitMinutes());
        $this->post('/admin/users/update', $this->accountData(['id' => $user->id, 'email' => $admin->email, 'weeklyLimit' => 3]))
            ->assertSessionHasErrors('email');
    }

    public function test_reset_link_uses_canonical_origin_even_with_poisoned_request_host(): void
    {
        config(['app.url' => 'https://laundry.school.test']);
        $user = User::factory()->create();
        $mail = (new ResetPasswordNotification(str_repeat('a', 64)))->toMail($user);
        $this->assertStringStartsWith('https://laundry.school.test/reset-password/', $mail->actionUrl);
        $this->assertStringContainsString(rawurlencode($user->email), $mail->actionUrl);
        $this->get('https://evil.example.test/login')->assertStatus(400);
    }

    public function test_reset_requests_are_generic_throttled_and_preserve_older_tokens(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect()->assertSessionHas('status');
        $known = session('status');
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertSessionHas('status', $known);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', $known);
        $this->assertSame(2, DB::table('password_reset_links')->whereNull('consumed_at')->count());
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        $this->post('/forgot-password', ['email' => $user->email])->assertStatus(429);
        Queue::assertPushed(SendResetLink::class, 3);
    }

    public function test_successful_reset_consumes_all_links_rotates_remember_token_and_revokes_other_sessions(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-remember']);
        $token = str_repeat('a', 64);
        $other = str_repeat('b', 64);
        foreach ([$token, $other] as $value) {
            DB::table('password_reset_links')->insert([
                'user_id' => $user->id, 'token_hash' => hash('sha256', $value), 'created_at' => now(), 'expires_at' => now()->addHour(),
            ]);
        }
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'Changed-pass-123', 'password_confirmation' => 'Changed-pass-123'];
        $this->post('/reset-password', $payload)->assertRedirect('/login');
        $this->assertTrue(Hash::check('Changed-pass-123', $user->fresh()->password));
        $this->assertNotSame('old-remember', $user->fresh()->remember_token);
        $this->assertSame(1, $user->fresh()->session_version);
        $this->assertSame(0, DB::table('password_reset_links')->whereNull('consumed_at')->count());
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        $this->actingAs($user->fresh())->withSession(['auth_version' => 0])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_expired_reset_link_and_admin_identity_change_invalidate_access(): void
    {
        $user = User::factory()->create();
        $token = str_repeat('a', 64);
        DB::table('password_reset_links')->insert(['user_id' => $user->id, 'token_hash' => hash('sha256', $token),
            'created_at' => now()->subHours(2), 'expires_at' => now()->subHour()]);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Changed-pass-123', 'password_confirmation' => 'Changed-pass-123'])
            ->assertSessionHasErrors('email');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/users/update', $this->accountData([
            'id' => $user->id, 'email' => 'changed@example.test', 'student_id' => $user->student_id,
            'weeklyLimit' => 8, 'role' => 'student', 'password' => null, 'password_confirmation' => null,
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, $user->fresh()->session_version);
        $this->assertSame(0, DB::table('password_reset_links')->whereNull('consumed_at')->count());
    }

    public function test_reset_queue_is_encrypted_and_mail_outage_does_not_reveal_an_account(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect()->assertSessionHas('status');
        // Queue dispatch occurs after commit; RefreshDatabase deliberately holds its test transaction.
        $job = new SendResetLink($user->id, 999, str_repeat('s', 64));
        $id = app('queue')->connection('database')->push($job);
        $payload = json_decode(DB::table('jobs')->where('id', $id)->value('payload'), true);
        $this->assertStringNotContainsString(str_repeat('s', 64), $payload['data']['command']);
        config(['queue.default' => 'sync']);
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect()->assertSessionHas('status');
    }

    public function test_last_administrator_cannot_demote_their_only_admin_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/users/update', $this->accountData([
            'id' => $admin->id, 'email' => $admin->email, 'student_id' => $admin->student_id,
            'role' => 'student', 'weeklyLimit' => 8, 'password' => null, 'password_confirmation' => null,
        ]))->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
    }
}
