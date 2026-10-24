<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsIn;
use Tests\TestCase;

class MoveBookingTest extends TestCase
{
    use RefreshDatabase, SignsIn;

    private Offer $offer;

    private User $anna;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        config(['terminvorschlag.force_https' => false]);

        $this->offer = Offer::factory()->create();
        $this->anna = User::factory()->create();
        $slot = Slot::factory()->create(['offer_id' => $this->offer->id]);
        $this->booking = Booking::factory()->forSlot($slot)->create(['user_id' => $this->anna->id]);
    }

    public function test_move_to_a_free_slot_replaces_the_old_booking_in_one_step(): void
    {
        $target = Slot::factory()->create(['offer_id' => $this->offer->id, 'starts_at' => now()->addDays(4)->setTime(10, 0), 'ends_at' => now()->addDays(4)->setTime(10, 30)]);

        $this->signInAs($this->anna)
            ->post(route('bookings.move', $this->booking), ['slot_id' => $target->id])
            ->assertSessionHasNoErrors();

        $old = $this->booking->fresh();
        $new = Booking::query()->where('slot_id', $target->id)->sole();

        $this->assertSame(BookingStatus::Cancelled, $old->status);
        $this->assertSame(BookingStatus::Confirmed, $new->status);
        $this->assertSame(BookingSource::Moved, $new->source);
        $this->assertSame($old->id, $new->replaces_booking_id);
        $this->assertSame($this->anna->id, $new->user_id);
        $this->assertSame(1, Booking::query()->confirmed()->where('user_id', $this->anna->id)->count());
    }

    public function test_move_to_a_taken_slot_leaves_the_old_booking_untouched(): void
    {
        $taken = Slot::factory()->create(['offer_id' => $this->offer->id]);
        Booking::factory()->forSlot($taken)->create();

        $this->signInAs($this->anna)
            ->post(route('bookings.move', $this->booking), ['slot_id' => $taken->id])
            ->assertSessionHasErrors(['booking']);

        $this->assertSame(BookingStatus::Confirmed, $this->booking->fresh()->status);
        $this->assertDatabaseCount('bookings', 2);
        $this->assertSame(1, Booking::query()->where('user_id', $this->anna->id)->count());
    }

    public function test_move_to_a_slot_of_another_offer_is_rejected(): void
    {
        $foreign = Slot::factory()->create();

        $this->signInAs($this->anna)
            ->post(route('bookings.move', $this->booking), ['slot_id' => $foreign->id])
            ->assertSessionHasErrors(['booking']);

        $this->assertSame(BookingStatus::Confirmed, $this->booking->fresh()->status);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_move_is_rolled_back_when_the_new_booking_cannot_be_written(): void
    {
        $target = Slot::factory()->create(['offer_id' => $this->offer->id, 'starts_at' => now()->addDays(4)->setTime(10, 0), 'ends_at' => now()->addDays(4)->setTime(10, 30)]);

        // Ein Fehler beim Anlegen des neuen Platzes darf den alten nicht freigeben.
        Booking::creating(function () {
            throw new \RuntimeException('simulierter Fehler');
        });

        try {
            $this->signInAs($this->anna)
                ->withoutExceptionHandling()
                ->post(route('bookings.move', $this->booking), ['slot_id' => $target->id]);
            $this->fail('Fehler wurde nicht weitergereicht.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('simulierter Fehler', $exception->getMessage());
        } finally {
            Booking::flushEventListeners();
        }

        $this->assertSame(BookingStatus::Confirmed, $this->booking->fresh()->status);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_move_is_blocked_after_the_slot_started(): void
    {
        $this->booking->slot->update(['starts_at' => now()->subMinute(), 'ends_at' => now()->addMinutes(29)]);
        $target = Slot::factory()->create(['offer_id' => $this->offer->id]);

        $this->signInAs($this->anna)
            ->post(route('bookings.move', $this->booking), ['slot_id' => $target->id])
            ->assertSessionHasErrors(['booking']);

        $this->assertSame(BookingStatus::Confirmed, $this->booking->fresh()->status);
    }
}
