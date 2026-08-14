<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HospitalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'legal_name' => fake()->company().' Ltd',
            'display_name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone_numbers' => [fake()->phoneNumber()],
            'address' => fake()->streetAddress(),
            'city' => 'Lagos',
            'state' => 'Lagos',
            'country' => 'Nigeria',
            'timezone' => 'Africa/Lagos',
            'status' => 'active',
            'default_currency' => 'NGN',
            'is_active' => true,
        ];
    }
}
