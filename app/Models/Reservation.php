<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    /**
     * Exact database table name in MySQL.
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
     * Returns a BookingStatus Enum with value, label(), badgeClass(), canBeStarted(), etc.
     */
    public function getStatusAttribute(): BookingStatus
    {
        $now = Carbon::now();

        if (!$this->start_time || !$this->end_time) {
            return BookingStatus::CONFIRMED;
        }

        if ($this->end_time->isPast()) {
            return BookingStatus::COMPLETED;
        }

        if ($this->start_time->isPast() && $this->end_time->isFuture()) {
            return BookingStatus::IN_PROGRESS;
        }

        return BookingStatus::CONFIRMED;
    }

    /**
     * Helper: Check if reservation is currently in progress.
     */
    public function isInProgress(): bool
    {
        return $this->start_time && $this->end_time && Carbon::now()->between($this->start_time, $this->end_time);
    }

    /**
     * Helper: Check if reservation is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->start_time && $this->start_time->isFuture();
    }

    /**
     * Helper: Check if reservation is completed.
     */
    public function isCompleted(): bool
    {
        return $this->end_time && $this->end_time->isPast();
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
