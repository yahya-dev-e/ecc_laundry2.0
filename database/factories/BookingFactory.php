<?php

namespace Database\Factories;

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
            'start_time' => $start,
            'end_time' => $end,
            'notified_start' => false,
            'notified_end' => false,
            'weekly_session_limit_remaining' => 8,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => now()->subMinutes(15),
            'end_time' => now()->addMinutes(30),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHours(1)->subMinutes(15),
        ]);
    }
}
