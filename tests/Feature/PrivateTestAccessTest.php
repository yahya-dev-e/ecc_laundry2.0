<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PrivateTestAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['private_test.enabled' => true, 'private_test.emails' => ['tester@example.test'],
            'private_test.networks' => ['127.0.0.1/32']]);
    }

    public function test_private_test_rejects_other_networks_and_registration(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])->get('/login')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('/login')->assertOk();
        $this->get('/register')->assertForbidden();
        $this->post('/register', [])->assertForbidden();
    }

    public function test_only_allowed_account_can_login_and_existing_sessions_cannot_bypass_gate(): void
    {
        $other = User::factory()->create(['password' => 'Strong-pass-123']);
        $this->post('/login', ['email' => $other->email, 'password' => 'Strong-pass-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($other)->get('/dashboard')->assertForbidden();
        $this->actingAs($other)->post('/bookings', [])->assertForbidden();
        auth()->logout();
        $tester = User::factory()->create(['email' => 'tester@example.test', 'password' => 'Strong-pass-123']);
        $this->post('/login', ['email' => ' TESTER@EXAMPLE.TEST ', 'password' => 'Strong-pass-123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($tester);
        $this->get('/dashboard')->assertOk();
    }

    public function test_disallowed_reset_requests_send_no_email_and_empty_allowlist_fails_closed(): void
    {
        Queue::fake();
        $other = User::factory()->create();
        $this->post('/forgot-password', ['email' => $other->email])->assertSessionHas('status');
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('password_reset_links', 0);
        config(['private_test.emails' => []]);
        $this->actingAs($other)->get('/dashboard')->assertForbidden();
    }

    public function test_disabled_mode_preserves_normal_site_access(): void
    {
        config(['private_test.enabled' => false]);
        $this->get('/register')->assertOk();
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
    }

    public function test_tester_provisioning_requires_isolated_database_and_allowlisted_email(): void
    {
        $this->artisan('laundry:create-tester', ['email' => 'tester@example.test'])->assertFailed();
        config(['database.connections.sqlite.database' => 'isolated_test']);
        $this->artisan('laundry:create-tester', ['email' => 'outsider@example.test'])->assertFailed();
        $this->artisan('laundry:create-tester', ['email' => 'tester@example.test'])
            ->expectsQuestion('Name', 'Private Tester')
            ->expectsQuestion('Choose a private test password (at least 12 characters)', 'Private-pass-123')
            ->expectsQuestion('Confirm password', 'Private-pass-123')->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'tester@example.test', 'role' => 'student']);
        $this->artisan('laundry:create-tester', ['email' => 'tester@example.test'])->assertFailed();
    }
}
