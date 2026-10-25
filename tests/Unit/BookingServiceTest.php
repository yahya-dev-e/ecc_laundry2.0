<?php

namespace Tests\Unit;

use App\Enums\MachineStatus;
use App\Events\CycleCompleted;
use App\Listeners\SendCycleAlertNotification;
use App\Models\Machine;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\CycleFinishedNotification;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BookingService
    {
        return app(BookingService::class);
    }

    public function test_last_credit_is_usable_and_minutes_are_exact(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        Reservation::create(['user_id' => $user->id, 'machine_id' => $machine->id,
            'start_time' => now()->startOfWeek()->addHour(), 'end_time' => now()->startOfWeek()->addHours(8),
            'quota_minutes' => 420, 'completed_at' => now()->subDay()]);
        $this->service()->createBooking($user, $machine, now()->addHour(), 20);
        $this->service()->createBooking($user, $machine, now()->addHours(2), 40);
        $this->assertSame(0, $user->weeklyRemainingMinutes());
        $this->assertSame(-60, (int) $user->transactions()->sum('amount'));
        try {
            $this->service()->createBooking($user, $machine, now()->addHours(3), 20);
            $this->fail('Quota exceeded');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseCount('reservations', 3);
        }
    }

    public function test_batch_allows_more_than_three_and_rolls_back_on_ledger_failure(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $this->service()->createBookings($user, $machine, [now()->addHour(), now()->addHours(2), now()->addHours(3), now()->addHours(4)]);
        $this->assertDatabaseCount('reservations', 4);
        Transaction::creating(fn () => throw new \RuntimeException('Ledger unavailable'));
        try {
            $this->service()->createBookings($user, $machine, [now()->addHours(5), now()->addHours(6)]);
            $this->fail('Ledger failure swallowed');
        } catch (\RuntimeException $e) {
            $this->assertSame('Ledger unavailable', $e->getMessage());
        } finally {
            Transaction::flushEventListeners();
        }
        $this->assertDatabaseCount('reservations', 4);
        $this->assertDatabaseCount('transactions', 4);
    }

    public function test_cannot_start_early_or_during_maintenance(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $booking = $this->service()->createBooking($user, $machine, now()->addHour());
        try {
            $this->service()->startCycle($booking);
            $this->fail('Early start accepted');
        } catch (\InvalidArgumentException) {
        }
        $this->travelTo(now()->addHour());
        $machine->update(['status' => MachineStatus::UNDER_MAINTENANCE]);
        try {
            $this->service()->startCycle($booking);
            $this->fail('Maintenance start accepted');
        } catch (\InvalidArgumentException) {
        }
        $this->assertNull($booking->fresh()->started_at);
    }

    public function test_future_cancellation_preserves_another_active_cycle_and_completion_has_recipient(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $running = $this->service()->createBooking($user, $machine, now()->addHour());
        $future = $this->service()->createBooking($user, $machine, now()->addHours(3));
        $this->travelTo(now()->addHour());
        $this->service()->startCycle($running);
        $this->service()->cancelBooking($future);
        $this->assertSame($running->id, $machine->fresh()->active_reservation_id);
        $this->assertSame(MachineStatus::IN_USE, $machine->fresh()->status);
        $this->travelTo(now()->addHour());
        $this->service()->completeCycle($machine);
        $this->assertNotNull($running->fresh()->completed_at);
        $this->assertNull($machine->fresh()->active_reservation_id);
        // RefreshDatabase wraps the test: execute the after-commit listener explicitly.
        $listener = app(SendCycleAlertNotification::class);
        $listener->handle(new CycleCompleted($machine->fresh(), $running->fresh()));
        $listener->handle(new CycleCompleted($machine->fresh(), $running->fresh()));
        Notification::assertSentToTimes($user, CycleFinishedNotification::class, 1);
    }

    public function test_no_show_is_released_even_after_end_and_only_refunded_once(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $booking = $this->service()->createBooking($user, $machine, now()->addHour());
        $this->travelTo(now()->addHours(3));
        $this->assertSame(1, $this->service()->releaseExpiredBookings());
        $this->assertSame(0, $this->service()->releaseExpiredBookings());
        $this->assertNotNull($booking->fresh()->released_at);
        $this->assertSame(480, $user->weeklyRemainingMinutes());
        $this->assertSame(1, DB::table('transactions')->where('type', 'refund')->count());
    }

    public function test_rolling_window_checks_the_target_weeks_quota(): void
    {
        config(['laundry.restrict_to_current_week' => false]);
        $user = User::factory()->create();
        $user->forceFill(['weekly_limit_minutes' => 60])->save();
        $machine = Machine::factory()->create();
        $this->service()->createBooking($user, $machine, now()->addHour(), 20);
        $nextWeek = now()->startOfWeek()->addWeek()->addHours(10);
        $this->service()->createBooking($user, $machine, $nextWeek, 60);
        $this->assertSame(40, $user->weeklyRemainingMinutes());
        $this->assertSame(0, $user->weeklyRemainingMinutes($nextWeek));
        try {
            $this->service()->createBooking($user, $machine, $nextWeek->copy()->addHours(2), 20);
            $this->fail('Next-week quota overspent');
        } catch (\InvalidArgumentException) {
            $this->assertSame(2, $user->reservations()->count());
        }
    }
}
