<?php

namespace Tests\Feature\Offers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsIn;
use Tests\TestCase;

/**
 * Rollen sind serverseitig getrennt: Angebote und Buchungslisten sieht nur der zuständige Organisator.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase, SignsIn;

    protected function setUp(): void
    {
        parent::setUp();

        config(['terminvorschlag.force_https' => false]);
    }

    public function test_employee_cannot_create_an_offer(): void
    {
        $this->signInAs(User::factory()->create())
            ->post(route('manage.offers.store'), [])
            ->assertForbidden();

        $this->assertDatabaseCount('offers', 0);
    }

    public function test_organizer_cannot_manage_an_offer_of_a_colleague(): void
    {
        $owner = User::factory()->organizer()->create();
        $other = User::factory()->organizer()->create();
        $offer = Offer::factory()->create(['owner_user_id' => $owner->id]);

        $this->signInAs($other)
            ->post(route('manage.offers.cancel', $offer))
            ->assertForbidden();

        $this->assertFalse($offer->fresh()->isCancelled());
    }

    public function test_organizer_cannot_remove_bookings_of_a_colleagues_offer(): void
    {
        $owner = User::factory()->organizer()->create();
        $other = User::factory()->organizer()->create();
        $offer = Offer::factory()->create(['owner_user_id' => $owner->id]);
        $booking = Booking::factory()->forSlot(Slot::factory()->create(['offer_id' => $offer->id]))->create();

        $this->signInAs($other)
            ->delete(route('manage.bookings.remove', $booking))
            ->assertForbidden();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_employee_cannot_remove_a_booking_even_their_own(): void
    {
        $anna = User::factory()->create();
        $booking = Booking::factory()->forSlot(Slot::factory()->create())->create(['user_id' => $anna->id]);

        $this->signInAs($anna)
            ->delete(route('manage.bookings.remove', $booking))
            ->assertForbidden();
    }

    public function test_owner_removes_a_person_from_the_slot(): void
    {
        $owner = User::factory()->organizer()->create();
        $offer = Offer::factory()->create(['owner_user_id' => $owner->id]);
        $slot = Slot::factory()->create(['offer_id' => $offer->id]);
        $booking = Booking::factory()->forSlot($slot)->create();

        $this->signInAs($owner)
            ->delete(route('manage.bookings.remove', $booking))
            ->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);

        // Der Termin ist danach sofort wieder buchbar.
        $this->assertSame(0, $slot->bookings()->confirmed()->count());
    }

    public function test_draft_offer_is_hidden_from_employees(): void
    {
        $owner = User::factory()->organizer()->create();
        $draft = Offer::factory()->draft()->create(['owner_user_id' => $owner->id]);

        $this->signInAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk();

        $this->assertFalse($this->app->make(Gate::class)
            ->forUser(User::factory()->create())
            ->allows('view', $draft));
    }
}
