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
                'color' => '#4338ca',
            ],
            [
                'name' => 'ML2-OM',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::IN_USE,
                'color' => '#0d9488',
            ],
            [
                'name' => 'ML1-PE',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#2563eb',
            ],
            [
                'name' => 'ML2-PE',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#d97706',
            ],
            [
                'name' => 'ML3-PE',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#db2777',
            ],
            [
                'name' => 'ML4-PE',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#ea580c',
            ],
            [
                'name' => 'ML3-OM',
                'type' => MachineType::WASHING_MACHINE,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#059669',
            ],

            // Dryers (Sèche-linge)
            [
                'name' => 'SL1-OM',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#b45309',
            ],
            [
                'name' => 'SL2-OM',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#16a34a',
            ],
            [
                'name' => 'SL1-PE',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::IN_USE,
                'color' => '#475569',
            ],
            [
                'name' => 'SL2-PE',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#65a30d',
            ],
            [
                'name' => 'SL3-PE',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#9333ea',
            ],
            [
                'name' => 'SL3-OM',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'color' => '#52525b',
            ],
        ];

        foreach ($machines as $machine) {
            Machine::updateOrCreate(['name' => $machine['name']], $machine);
        }
    }
}
