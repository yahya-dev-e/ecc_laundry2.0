<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\MachineStatus;
use App\Models\Booking;
use App\Models\Machine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_bookings_page(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $response = $this->actingAs($user)->get(route('bookings.index'));

        $response->assertStatus(200);
        $response->assertSee('My Bookings & History');
    }

    public function test_user_can_reserve_an_available_machine(): void
    {
        $user = User::factory()->create(['credits' => 10]);
        $machine = Machine::factory()->create([
            'status' => MachineStatus::AVAILABLE,
            'cost_per_cycle' => 2,
        ]);

        $slotTime = Carbon::now()->addHours(2)->startOfHour();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'machine_id' => $machine->id,
            'start_time' => $slotTime->toIso8601String(),
        ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'status' => BookingStatus::CONFIRMED->value,
            'credits_spent' => 2,
        ]);

        $this->assertEquals(8, $user->fresh()->credits);
    }

    public function test_user_cannot_reserve_machine_with_insufficient_credits(): void
    {
        $user = User::factory()->create(['credits' => 1]);
        $machine = Machine::factory()->create([
            'status' => MachineStatus::AVAILABLE,
            'cost_per_cycle' => 2,
        ]);

        $slotTime = Carbon::now()->addHours(3)->startOfHour();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'machine_id' => $machine->id,
            'start_time' => $slotTime->toIso8601String(),
        ]);

        $this->assertDatabaseMissing('bookings', [
            'user_id' => $user->id,
            'machine_id' => $machine->id,
        ]);
        $this->assertEquals(1, $user->fresh()->credits);
    }

    public function test_user_can_cancel_booking_and_get_refund(): void
    {
        $user = User::factory()->create(['credits' => 10]);
        $machine = Machine::factory()->create(['cost_per_cycle' => 2]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'status' => BookingStatus::CONFIRMED,
            'credits_spent' => 2,
        ]);

        $response = $this->actingAs($user)->post(route('bookings.cancel', $booking));

        $response->assertRedirect(route('bookings.index'));
        $this->assertEquals(BookingStatus::CANCELLED, $booking->fresh()->status);
        $this->assertEquals(12, $user->fresh()->credits);
    }

    public function test_user_can_start_cycle_on_confirmed_booking(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create([
            'status' => MachineStatus::AVAILABLE,
            'default_duration_minutes' => 45,
        ]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $response = $this->actingAs($user)->post(route('bookings.start', $booking));

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals(BookingStatus::IN_PROGRESS, $booking->fresh()->status);
        $this->assertEquals(MachineStatus::IN_USE, $machine->fresh()->status);
        $this->assertNotNull($machine->fresh()->current_cycle_ends_at);
    }
}
