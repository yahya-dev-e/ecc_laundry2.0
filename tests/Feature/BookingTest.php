<?php

namespace Tests\Feature;

use App\Enums\MachineStatus;
use App\Models\Machine;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_http_flow_charges_and_cancellation_retains_history_once(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $this->actingAs($user)->post('/bookings', ['machine_id' => $machine->id, 'start_time' => now()->addHour()->toIso8601String()])
            ->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $booking = Reservation::firstOrFail();
        $this->assertSame(420, $user->weeklyRemainingMinutes());
        $this->assertDatabaseHas('transactions', ['booking_id' => $booking->id, 'amount' => -60]);
        $this->post('/bookings/'.$booking->id.'/cancel', ['redirect_to' => 'https://evil.example/dashboard', 'reason' => 'Changement'])
            ->assertRedirect('/dashboard')->assertSessionHas('success');
        $this->assertNotNull($booking->fresh()->cancelled_at);
        $this->assertSame('Changement', $booking->fresh()->cancellation_reason);
        $this->post('/bookings/'.$booking->id.'/cancel')->assertSessionHas('status');
        $this->assertSame(1, DB::table('transactions')->where('type', 'refund')->count());
        $this->assertSame(480, $user->weeklyRemainingMinutes());
    }

    public function test_multi_slot_duplicate_and_conflict_leave_no_partial_booking(): void
    {
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $time = now()->addHours(2)->toIso8601String();
        $this->actingAs($user)->post('/bookings', ['machine_id' => $machine->id, 'start_times' => [$time, $time]])
            ->assertSessionHasErrors('start_times.0');
        $this->assertDatabaseCount('reservations', 0);
        app(BookingService::class)->createBooking(User::factory()->create(), $machine, now()->addHours(3));
        $this->post('/bookings', ['machine_id' => $machine->id, 'start_times' => [$time, now()->addHours(3)->toIso8601String()]])
            ->assertSessionHas('error');
        $this->assertSame(0, $user->reservations()->count());
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_students_cannot_cancel_or_start_another_persons_reservation(): void
    {
        $user = User::factory()->create();
        $booking = app(BookingService::class)->createBooking(User::factory()->create(), Machine::factory()->create(), now()->addHour());
        $this->actingAs($user)->post('/bookings/'.$booking->id.'/start')->assertForbidden();
        $this->post('/bookings/'.$booking->id.'/cancel')->assertForbidden();
    }

    public function test_slots_use_campus_timezone_cutoff_and_sunday_midnight_boundary(): void
    {
        config(['laundry.restrict_to_current_week' => true]);
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $this->travelTo(now()->endOfWeek()->setTime(22, 45));
        $response = $this->actingAs($user)->getJson('/machines/'.$machine->id.'/slots?date='.now()->toDateString())->assertOk();
        $slots = collect($response->json('slots'));
        $this->assertFalse($slots->firstWhere('start_time', '22:00')['is_available']);
        $this->assertTrue($slots->firstWhere('start_time', '23:00')['is_available']);
        $this->assertSame('Africa/Casablanca', $response->json('timezone'));
        $this->post('/bookings', ['machine_id' => $machine->id, 'start_time' => now()->setTime(23, 0)->toIso8601String()])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post('/bookings', ['machine_id' => $machine->id, 'start_time' => now()->addDay()->startOfDay()->toIso8601String()])
            ->assertSessionHasErrors('start_time');
    }

    public function test_rolling_window_on_sunday_includes_next_sunday_and_its_last_slot(): void
    {
        $this->travelTo(Carbon::parse('2026-10-11 10:15', 'Africa/Casablanca'));
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $this->actingAs($user)->get('/bookings/create')->assertViewHas('maxDate', '2026-10-18');
        $slots = $this->getJson('/machines/'.$machine->id.'/slots?date=2026-10-18')->assertOk()->json('slots');
        $this->assertTrue(collect($slots)->firstWhere('start_time', '23:00')['is_available']);
        foreach (['2026-10-12T12:00:00+01:00', '2026-10-18T23:00:00+01:00'] as $start) {
            $this->post('/bookings', ['machine_id' => $machine->id, 'start_time' => $start])
                ->assertSessionHasNoErrors()->assertSessionHas('success');
        }
        $this->post('/bookings', ['machine_id' => $machine->id, 'start_time' => '2026-10-19T00:00:00+01:00'])
            ->assertSessionHasErrors('start_time');
        $outside = $this->getJson('/machines/'.$machine->id.'/slots?date=2026-10-19')->json('slots');
        $this->assertFalse(collect($outside)->contains('is_available', true));
        try {
            app(BookingService::class)->createBooking($user, $machine, Carbon::parse('2026-10-18 23:30'), 60);
            $this->fail('Reservation crossing the window end accepted');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseCount('reservations', 2);
        }
    }

    public function test_real_pages_render_for_student_and_admin(): void
    {
        $user = User::factory()->create();
        foreach (['/dashboard', '/bookings/create', '/calendrier'] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['/utilisateurs', '/admin/users/create', '/admin/users/'.$user->id.'/edit', '/machines'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_machine_status_is_admin_only_and_cannot_fake_an_active_cycle(): void
    {
        $machine = Machine::factory()->create();
        $this->actingAs(User::factory()->create())->patch('/machines/'.$machine->id.'/status', ['status' => 'available'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch('/machines/'.$machine->id.'/status', ['status' => 'in-use'])
            ->assertSessionHasErrors('status');
        $this->patch('/machines/'.$machine->id.'/status', ['status' => MachineStatus::UNDER_MAINTENANCE->value])->assertSessionHasNoErrors();
        $this->assertSame(MachineStatus::UNDER_MAINTENANCE, $machine->fresh()->status);
    }
}
