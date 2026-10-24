<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Raum '.fake()->unique()->numberBetween(100, 999),
            'address' => fake()->streetAddress().', 01067 Dresden',
            'is_active' => true,
        ];
    }
}
