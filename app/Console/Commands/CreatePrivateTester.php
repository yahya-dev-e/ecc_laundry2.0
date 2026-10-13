<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreatePrivateTester extends Command
{
    protected $signature = 'laundry:create-tester {email} {--admin}';

    protected $description = 'Provision an allowlisted account in the isolated private test database';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $database = config('database.connections.'.config('database.default').'.database');
        if (! config('private_test.enabled') || ! preg_match('/[_-]test$/', (string) $database)
            || ! in_array($email, config('private_test.emails'), true)) {
            $this->error('Requires private test mode, an allowlisted email and a database name ending in _test or -test.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('Account already exists; no changes made.');

            return self::FAILURE;
        }
        $name = $this->ask('Name');
        $password = $this->secret('Choose a private test password (at least 12 characters)');
        if (! $name || strlen((string) $password) < 12 || strlen((string) $password) > 72 || str_contains((string) $password, "\0") || $password !== $this->secret('Confirm password')) {
            $this->error('A name and matching password of at least 12 characters are required.');

            return self::FAILURE;
        }
        User::create(['name' => $name, 'email' => $email, 'password' => $password,
            'role' => $this->option('admin') ? 'admin' : 'student',
            'student_id' => 'TEST-'.bin2hex(random_bytes(8)), 'room_number' => 'TEST']);
        $this->info('Private tester created. No email sent.');

        return self::SUCCESS;
    }
}
