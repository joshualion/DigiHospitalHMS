<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

class FacilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'name' => fake()->company().' Branch',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'facility_type' => 'branch',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'country' => 'Nigeria',
            'is_primary' => false,
            'status' => 'active',
        ];
    }
}
