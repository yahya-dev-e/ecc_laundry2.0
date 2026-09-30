<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'machine_id',
        'status',
        'start_time',
        'end_time',
        'credits_spent',
        'cancellation_reason',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'credits_spent' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::IN_PROGRESS);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
                     ->where('start_time', '>=', Carbon::now());
    }

    public function scopeExpiredPending(Builder $query, int $graceMinutes = 15): Builder
    {
        $cutoff = Carbon::now()->subMinutes($graceMinutes);

        return $query->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
                     ->where('start_time', '<=', $cutoff);
    }

    public function isPendingOrConfirmed(): bool
    {
        return in_array($this->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED]);
    }
}
