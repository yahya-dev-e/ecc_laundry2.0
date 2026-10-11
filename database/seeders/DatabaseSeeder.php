<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MachineSeeder::class);
        if (! filter_var(env('DEMO_SEED_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Demo accounts are forbidden in this environment.');
        }
        $password = (string) env('DEMO_PASSWORD');
        if (strlen($password) < 12) {
            throw new \RuntimeException('Set a private DEMO_PASSWORD of at least 12 characters.');
        }
        foreach (['student', 'admin'] as $role) {
            User::firstOrCreate(['email' => $role.'@example.test'], [
                'name' => 'Demo '.ucfirst($role), 'password' => $password, 'role' => $role,
                'student_id' => 'DEMO-'.strtoupper($role), 'room_number' => 'Demo',
            ]);
        }
    }
}
