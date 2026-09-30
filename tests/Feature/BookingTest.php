<?php

namespace Tests\Feature;

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
        $response->assertSee('Mes Réservations & Historique');
    }

    public function test_user_can_reserve_an_available_machine(): void
    {
        $user = User::factory()->create(['credits' => 10]);
        $machine = Machine::factory()->create([
            'status' => MachineStatus::AVAILABLE,
        ]);

        $slotTime = Carbon::now()->addHours(2)->startOfHour();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'machine_id' => $machine->id,
            'start_time' => $slotTime->toIso8601String(),
        ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertDatabaseHas('reservations', [
            'user_id' => $user->id,
            'machine_id' => $machine->id,
        ]);

        $this->assertEquals(9, $user->fresh()->credits);
    }

    public function test_user_cannot_reserve_machine_with_insufficient_credits(): void
    {
        $user = User::factory()->create(['credits' => 0]);
        $machine = Machine::factory()->create([
            'status' => MachineStatus::AVAILABLE,
        ]);

        $slotTime = Carbon::now()->addHours(3)->startOfHour();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'machine_id' => $machine->id,
            'start_time' => $slotTime->toIso8601String(),
        ]);

        $this->assertDatabaseMissing('reservations', [
            'user_id' => $user->id,
            'machine_id' => $machine->id,
        ]);
        $this->assertEquals(0, $user->fresh()->credits);
    }

    public function test_user_can_cancel_booking_and_get_refund(): void
    {
        $user = User::factory()->create(['credits' => 10]);
        $machine = Machine::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'start_time' => Carbon::now()->addHours(2),
            'end_time' => Carbon::now()->addHours(3),
        ]);

        $response = $this->actingAs($user)->post(route('bookings.cancel', $booking));

        $response->assertRedirect(route('bookings.index'));
        $this->assertDatabaseMissing('reservations', ['id' => $booking->id]);
        $this->assertEquals(11, $user->fresh()->credits);
    }

    public function test_user_can_start_cycle_on_confirmed_booking(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create([
            'status' => MachineStatus::AVAILABLE,
        ]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'start_time' => Carbon::now()->subMinutes(5),
            'end_time' => Carbon::now()->addMinutes(40),
        ]);

        $response = $this->actingAs($user)->post(route('bookings.start', $booking));

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals('in_progress', $booking->fresh()->status);
        $this->assertEquals(MachineStatus::IN_USE, $machine->fresh()->status);
    }
}
