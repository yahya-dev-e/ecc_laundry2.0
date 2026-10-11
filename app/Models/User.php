<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'student_id', 'room_number'];

    protected $hidden = ['password', 'remember_token', 'session_version'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'session_version' => 'integer',
            'weekly_limit_minutes' => 'integer'];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function bookings(): HasMany
    {
        return $this->reservations();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public static function hasCreditsColumn(): bool
    {
        return true;
    }

    public static function hasTransactionsTable(): bool
    {
        return true;
    }

    public function scopeWithWeeklyUsage($query)
    {
        return $query->withSum(['reservations as reserved_minutes' => fn ($q) => $q
            ->whereNull('cancelled_at')->whereNull('released_at')->where('quota_waived', false)
            ->where('start_time', '>=', now()->startOfWeek())
            ->where('start_time', '<', now()->startOfWeek()->addWeek())], 'quota_minutes');
    }

    public function weeklyLimitMinutes(): int
    {
        return $this->weekly_limit_minutes ?? (int) (config($this->isAdmin()
            ? 'laundry.admin_weekly_hours_limit' : 'laundry.weekly_hours_limit', $this->isAdmin() ? 100 : 8) * 60);
    }

    public function weeklyLimit(): float
    {
        return $this->weeklyLimitMinutes() / 60;
    }

    public function weeklyUsedMinutes(?CarbonInterface $date = null): int
    {
        $week = ($date ?? now())->copy()->startOfWeek();
        $reserved = array_key_exists('reserved_minutes', $this->attributes) && $week->equalTo(now()->startOfWeek())
            ? $this->attributes['reserved_minutes'] : $this->reservations()->whereNull('cancelled_at')
                ->whereNull('released_at')->where('quota_waived', false)->where('start_time', '>=', $week)
                ->where('start_time', '<', $week->copy()->addWeek())->sum('quota_minutes');

        return max(0, (int) $reserved);
    }

    public function weeklyRemainingMinutes(?CarbonInterface $date = null): int
    {
        return max(0, $this->weeklyLimitMinutes() - $this->weeklyUsedMinutes($date));
    }

    public function weeklyRemainingLimit(): float
    {
        return round($this->weeklyRemainingMinutes() / 60, 2);
    }

    public function getCreditsAttribute($value): float
    {
        return $this->weeklyRemainingLimit();
    }

    public function hasCredits(float $amount): bool
    {
        return $this->weeklyRemainingMinutes() >= (int) ceil($amount * 60);
    }
}
