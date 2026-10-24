<?php

namespace Database\Factories;

use App\Enums\AllocationMode;
use App\Enums\OfferStatus;
use App\Models\Location;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory()->organizer(),
            'title' => 'Angebot '.fake()->unique()->words(2, true),
            'description' => fake()->sentence(12),
            'location_id' => Location::factory(),
            'online_url' => null,
            'contact_name' => fake()->name(),
            'target_group' => 'Alle Mitarbeitenden',
            'area' => 'Bereich Nord',
            'allocation_mode' => AllocationMode::Instant,
            'status' => OfferStatus::Published,
            'bookable_from' => now()->subDay(),
            'bookable_until' => now()->addMonth(),
            'wish_deadline' => null,
            'wishes_released_at' => null,
            'one_booking_per_person' => true,
            'reminders_enabled' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => OfferStatus::Draft]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => OfferStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Vergabe per Wunschtermin mit offener Frist.
     */
    public function withWishes(): static
    {
        return $this->state(fn () => [
            'allocation_mode' => AllocationMode::Wishes,
            'wish_deadline' => now()->addDays(3),
        ]);
    }
}
