<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\SafePassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdministrator extends Command
{
    protected $signature = 'laundry:create-admin';

    protected $description = 'Create the initial administrator interactively, without public default credentials';

    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Name'),
            'email' => strtolower(trim((string) $this->ask('Email'))),
            'student_id' => $this->ask('Staff / student ID'),
            'room_number' => $this->ask('Office / room'),
            'password' => $this->secret('Password (at least 12 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users',
            'student_id' => 'nullable|string|max:50|unique:users', 'room_number' => 'nullable|string|max:50',
            'password' => ['required', 'string', 'min:12', 'confirmed', new SafePassword],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create(collect($data)->except('password_confirmation')->all() + ['role' => 'admin']);
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
