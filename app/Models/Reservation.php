<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    /**
     * Exact MySQL database table name.
     *
     * @var string
     */
    protected $table = 'reservations';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     * STRICTLY MATCHING REAL DATABASE COLUMNS ONLY (NO 'status', NO 'credits_spent')
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'machine_id',
        'start_time',
        'end_time',
        'notified_start',
        'notified_end',
        'weekly_session_limit_remaining',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'notified_start' => 'boolean',
            'notified_end' => 'boolean',
            'weekly_session_limit_remaining' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Time-Derived Query Scopes (NO 'status' COLUMN QUERIES!)
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: In Progress (start_time <= now AND end_time >= now).
     */
    public function scopeInProgress(Builder $query): Builder
    {
        $now = Carbon::now();
        return $query->where('start_time', '<=', $now)
                     ->where('end_time', '>=', $now);
    }

    /**
     * Scope: Upcoming (start_time > now).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_time', '>', Carbon::now());
    }

    /**
     * Scope: Completed (end_time < now).
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('end_time', '<', Carbon::now());
    }

    /**
     * Scope: Active or Upcoming (end_time >= now).
     */
    public function scopeActiveOrUpcoming(Builder $query): Builder
    {
        return $query->where('end_time', '>=', Carbon::now());
    }

    /*
    |--------------------------------------------------------------------------
    | Dynamic Accessors & Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Dynamically derive status from start_time and end_time.
     * Returns 'in_progress', 'upcoming', or 'completed' based on timestamps.
     */
    public function getStatusAttribute(): string
    {
        $now = Carbon::now();

        if ($this->end_time && $this->end_time->lessThan($now)) {
            return 'completed';
        }

        if ($this->start_time && $this->end_time && $this->start_time->lessThanOrEqualTo($now) && $this->end_time->greaterThanOrEqualTo($now)) {
            return 'in_progress';
        }

        return 'upcoming';
    }

    /**
     * Helper: Check if reservation is currently in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Helper: Check if reservation is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->status === 'upcoming';
    }

    /**
     * Helper: Check if reservation is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Helper: Check if reservation cycle can be started.
     */
    public function canBeStarted(): bool
    {
        return $this->status === 'upcoming' || $this->status === 'in_progress';
    }

    /**
     * Helper: Check if reservation can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status === 'upcoming';
    }

    /**
     * Dynamic Credits Spent: 1 hour = 1 credit.
     */
    public function getCreditsSpentAttribute(): int
    {
        if ($this->start_time && $this->end_time) {
            $hours = (int) round($this->start_time->diffInMinutes($this->end_time) / 60);
            return max(1, $hours);
        }
        return 1;
    }
}
