<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'hospital_id' => Hospital::factory(),
            'staff_number' => strtoupper(fake()->unique()->bothify('STF-####')),
            'job_title' => 'Administrator',
            'staff_category' => 'administrative',
            'employment_status' => 'active',
            'is_active' => true,
        ];
    }
}
