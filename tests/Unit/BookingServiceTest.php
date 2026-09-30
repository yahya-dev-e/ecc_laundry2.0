<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Enums\MachineStatus;
use App\Events\CycleCompleted;
use App\Events\CycleStarted;
use App\Models\Booking;
use App\Models\Machine;
use App\Models\User;
use App\Services\BookingService;
use App\Services\MachineSchedulerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BookingService(new MachineSchedulerService);
    }

    public function test_booking_creation_deducts_credits_and_sets_confirmed_status(): void
    {
        Event::fake();

        $user = User::factory()->create(['credits' => 10]);
        $machine = Machine::factory()->create([
            'status' => MachineStatus::AVAILABLE,
            'cost_per_cycle' => 2,
            'default_duration_minutes' => 45,
        ]);

        $start = Carbon::now()->addHours(2);
        $booking = $this->service->createBooking($user, $machine, $start);

        $this->assertEquals(BookingStatus::CONFIRMED, $booking->status);
        $this->assertEquals(8, $user->fresh()->credits);
        $this->assertEquals($start->addMinutes(45)->toIso8601String(), $booking->end_time->toIso8601String());
    }

    public function test_cannot_book_when_slot_has_conflict(): void
    {
        $user = User::factory()->create(['credits' => 10]);
        $machine = Machine::factory()->create(['status' => MachineStatus::AVAILABLE]);

        $start = Carbon::now()->addHours(2);
        $this->service->createBooking($user, $machine, $start);

        $this->expectException(InvalidArgumentException::class);
        $this->service->createBooking($user, $machine, $start);
    }

    public function test_starting_cycle_updates_machine_and_dispatches_event(): void
    {
        Event::fake([CycleStarted::class]);

        $user = User::factory()->create();
        $machine = Machine::factory()->create(['status' => MachineStatus::AVAILABLE]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $this->service->startCycle($booking);

        $this->assertEquals(BookingStatus::IN_PROGRESS, $booking->fresh()->status);
        $this->assertEquals(MachineStatus::IN_USE, $machine->fresh()->status);
        Event::assertDispatched(CycleStarted::class);
    }

    public function test_completing_cycle_frees_machine_and_dispatches_event(): void
    {
        Event::fake([CycleCompleted::class]);

        $user = User::factory()->create();
        $machine = Machine::factory()->create(['status' => MachineStatus::IN_USE]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'status' => BookingStatus::IN_PROGRESS,
        ]);

        $this->service->completeCycle($machine);

        $this->assertEquals(MachineStatus::AVAILABLE, $machine->fresh()->status);
        $this->assertEquals(BookingStatus::COMPLETED, $booking->fresh()->status);
        Event::assertDispatched(CycleCompleted::class);
    }
}
