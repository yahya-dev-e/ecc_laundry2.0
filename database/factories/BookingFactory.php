<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Machine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = Carbon::now()->addHours(fake()->numberBetween(1, 48));
        $end = (clone $start)->addMinutes(45);

        return [
            'user_id' => User::factory(),
            'machine_id' => Machine::factory(),
            'status' => BookingStatus::CONFIRMED,
            'start_time' => $start,
            'end_time' => $end,
            'credits_spent' => 2,
            'cancellation_reason' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::IN_PROGRESS,
            'start_time' => now()->subMinutes(15),
            'end_time' => now()->addMinutes(30),
            'started_at' => now()->subMinutes(15),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::COMPLETED,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHours(1)->subMinutes(15),
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHours(1)->subMinutes(15),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CANCELLED,
            'cancellation_reason' => 'User changed schedule',
        ]);
    }
}
