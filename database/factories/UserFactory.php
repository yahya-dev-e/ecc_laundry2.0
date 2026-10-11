<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(), 'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => null, 'password' => Hash::make('password-for-tests'),
            'student_id' => fake()->unique()->bothify('TEST-########'),
            'room_number' => 'TEST-101', 'role' => 'student', 'session_version' => 0,
            'remember_token' => Str::random(10),
        ];
    }
}
