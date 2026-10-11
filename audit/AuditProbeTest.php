<?php
// These probes assert observed defects, not acceptance of those defects.
use App\Models\User;
use App\Models\Machine;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

final class AuditProbeTest extends \Illuminate\Foundation\Testing\TestCase
{
    public function createApplication() {
        $app = require __DIR__.'/bootstrap.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $app['env'] = 'testing';
        return $app;
    }
    protected function setUp(): void {
        parent::setUp();
        config(['session.driver'=>'array', 'mail.default'=>'array', 'hashing.bcrypt.rounds'=>4]);
        Carbon::setTestNow(Carbon::parse('2026-10-12 08:00:00', 'Africa/Casablanca'));
        DB::beginTransaction();
    }
    protected function tearDown(): void { DB::rollBack(); Carbon::setTestNow(); parent::tearDown(); }
    private function student(): User { return User::where('email','student@example.test')->firstOrFail(); }
    public function test_student_can_promote_self_to_admin(): void {
        $u=$this->student();
        $this->actingAs($u)->post('/admin/users/update',[
            'id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'student_id'=>$u->student_id,
            'room_number'=>$u->room_number,'role'=>'admin',
        ])->assertRedirect('/utilisateurs');
        $this->assertSame('admin',$u->fresh()->role);
    }
    public function test_student_can_read_admin_directory(): void {
        $this->actingAs($this->student())->get('/utilisateurs')->assertOk()->assertSee('admin@example.test');
    }
    public function test_actual_edit_form_payload_fails_required_fields(): void {
        $u=User::where('role','admin')->firstOrFail();
        $this->actingAs($u)->post('/admin/users/update',[
            'id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'role'=>'admin','weeklyLimit'=>100,'weeklyUsed'=>0,
        ])->assertSessionHasErrors(['student_id','room_number']);
    }
    public function test_failed_logins_have_no_rate_limit_after_twenty_attempts(): void {
        for($i=0;$i<20;$i++) $this->post('/login',['email'=>'student@example.test','password'=>'wrong'])->assertStatus(302);
    }
    public function test_welcome_notification_and_registration(): void {
        Notification::fake();
        $this->post('/register',[
            'name'=>'Audit New Student','email'=>'new@example.test','student_id'=>'AUDIT-NEW','room_number'=>'T2',
            'password'=>'Synthetic-password-42!','password_confirmation'=>'Synthetic-password-42!',
        ])->assertRedirect('/dashboard');
        $u=User::where('email','new@example.test')->firstOrFail();
        Notification::assertSentTo($u,\App\Notifications\WelcomeNotification::class);
        $this->assertNull($u->email_verified_at);
        $this->assertSame(8,(int)$u->getRawOriginal('credits'));
    }
    public function test_reset_notification_is_generated_and_reset_keeps_remember_token(): void {
        Notification::fake(); $u=$this->student(); $u->remember_token='synthetic-existing-remember-token'; $u->save();
        $this->post('/forgot-password',['email'=>$u->email])->assertSessionHas('status');
        $n=Notification::sent($u,\App\Notifications\ResetPasswordNotification::class)->first();
        $this->assertNotNull($n);
        $this->post('/reset-password',[
            'email'=>$u->email,'token'=>$n->token,'password'=>'Replacement-password-42!',
            'password_confirmation'=>'Replacement-password-42!',
        ])->assertRedirect('/login');
        $this->assertTrue(Hash::check('Replacement-password-42!',$u->fresh()->password));
        $this->assertSame('synthetic-existing-remember-token',$u->fresh()->remember_token);
        $this->post('/reset-password',[
            'email'=>$u->email,'token'=>$n->token,'password'=>'Another-password-42!',
            'password_confirmation'=>'Another-password-42!',
        ])->assertSessionHasErrors('email');
    }
    public function test_reset_expiry_is_enforced(): void {
        Notification::fake(); $u=$this->student();
        $this->post('/forgot-password',['email'=>$u->email]);
        $n=Notification::sent($u,\App\Notifications\ResetPasswordNotification::class)->first();
        Carbon::setTestNow(Carbon::now()->addMinutes(61));
        $this->post('/reset-password',[
            'email'=>$u->email,'token'=>$n->token,'password'=>'Replacement-password-42!',
            'password_confirmation'=>'Replacement-password-42!',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Audit-only-password-42!',$u->fresh()->password));
    }
    public function test_final_weekly_credit_cannot_be_spent(): void {
        $u=$this->student(); $m=Machine::where('name','AUDIT-WASHER')->firstOrFail();
        $service=app(\App\Services\BookingService::class);
        for($i=0;$i<7;$i++) $service->createBooking($u,$m,Carbon::now()->addHours(2+$i),60);
        $this->assertSame(1,$u->weeklyRemainingLimit());
        try { $service->createBooking($u,$m,Carbon::now()->addHours(10),60); $this->fail('Expected final-credit defect'); }
        catch(\InvalidArgumentException $e) { $this->assertStringContainsString('insuffisants',$e->getMessage()); }
        $this->assertSame(7,$u->reservations()->count());
    }
    public function test_booking_charge_ledger_is_missing(): void {
        $u=$this->student(); $m=Machine::where('name','AUDIT-WASHER')->firstOrFail();
        $b=app(\App\Services\BookingService::class)->createBooking($u,$m,Carbon::now()->addHours(2),60);
        $this->assertNotNull($b->id);
        $this->assertSame(0,$u->transactions()->count());
    }
    public function test_future_booking_can_start_machine_in_maintenance(): void {
        $u=$this->student(); $m=Machine::where('name','AUDIT-WASHER')->firstOrFail();
        $service=app(\App\Services\BookingService::class);
        $b=$service->createBooking($u,$m,Carbon::now()->addDays(2),60);
        $m->update(['status'=>\App\Enums\MachineStatus::MAINTENANCE]);
        $service->startCycle($b->fresh());
        $this->assertSame(\App\Enums\MachineStatus::IN_USE,$m->fresh()->status);
    }
    private function failMail(): void {
        config(['mail.default'=>'smtp','mail.mailers.smtp'=>[
            'transport'=>'smtp','scheme'=>'smtp','host'=>'127.0.0.1','port'=>1,'timeout'=>1,
        ]]);
    }
    public function test_reset_mail_failure_exposes_server_error(): void {
        $this->failMail();
        $this->post('/forgot-password',['email'=>'student@example.test'])->assertStatus(500);
        $this->post('/forgot-password',['email'=>'nobody@example.test'])->assertStatus(302)->assertSessionHas('status');
    }
    public function test_welcome_mail_failure_still_claims_email_was_sent(): void {
        $this->failMail();
        $this->post('/register',[
            'name'=>'Audit Failed Mail','email'=>'failedmail@example.test','student_id'=>'AUDIT-FAILED-MAIL','room_number'=>'T4',
            'password'=>'Synthetic-password-42!','password_confirmation'=>'Synthetic-password-42!',
        ])->assertRedirect('/dashboard')->assertSessionHas('success',fn($s)=>str_contains($s,'a été envoyé'));
        $this->assertNotNull(User::where('email','failedmail@example.test')->first());
    }
    public function test_multi_slot_error_keeps_earlier_booking(): void {
        $u=$this->student();$m=Machine::where('name','AUDIT-WASHER')->firstOrFail();$time=Carbon::now()->addHours(2)->toDateTimeString();
        $this->actingAs($u)->post('/bookings',['machine_id'=>$m->id,'start_times'=>[$time,$time]])->assertSessionHas('error');
        $this->assertSame(1,$u->reservations()->count());
    }
    public function test_future_cancellation_frees_other_active_cycle(): void {
        $u=$this->student();$m=Machine::where('name','AUDIT-WASHER')->firstOrFail();$service=app(\App\Services\BookingService::class);
        $b=$service->createBooking($u,$m,Carbon::now()->addHours(3),60);
        $m->update(['status'=>\App\Enums\MachineStatus::IN_USE]);
        $service->cancelBooking($b->fresh());
        $this->assertSame(\App\Enums\MachineStatus::AVAILABLE,$m->fresh()->status);
    }
    public function test_cancellation_accepts_external_redirect(): void {
        $u=$this->student();$m=Machine::where('name','AUDIT-WASHER')->firstOrFail();
        $b=app(\App\Services\BookingService::class)->createBooking($u,$m,Carbon::now()->addHours(3),60);
        $this->actingAs($u)->post('/bookings/'.$b->id.'/cancel',['redirect_to'=>'https://example.test/untrusted'])->assertRedirect('https://example.test/untrusted');
    }
    public function test_quota_reset_does_not_reset_displayed_quota(): void {
        $u=$this->student();$m=Machine::where('name','AUDIT-WASHER')->firstOrFail();
        app(\App\Services\BookingService::class)->createBooking($u,$m,Carbon::now()->addHours(3),60);
        $this->actingAs($u)->get('/reset-user-quota?id='.$u->id)->assertRedirect('/utilisateurs');
        $this->assertSame(7,$u->fresh()->weeklyRemainingLimit());
    }
    public function test_admin_update_duplicate_email_returns_server_error(): void {
        $u=User::where('email','admin@example.test')->firstOrFail();
        $this->actingAs($u)->post('/admin/users/update',[
            'id'=>$u->id,'name'=>$u->name,'email'=>'student@example.test','student_id'=>$u->student_id,
            'room_number'=>$u->room_number,'role'=>'admin',
        ])->assertStatus(500);
    }
    public function test_reset_link_uses_untrusted_request_host(): void {
        Notification::fake();$u=$this->student();
        $this->withServerVariables(['HTTP_HOST'=>'evil.example.test','SERVER_NAME'=>'evil.example.test'])
            ->post('http://evil.example.test/forgot-password',['email'=>$u->email])->assertStatus(302);
        $n=Notification::sent($u,\App\Notifications\ResetPasswordNotification::class)->first();
        $this->assertStringContainsString('http://evil.example.test/reset-password/',$n->toMail($u)->actionUrl);
    }
    public function test_seed_fails_on_missing_bookings_foreign_key_table(): void {
        try { \Illuminate\Support\Facades\Artisan::call('db:seed',['--force'=>true]); $this->fail('Expected missing bookings parent table'); }
        catch(\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('bookings',$e->getMessage()); }
    }
}
