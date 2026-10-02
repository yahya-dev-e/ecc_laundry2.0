<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Machine;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed demo student user
        $student = User::updateOrCreate(
            ['email' => 'student@ecc.edu'],
            [
                'name' => 'Alex Rivera',
                'password' => Hash::make('password123'),
                'credits' => 8,
                'role' => 'student',
                'student_id' => 'STU-98241',
                'room_number' => 'Hall 4 - Room 312',
            ]
        );

        // 2. Seed admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@ecc.edu'],
            [
                'name' => 'Campus Facilities Admin',
                'password' => Hash::make('admin123'),
                'credits' => 100,
                'role' => 'admin',
                'student_id' => 'FAC-001',
                'room_number' => 'Facilities Office 101',
            ]
        );

        // 3. Seed Machines
        $this->call(MachineSeeder::class);

        // 4. Create sample initial top-up transaction
        Transaction::firstOrCreate(
            [
                'user_id' => $student->id,
                'type' => 'topup',
                'amount' => 20,
            ],
            [
                'description' => 'Semester Laundry Credit Top-Up Package',
                'reference_id' => 'TXN-TOPUP-001',
            ]
        );

        // 5. Seed an active reservation for machine ML2-OM
        $machineW2 = Machine::where('name', 'ML2-OM')->first();
        if ($machineW2) {
            $booking = Booking::create([
                'user_id' => $student->id,
                'machine_id' => $machineW2->id,
                'start_time' => Carbon::now()->subMinutes(23),
                'end_time' => Carbon::now()->addMinutes(22),
                'notified_start' => true,
                'notified_end' => false,
                'weekly_session_limit_remaining' => 6,
            ]);

            Transaction::create([
                'user_id' => $student->id,
                'booking_id' => $booking->id,
                'amount' => -2,
                'type' => 'booking_charge',
                'description' => "Cycle started for {$machineW2->name}",
            ]);
        }
    }
}
