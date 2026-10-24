<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->regexify('[a-z]{3}[0-9]{3}'),
            'display_name' => fake()->name(),
            'department' => 'Bereich '.fake()->randomElement(['Nord', 'Süd', 'Verwaltung']),
            'email' => fake()->unique()->safeEmail(),
            'is_organizer' => false,
            'is_admin' => false,
        ];
    }

    public function organizer(): static
    {
        return $this->state(fn () => ['is_organizer' => true]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }
}
