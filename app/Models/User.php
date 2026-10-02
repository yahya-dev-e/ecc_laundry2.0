<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'credits',
        'role',
        'student_id',
        'room_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'credits' => 'integer',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function weeklyLimit(): int
    {
        return $this->isAdmin() ? (int) config('laundry.admin_weekly_hours_limit', 100) : (int) config('laundry.weekly_hours_limit', 8);
    }

    public function weeklyRemainingLimit(): int
    {
        $startOfWeek = \Carbon\Carbon::now()->startOfWeek();
        $endOfWeek = \Carbon\Carbon::now()->endOfWeek();

        $usedCount = $this->reservations()
            ->where('start_time', '>=', $startOfWeek)
            ->where('start_time', '<=', $endOfWeek)
            ->count();

        return max(0, $this->weeklyLimit() - $usedCount);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function hasCredits(int $amount): bool
    {
        return $this->credits >= $amount;
    }

    public function deductCredits(int $amount, string $description, ?int $bookingId = null): Transaction
    {
        if (!$this->hasCredits($amount)) {
            throw new \InvalidArgumentException('Insufficient credits.');
        }

        $this->decrement('credits', $amount);

        return $this->transactions()->create([
            'amount' => -$amount,
            'type' => 'booking_charge',
            'description' => $description,
            'booking_id' => $bookingId,
        ]);
    }

    public function addCredits(int $amount, string $description, ?int $bookingId = null): Transaction
    {
        $this->increment('credits', $amount);

        return $this->transactions()->create([
            'amount' => $amount,
            'type' => 'refund',
            'description' => $description,
            'booking_id' => $bookingId,
        ]);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
