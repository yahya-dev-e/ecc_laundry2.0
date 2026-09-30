<?php

namespace Database\Seeders;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\Machine;
use Illuminate\Database\Seeder;

class MachineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Strictly matching the frozen MySQL schema:
     * id, name, type ('washing-machine', 'dryer'), status, color, timestamps.
     */
    public function run(): void
    {
        $machines = [
            // Washers (Machines à laver)
            [
                'name' => 'ML1-OM',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#e53935',
            ],
            [
                'name' => 'ML2-OM',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::IN_USE,
                'color' => '#00e676',
            ],
            [
                'name' => 'ML1-PE',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#2979ff',
            ],
            [
                'name' => 'ML2-PE',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::RESERVED,
                'color' => '#ffd600',
            ],
            [
                'name' => 'ML3-PE',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::RESERVED,
                'color' => '#ff007f',
            ],
            [
                'name' => 'ML4-PE',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#ff9100',
            ],
            [
                'name' => 'ML3-OM',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#004d40',
            ],

            // Dryers (Sèche-linge)
            [
                'name' => 'SL1-OM',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#4e342e',
            ],
            [
                'name' => 'SL2-OM',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#4caf50',
            ],
            [
                'name' => 'SL1-PE',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::IN_USE,
                'color' => '#1a237e',
            ],
            [
                'name' => 'SL2-PE',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#827717',
            ],
            [
                'name' => 'SL3-PE',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#8e24aa',
            ],
            [
                'name' => 'SL3-OM',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#212121',
            ],
        ];

        foreach ($machines as $machine) {
            Machine::updateOrCreate(['name' => $machine['name']], $machine);
        }
    }
}
