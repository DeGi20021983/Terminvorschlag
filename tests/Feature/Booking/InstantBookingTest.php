<?php

namespace Tests\Feature\Booking;

use App\Enums\AllocationMode;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsIn;
use Tests\TestCase;

class InstantBookingTest extends TestCase
{
    use RefreshDatabase, SignsIn;

    private Offer $offer;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        config(['terminvorschlag.force_https' => false]);
        $this->offer = Offer::factory()->create();
        $this->slot = Slot::factory()->create(['offer_id' => $this->offer->id]);
    }

    public function test_employee_books_a_free_slot_and_it_is_blocked_at_once(): void
    {
        $anna = User::factory()->create();

        $this->signInAs($anna)
            ->post(route('bookings.store', [$this->offer, $this->slot]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('bookings', [
            'slot_id' => $this->slot->id,
            'user_id' => $anna->id,
            'status' => BookingStatus::Confirmed->value,
            'source' => 'instant',
        ]);
    }

    public function test_second_booking_for_the_same_slot_is_rejected_with_a_hint(): void
    {
        $anna = User::factory()->create();
        $ben = User::factory()->create();

        $this->signInAs($anna)->post(route('bookings.store', [$this->offer, $this->slot]));
        $this->signInAs($ben)
            ->post(route('bookings.store', [$this->offer, $this->slot]))
            ->assertSessionHasErrors(['booking' => 'Dieser Termin ist gerade vergeben. Bitte wählen Sie einen anderen Termin.']);

        $this->assertSame(1, Booking::query()->confirmed()->where('slot_id', $this->slot->id)->count());
    }

    public function test_person_may_hold_only_one_slot_per_offer_by_default(): void
    {
        $anna = User::factory()->create();
        $second = Slot::factory()->create([
            'offer_id' => $this->offer->id,
            'starts_at' => $this->slot->starts_at->addHours(2),
            'ends_at' => $this->slot->ends_at->addHours(2),
        ]);

        $this->signInAs($anna)->post(route('bookings.store', [$this->offer, $this->slot]));
        $this->signInAs($anna)
            ->post(route('bookings.store', [$this->offer, $second]))
            ->assertSessionHasErrors(['booking']);

        $this->assertSame(1, Booking::query()->confirmed()->where('user_id', $anna->id)->count());
    }

    public function test_one_booking_rule_can_be_switched_off_per_offer(): void
    {
        $this->offer->update(['one_booking_per_person' => false]);
        $anna = User::factory()->create();
        $second = Slot::factory()->create([
            'offer_id' => $this->offer->id,
            'starts_at' => $this->slot->starts_at->addHours(2),
            'ends_at' => $this->slot->ends_at->addHours(2),
        ]);

        $this->signInAs($anna)->post(route('bookings.store', [$this->offer, $this->slot]));
        $this->signInAs($anna)->post(route('bookings.store', [$this->offer, $second]))->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Booking::query()->confirmed()->where('user_id', $anna->id)->count());
    }

    public function test_booking_outside_the_booking_window_is_rejected(): void
    {
        $this->offer->update(['bookable_until' => now()->subMinute()]);

        $this->signInAs(User::factory()->create())
            ->post(route('bookings.store', [$this->offer, $this->slot]))
            ->assertSessionHasErrors(['booking']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_started_slot_cannot_be_booked(): void
    {
        $started = Slot::factory()->create([
            'offer_id' => $this->offer->id,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addMinutes(25),
        ]);

        $this->signInAs(User::factory()->create())
            ->post(route('bookings.store', [$this->offer, $started]))
            ->assertSessionHasErrors(['booking']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_instant_booking_is_closed_while_wishes_are_pending(): void
    {
        $this->offer->update([
            'allocation_mode' => AllocationMode::Wishes,
            'wish_deadline' => now()->addDay(),
        ]);

        $this->signInAs(User::factory()->create())
            ->post(route('bookings.store', [$this->offer, $this->slot]))
            ->assertSessionHasErrors(['booking']);

        $this->offer->update(['wishes_released_at' => now()]);

        $this->signInAs(User::factory()->create())
            ->post(route('bookings.store', [$this->offer, $this->slot]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_slot_of_another_offer_cannot_be_booked_through_this_offer(): void
    {
        $other = Slot::factory()->create();

        $this->signInAs(User::factory()->create())
            ->post(route('bookings.store', [$this->offer, $other]))
            ->assertSessionHasErrors(['booking']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_draft_offer_is_not_visible_to_employees(): void
    {
        $draft = Offer::factory()->draft()->create();
        $slot = Slot::factory()->create(['offer_id' => $draft->id]);

        $this->signInAs(User::factory()->create())
            ->post(route('bookings.store', [$draft, $slot]))
            ->assertForbidden();
    }

    public function test_booking_window_boundaries_are_inclusive(): void
    {
        $this->offer->update(['bookable_from' => CarbonImmutable::now()->subHour()]);

        $this->signInAs(User::factory()->create())
            ->post(route('bookings.store', [$this->offer, $this->slot]))
            ->assertSessionDoesntHaveErrors();
    }
}
