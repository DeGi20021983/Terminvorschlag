<?php

namespace Tests\Feature\Database;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use App\Models\WishEntry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_a_second_confirmed_booking_for_the_same_slot(): void
    {
        $slot = Slot::factory()->create();
        Booking::factory()->forSlot($slot)->create();

        $this->expectException(QueryException::class);

        Booking::factory()->forSlot($slot)->create();
    }

    public function test_cancelled_booking_does_not_block_the_slot(): void
    {
        $slot = Slot::factory()->create();
        Booking::factory()->forSlot($slot)->cancelled()->create();

        $booking = Booking::factory()->forSlot($slot)->create();

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(2, Booking::query()->where('slot_id', $slot->id)->count());
    }

    public function test_slot_can_be_rebooked_after_cancellation(): void
    {
        $slot = Slot::factory()->create();
        $first = Booking::factory()->forSlot($slot)->create();

        $first->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);

        Booking::factory()->forSlot($slot)->create();

        $this->assertSame(1, $slot->bookings()->confirmed()->count());
    }

    public function test_active_booking_relation_ignores_cancelled_bookings(): void
    {
        $slot = Slot::factory()->create();
        Booking::factory()->forSlot($slot)->cancelled()->create();
        $active = Booking::factory()->forSlot($slot)->create(['source' => BookingSource::Manual]);

        $this->assertTrue($slot->activeBooking()->first()->is($active));
    }

    public function test_one_wish_per_priority_per_person_and_offer(): void
    {
        $slot = Slot::factory()->create();
        $user = User::factory()->create();

        WishEntry::query()->create([
            'offer_id' => $slot->offer_id,
            'user_id' => $user->id,
            'slot_id' => $slot->id,
            'priority' => 1,
            'submitted_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        WishEntry::query()->create([
            'offer_id' => $slot->offer_id,
            'user_id' => $user->id,
            'slot_id' => Slot::factory()->create(['offer_id' => $slot->offer_id])->id,
            'priority' => 1,
            'submitted_at' => now(),
        ]);
    }
}
