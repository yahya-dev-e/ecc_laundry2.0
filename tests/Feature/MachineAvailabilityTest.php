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

class MachineAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_machine_slots_endpoint_returns_json_schedule(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create([
            'status' => MachineStatus::AVAILABLE,
            'default_duration_minutes' => 45,
        ]);

        $today = Carbon::today()->toDateString();
        $response = $this->actingAs($user)->getJson(route('machines.slots', [
            'machine' => $machine->id,
            'date' => $today,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'machine_id',
            'code',
            'date',
            'slots' => [
                '*' => ['start_time', 'end_time', 'datetime', 'is_available', 'is_past'],
            ],
        ]);
    }

    public function test_conflicting_slot_is_marked_unavailable(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create(['default_duration_minutes' => 45]);

        $tomorrowTenAm = Carbon::tomorrow()->setTime(10, 0, 0);
        $tomorrowTenFortyFive = Carbon::tomorrow()->setTime(10, 45, 0);

        Booking::create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'status' => BookingStatus::CONFIRMED,
            'start_time' => $tomorrowTenAm,
            'end_time' => $tomorrowTenFortyFive,
            'credits_spent' => 2,
        ]);

        $response = $this->actingAs($user)->getJson(route('machines.slots', [
            'machine' => $machine->id,
            'date' => Carbon::tomorrow()->toDateString(),
        ]));

        $slots = collect($response->json('slots'));
        $bookedSlot = $slots->firstWhere('start_time', '10:00');

        $this->assertNotNull($bookedSlot);
        $this->assertFalse($bookedSlot['is_available']);
    }

    public function test_admin_can_update_machine_operational_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $machine = Machine::factory()->create(['status' => MachineStatus::AVAILABLE]);

        $response = $this->actingAs($admin)->patch(route('machines.update-status', $machine), [
            'status' => MachineStatus::MAINTENANCE->value,
            'notes' => 'Replacing water pump seal',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(MachineStatus::MAINTENANCE, $machine->fresh()->status);
        $this->assertEquals('Replacing water pump seal', $machine->fresh()->notes);
    }
}
