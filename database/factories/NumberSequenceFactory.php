<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

class NumberSequenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'key' => fake()->unique()->slug(2),
            'label' => fake()->words(3, true),
            'prefix' => 'TST',
            'date_format' => 'Y',
            'padding_length' => 6,
            'next_value' => 1,
            'issued_count' => 0,
            'status' => 'active',
        ];
    }
}
