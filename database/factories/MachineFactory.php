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
            'color' => fake()->randomElement(['#4338ca', '#0d9488', '#2563eb', '#d97706', '#db2777', '#ea580c', '#059669', '#b45309', '#16a34a', '#475569', '#65a30d', '#9333ea', '#52525b']),
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
