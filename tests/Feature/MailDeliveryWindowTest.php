<?php

namespace Tests\Feature;

use App\Events\CycleCompleted;
use App\Jobs\CheckActiveCycles;
use App\Jobs\SendResetLink;
use App\Listeners\SendCycleAlertNotification;
use App\Models\Machine;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailDeliveryWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_delivery_discards_old_mail_but_keeps_recent_mail(): void
    {
        config(['mail.default' => 'array']);
        $user = User::factory()->create(['created_at' => now()->subMinutes(16)]);
        Notification::sendNow($user, new WelcomeNotification);
        $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();
        $this->assertCount(0, $transport->messages());
        $user->created_at = now()->subMinutes(5);
        Notification::sendNow($user, new WelcomeNotification);
        $this->assertCount(1, $transport->messages());
    }

    public function test_reset_job_skips_stale_delivery_without_revoking_a_valid_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $id = DB::table('password_reset_links')->insertGetId(['user_id' => $user->id, 'token_hash' => hash('sha256', 'token'),
            'created_at' => now()->subMinutes(16), 'expires_at' => now()->addMinutes(44)]);
        (new SendResetLink($user->id, $id, 'token'))->handle();
        Notification::assertNothingSent();
        $this->assertNull(DB::table('password_reset_links')->where('id', $id)->value('consumed_at'));
        DB::table('password_reset_links')->where('id', $id)->update(['created_at' => now()->subMinutes(5)]);
        (new SendResetLink($user->id, $id, 'token'))->handle();
        Notification::assertCount(1);
    }

    public function test_late_scheduler_completion_does_not_generate_hours_old_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $machine = Machine::factory()->create();
        $service = app(BookingService::class);
        $booking = $service->createBooking($user, $machine, now()->addHour());
        $this->travelTo($booking->start_time);
        $service->startCycle($booking);
        $this->travelTo($booking->end_time->copy()->addHours(2));
        $service->completeCycle($machine);
        app(SendCycleAlertNotification::class)->handle(new CycleCompleted($machine->fresh(), $booking->fresh()));
        Notification::assertNothingSent();
        $this->assertFalse($booking->fresh()->notified_end);
        Event::fake([CycleCompleted::class]);
        (new CheckActiveCycles)->handle($service);
        Event::assertNotDispatched(CycleCompleted::class);
        $this->assertNotNull($booking->fresh()->completed_at);
    }
}
