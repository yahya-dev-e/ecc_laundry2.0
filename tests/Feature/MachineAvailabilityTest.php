<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_conflict_disappears_after_cancellation_and_revision_changes(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $this->actingAs($user);
        $before = $this->getJson('/calendar-revision')->json('revision');
        $booking = app(BookingService::class)->createBooking($user, $machine, now()->setTime(11, 0));
        $occupied = $this->getJson('/calendar-revision')->json('revision');
        $this->assertNotSame($before, $occupied);
        $slots = collect($this->getJson('/machines/'.$machine->id.'/slots?date='.now()->toDateString())->assertOk()->json('slots'));
        $this->assertFalse($slots->firstWhere('start_time', '11:00')['is_available']);
        app(BookingService::class)->cancelBooking($booking);
        $this->assertNotSame($occupied, $this->getJson('/calendar-revision')->json('revision'));
        $slots = collect($this->getJson('/machines/'.$machine->id.'/slots?date='.now()->toDateString())->json('slots'));
        $this->assertTrue($slots->firstWhere('start_time', '11:00')['is_available']);
        $this->getJson('/machines/'.$machine->id.'/slots?date=invalid')->assertUnprocessable();
    }
}
