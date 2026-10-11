<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $table = 'reservations';

    protected $fillable = ['user_id', 'machine_id', 'start_time', 'end_time', 'quota_minutes',
        'notified_start', 'notified_end', 'weekly_session_limit_remaining', 'started_at',
        'completed_at', 'cancelled_at', 'released_at', 'cancellation_reason'];

    protected function casts(): array
    {
        return array_fill_keys(['start_time', 'end_time', 'started_at', 'completed_at', 'cancelled_at', 'released_at'], 'datetime')
            + ['notified_start' => 'boolean', 'notified_end' => 'boolean', 'quota_minutes' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function scopeOccupying($query)
    {
        return $query->whereNull('cancelled_at')->whereNull('released_at');
    }

    public function scopeInProgress($query)
    {
        return $query->occupying()->whereNotNull('started_at')->whereNull('completed_at');
    }

    public function scopeUpcoming($query)
    {
        return $query->occupying()->whereNull('started_at')->where('end_time', '>', now());
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeActiveOrUpcoming($query)
    {
        return $query->occupying()->where('end_time', '>', now());
    }

    public function getStatusAttribute(): string
    {
        if ($this->cancelled_at) {
            return 'cancelled';
        }
        if ($this->released_at) {
            return 'no_show';
        }
        if ($this->completed_at) {
            return 'completed';
        }
        if ($this->started_at) {
            return 'in_progress';
        }

        return $this->end_time?->isPast() ? 'no_show' : 'upcoming';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isUpcoming(): bool
    {
        return $this->status === 'upcoming';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function canBeStarted(): bool
    {
        return ! $this->started_at && ! $this->cancelled_at && ! $this->released_at && ! $this->completed_at
            && $this->start_time && $this->end_time
            && now()->betweenIncluded($this->start_time, $this->start_time->copy()->addMinutes(config('laundry.grace_period_minutes', 15)))
            && $this->end_time->isFuture();
    }

    public function canBeCancelled(): bool
    {
        return ! $this->started_at && ! $this->completed_at && ! $this->cancelled_at && ! $this->released_at && $this->start_time?->isFuture();
    }

    public function getCreditsSpentAttribute(): float
    {
        return $this->quota_minutes / 60;
    }
}
