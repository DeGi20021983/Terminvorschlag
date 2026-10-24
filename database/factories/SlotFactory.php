<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Slot>
 */
class SlotFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(3)->setTime(9, 0);

        return [
            'offer_id' => Offer::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->addMinutes(30),
            'was_booked' => false,
        ];
    }

    public function startingAt(\DateTimeInterface $start, int $minutes = 30): static
    {
        return $this->state(fn () => [
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify("+{$minutes} minutes"),
        ]);
    }
}
