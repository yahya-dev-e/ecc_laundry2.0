<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_role_enum_preserves_users_and_allows_real_student_signup(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->enum('role', ['user', 'admin'])->default('user')->change());
        $student = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $migration = require database_path('migrations/2026_10_14_000001_allow_application_account_roles.php');
        $migration->up();
        $migration->up();
        $this->assertSame('user', $student->fresh()->role);
        $this->assertFalse($student->fresh()->isAdmin());
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->isAdmin());
        Notification::fake();
        $this->post('/register', ['name' => 'New Student', 'email' => 'role-test@example.test',
            'password' => 'Private-pass-42!', 'password_confirmation' => 'Private-pass-42!', 'role' => 'admin'])
            ->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'role-test@example.test', 'role' => 'student']);
    }
}
