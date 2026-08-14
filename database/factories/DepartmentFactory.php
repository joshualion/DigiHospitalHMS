<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'name' => fake()->word().' Department',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'category' => 'administrative',
            'status' => 'active',
            'display_order' => 0,
        ];
    }
}
