<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected static ?bool $hasCreditsColumn = null;
    protected static ?bool $hasTransactionsTable = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    public static function hasCreditsColumn(): bool
    {
        if (static::$hasCreditsColumn === null) {
            try {
                static::$hasCreditsColumn = Schema::hasColumn((new static)->getTable(), 'credits');
            } catch (\Throwable $e) {
                static::$hasCreditsColumn = false;
            }
        }
        return static::$hasCreditsColumn;
    }

    public static function hasTransactionsTable(): bool
    {
        if (static::$hasTransactionsTable === null) {
            try {
                static::$hasTransactionsTable = Schema::hasTable('transactions');
            } catch (\Throwable $e) {
                static::$hasTransactionsTable = false;
            }
        }
        return static::$hasTransactionsTable;
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

    public function getCreditsAttribute($value): int
    {
        return $this->weeklyRemainingLimit();
    }

    public function hasCredits(int $amount): bool
    {
        return $this->weeklyRemainingLimit() >= $amount;
    }

    public function deductCredits(int $amount, string $description, ?int $bookingId = null): ?Transaction
    {
        if (!$this->hasCredits($amount)) {
            throw new \InvalidArgumentException('Crédits insuffisants.');
        }

        if (static::hasCreditsColumn()) {
            try {
                $this->decrement('credits', $amount);
            } catch (\Throwable $e) {
                // Ignore if column is missing from real DB
            }
        }

        if (static::hasTransactionsTable()) {
            try {
                return $this->transactions()->create([
                    'amount' => -$amount,
                    'type' => 'booking_charge',
                    'description' => $description,
                    'booking_id' => $bookingId,
                ]);
            } catch (\Throwable $e) {
                // Ignore if transactions table schema differs or doesn't exist
            }
        }

        return null;
    }

    public function addCredits(int $amount, string $description, ?int $bookingId = null): ?Transaction
    {
        if (static::hasCreditsColumn()) {
            try {
                $this->increment('credits', $amount);
            } catch (\Throwable $e) {
                // Ignore if column is missing from real DB
            }
        }

        if (static::hasTransactionsTable()) {
            try {
                return $this->transactions()->create([
                    'amount' => $amount,
                    'type' => 'refund',
                    'description' => $description,
                    'booking_id' => $bookingId,
                ]);
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        return null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
