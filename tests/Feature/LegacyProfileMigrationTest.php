<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyProfileMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_required_profiles_become_optional_without_losing_existing_values(): void
    {
        $existing = User::factory()->create();
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_id')->nullable(false)->change();
            $table->string('room_number')->nullable(false)->change();
        });
        $migration = require database_path('migrations/2026_10_13_000001_make_profile_fields_optional.php');
        $migration->up();
        $migration->up();
        $this->assertSame($existing->student_id, $existing->fresh()->student_id);
        $this->assertSame($existing->room_number, $existing->fresh()->room_number);
        foreach ([1, 2] as $number) {
            User::factory()->create(['email' => 'optional'.$number.'@example.test', 'student_id' => null, 'room_number' => null]);
        }
        $this->assertSame(2, User::whereNull('student_id')->whereNull('room_number')->count());
    }
}
