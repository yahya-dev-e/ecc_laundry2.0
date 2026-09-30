<?php

namespace Database\Factories;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

class MachineFactory extends Factory
{
    protected $model = Machine::class;

    public function definition(): array
    {
        $type = fake()->randomElement([MachineType::WASHER, MachineType::DRYER]);
        $prefix = $type === MachineType::WASHER ? 'W' : 'D';
        $number = fake()->unique()->numberBetween(1, 99);

        return [
            'name' => ($type === MachineType::WASHER ? 'Eco Wash ' : 'Turbo Dry ') . sprintf('%02d', $number),
            'code' => sprintf('%s-%02d', $prefix, $number),
            'type' => $type,
            'status' => MachineStatus::AVAILABLE,
            'capacity_kg' => fake()->randomElement([7.5, 8.5, 10.0]),
            'cost_per_cycle' => 2,
            'default_duration_minutes' => $type === MachineType::WASHER ? 45 : 40,
            'location' => 'Block ' . fake()->randomElement(['A', 'B', 'C']) . ' - Level ' . fake()->numberBetween(1, 3),
            'current_cycle_ends_at' => null,
            'last_maintenance_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'notes' => null,
        ];
    }

    public function washer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MachineType::WASHER,
            'default_duration_minutes' => 45,
        ]);
    }

    public function dryer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MachineType::DRYER,
            'default_duration_minutes' => 40,
        ]);
    }

    public function inUse(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MachineStatus::IN_USE,
            'current_cycle_ends_at' => now()->addMinutes(25),
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MachineStatus::MAINTENANCE,
            'notes' => 'Scheduled sensor inspection',
        ]);
    }
}
