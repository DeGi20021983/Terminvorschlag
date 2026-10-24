<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Standardmäßig bestätigt. Angebot und Termin gehören zusammen.
 *
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $slot = Slot::factory()->create();

        return [
            'offer_id' => $slot->offer_id,
            'slot_id' => $slot->id,
            'user_id' => User::factory(),
            'booked_by_user_id' => null,
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Instant,
            'booked_at' => now(),
        ];
    }

    /**
     * Bucht einen bestehenden Termin (Angebot und Termin passen zusammen).
     */
    public function forSlot(Slot $slot): static
    {
        return $this->state(fn () => [
            'offer_id' => $slot->offer_id,
            'slot_id' => $slot->id,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
