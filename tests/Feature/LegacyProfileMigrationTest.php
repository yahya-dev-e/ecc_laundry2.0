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

    public function test_existing_ledger_is_adopted_without_recreating_or_changing_rows(): void
    {
        $user = User::factory()->create();
        $transaction = $user->transactions()->create(['amount' => 17, 'type' => 'topup', 'description' => 'Existing ledger record']);
        $migration = require database_path('migrations/2026_01_01_000003_create_transactions_table.php');
        $migration->up();
        $migration->up();
        $this->assertSame(17, (int) $transaction->fresh()->amount);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_missing_legacy_profile_columns_are_added_as_optional_and_repeatable(): void
    {
        $existing = User::factory()->create();
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['student_id']);
            $table->dropColumn(['student_id', 'room_number']);
        });
        $migration = require database_path('migrations/2026_10_13_000001_make_profile_fields_optional.php');
        $migration->up();
        $migration->up();
        $this->assertSame($existing->email, $existing->fresh()->email);
        $this->assertNull($existing->fresh()->student_id);
        $this->assertNull($existing->fresh()->room_number);
        User::factory()->create(['student_id' => null, 'room_number' => null]);
        $this->assertSame(2, User::whereNull('student_id')->whereNull('room_number')->count());
    }

    public function test_orphan_ledger_links_are_preserved_and_constraint_restored_without_deleting_history(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->dropForeign(['booking_id']));
        $user = User::factory()->create();
        $orphan = $user->transactions()->create(['booking_id' => 99999, 'amount' => -60, 'type' => 'booking_charge', 'description' => 'Historical charge']);
        $migration = require database_path('migrations/2026_10_13_000002_preserve_legacy_ledger_links.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('transactions', ['id' => $orphan->id, 'booking_id' => null, 'legacy_booking_id' => 99999,
            'amount' => -60, 'type' => 'booking_charge', 'description' => 'Historical charge']);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertTrue(collect(Schema::getForeignKeys('transactions'))->contains(
            fn ($key) => $key['columns'] === ['booking_id'] && $key['foreign_table'] === 'reservations'));
    }
}
