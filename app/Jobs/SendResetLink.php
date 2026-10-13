<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\MailDeliveryWindow;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendResetLink implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(public int $userId, public int $linkId, public string $token) {}

    public function handle(): void
    {
        $link = DB::table('password_reset_links')->where('id', $this->linkId)->whereNull('consumed_at')->where('expires_at', '>', now())
            ->where('created_at', '>=', MailDeliveryWindow::cutoff())->first();
        if ($link && ($user = User::find($this->userId))) {
            $user->notify(new ResetPasswordNotification($this->token));
        }
    }
}
