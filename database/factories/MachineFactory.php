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
        $type = fake()->randomElement([MachineType::WASHING_MACHINE, MachineType::DRYER]);
        $prefix = $type === MachineType::WASHING_MACHINE ? 'ML' : 'SL';
        $number = fake()->unique()->numberBetween(1, 99);
        $location = fake()->randomElement(['OM', 'PE']);

        return [
            'name' => sprintf('%s%d-%s', $prefix, $number, $location),
            'type' => $type,
            'status' => MachineStatus::AVAILABLE,
            'color' => fake()->randomElement(['#e53935', '#00e676', '#2979ff', '#ffd600', '#ff007f', '#ff9100', '#004d40', '#4e342e', '#4caf50', '#1a237e', '#827717', '#8e24aa', '#212121']),
        ];
    }

    public function washer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MachineType::WASHING_MACHINE,
        ]);
    }

    public function dryer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MachineType::DRYER,
        ]);
    }

    public function inUse(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MachineStatus::IN_USE,
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MachineStatus::UNDER_MAINTENANCE,
        ]);
    }
}
