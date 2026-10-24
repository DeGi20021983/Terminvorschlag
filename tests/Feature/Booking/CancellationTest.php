<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SignsIn;
use Tests\TestCase;

class CancellationTest extends TestCase
{
    use RefreshDatabase, SignsIn;

    protected function setUp(): void
    {
        parent::setUp();

        config(['terminvorschlag.force_https' => false]);
    }

    public function test_cancelling_before_start_frees_the_slot_at_once(): void
    {
        $offer = Offer::factory()->create();
        $slot = Slot::factory()->create(['offer_id' => $offer->id]);
        $anna = User::factory()->create();
        $booking = Booking::factory()->forSlot($slot)->create(['user_id' => $anna->id]);

        Event::fake([BookingCancelled::class]);

        $this->signInAs($anna)
            ->post(route('bookings.cancel', $booking))
            ->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->cancelled_at);

        // Der Platz steht sofort einer anderen Person zur Verfügung.
        $this->signInAs(User::factory()->create())
            ->post(route('bookings.store', [$offer, $slot]))
            ->assertSessionDoesntHaveErrors();

        Event::assertDispatched(BookingCancelled::class, fn ($event) => $event->reason === 'self');
    }

    public function test_cancelling_after_start_is_rejected(): void
    {
        $offer = Offer::factory()->create();
        $slot = Slot::factory()->create([
            'offer_id' => $offer->id,
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(20),
        ]);
        $anna = User::factory()->create();
        $booking = Booking::factory()->forSlot($slot)->create(['user_id' => $anna->id]);

        $this->signInAs($anna)
            ->post(route('bookings.cancel', $booking))
            ->assertSessionHasErrors(['booking']);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_employee_cannot_cancel_someone_elses_booking(): void
    {
        $slot = Slot::factory()->create();
        $booking = Booking::factory()->forSlot($slot)->create();

        $this->signInAs(User::factory()->create())
            ->post(route('bookings.cancel', $booking))
            ->assertForbidden();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_cancelling_twice_reports_that_the_booking_is_not_active(): void
    {
        $slot = Slot::factory()->create();
        $anna = User::factory()->create();
        $booking = Booking::factory()->forSlot($slot)->create(['user_id' => $anna->id]);

        $this->signInAs($anna)->post(route('bookings.cancel', $booking));
        $this->signInAs($anna)
            ->post(route('bookings.cancel', $booking))
            ->assertSessionHasErrors(['booking' => 'Diese Buchung ist nicht mehr aktiv.']);
    }
}
