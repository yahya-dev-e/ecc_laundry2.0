<?php

namespace Database\Seeders;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\Machine;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MachineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $machines = [
            // Washers (Floor 1 - Main Laundry Room)
            [
                'name' => 'Speed Queen Washer A1',
                'code' => 'W-01',
                'type' => MachineType::WASHER,
                'status' => MachineStatus::AVAILABLE,
                'capacity_kg' => 9.0,
                'cost_per_cycle' => 2,
                'default_duration_minutes' => 45,
                'location' => 'Block A, Level 1 Laundry Bay',
                'current_cycle_ends_at' => null,
                'last_maintenance_at' => Carbon::now()->subDays(12),
                'notes' => 'Heavy duty inverter motor, hot & cold wash options.',
            ],
            [
                'name' => 'Speed Queen Washer A2',
                'code' => 'W-02',
                'type' => MachineType::WASHER,
                'status' => MachineStatus::IN_USE,
                'capacity_kg' => 9.0,
                'cost_per_cycle' => 2,
                'default_duration_minutes' => 45,
                'location' => 'Block A, Level 1 Laundry Bay',
                'current_cycle_ends_at' => Carbon::now()->addMinutes(22),
                'last_maintenance_at' => Carbon::now()->subDays(20),
                'notes' => 'Currently running Cotton 40°C cycle.',
            ],
            [
                'name' => 'Bosch Maxx Washer B1',
                'code' => 'W-03',
                'type' => MachineType::WASHER,
                'status' => MachineStatus::AVAILABLE,
                'capacity_kg' => 8.0,
                'cost_per_cycle' => 2,
                'default_duration_minutes' => 45,
                'location' => 'Block B, Level 2 Utility Room',
                'current_cycle_ends_at' => null,
                'last_maintenance_at' => Carbon::now()->subDays(5),
                'notes' => 'EcoSilence drive, quiet wash mode.',
            ],
            [
                'name' => 'Bosch Maxx Washer B2',
                'code' => 'W-04',
                'type' => MachineType::WASHER,
                'status' => MachineStatus::RESERVED,
                'capacity_kg' => 8.0,
                'cost_per_cycle' => 2,
                'default_duration_minutes' => 45,
                'location' => 'Block B, Level 2 Utility Room',
                'current_cycle_ends_at' => null,
                'last_maintenance_at' => Carbon::now()->subDays(30),
                'notes' => 'Reserved for upcoming slot.',
            ],
            [
                'name' => 'LG Commercial Inverter W5',
                'code' => 'W-05',
                'type' => MachineType::WASHER,
                'status' => MachineStatus::MAINTENANCE,
                'capacity_kg' => 10.5,
                'cost_per_cycle' => 3,
                'default_duration_minutes' => 50,
                'location' => 'Block C, Ground Floor Wash Center',
                'current_cycle_ends_at' => null,
                'last_maintenance_at' => Carbon::now()->subDays(2),
                'notes' => 'Water inlet filter replacement in progress.',
            ],
            [
                'name' => 'LG Commercial Inverter W6',
                'code' => 'W-06',
                'type' => MachineType::WASHER,
                'status' => MachineStatus::AVAILABLE,
                'capacity_kg' => 10.5,
                'cost_per_cycle' => 3,
                'default_duration_minutes' => 50,
                'location' => 'Block C, Ground Floor Wash Center',
                'current_cycle_ends_at' => null,
                'last_maintenance_at' => Carbon::now()->subDays(14),
                'notes' => 'Large capacity unit for blankets and bedding.',
            ],

            // Dryers (Floor 1 & Floor 2)
            [
                'name' => 'Huebsch Commercial Dryer D1',
                'code' => 'D-01',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'capacity_kg' => 9.5,
                'cost_per_cycle' => 2,
                'default_duration_minutes' => 40,
                'location' => 'Block A, Level 1 Laundry Bay',
                'current_cycle_ends_at' => null,
                'last_maintenance_at' => Carbon::now()->subDays(10),
                'notes' => 'Axial airflow technology, high efficiency.',
            ],
            [
                'name' => 'Huebsch Commercial Dryer D2',
                'code' => 'D-02',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::IN_USE,
                'capacity_kg' => 9.5,
                'cost_per_cycle' => 2,
                'default_duration_minutes' => 40,
                'location' => 'Block A, Level 1 Laundry Bay',
                'current_cycle_ends_at' => Carbon::now()->addMinutes(14),
                'last_maintenance_at' => Carbon::now()->subDays(18),
                'notes' => 'High temp drying cycle active.',
            ],
            [
                'name' => 'Electrolux Heat Pump Dryer D3',
                'code' => 'D-03',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'capacity_kg' => 8.0,
                'cost_per_cycle' => 2,
                'default_duration_minutes' => 40,
                'location' => 'Block B, Level 2 Utility Room',
                'current_cycle_ends_at' => null,
                'last_maintenance_at' => Carbon::now()->subDays(7),
                'notes' => 'Gentle heat pump sensor drying.',
            ],
            [
                'name' => 'Electrolux Heat Pump Dryer D4',
                'code' => 'D-04',
                'type' => MachineType::DRYER,
                'status' => MachineStatus::AVAILABLE,
                'capacity_kg' => 8.0,
                'cost_per_cycle' => 2,
                'default_duration_minutes' => 40,
                'location' => 'Block B, Level 2 Utility Room',
                'current_cycle_ends_at' => null,
                'last_maintenance_at' => Carbon::now()->subDays(25),
                'notes' => 'Clean lint filter before use.',
            ],
        ];

        foreach ($machines as $machine) {
            Machine::updateOrCreate(['code' => $machine['code']], $machine);
        }
    }
}
