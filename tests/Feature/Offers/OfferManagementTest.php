<?php

namespace Tests\Feature\Offers;

use App\Enums\BookingStatus;
use App\Enums\OfferStatus;
use App\Events\OfferCancelled;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use App\Services\OfferService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SignsIn;
use Tests\TestCase;

class OfferManagementTest extends TestCase
{
    use RefreshDatabase, SignsIn;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['terminvorschlag.force_https' => false]);
        $this->organizer = User::factory()->organizer()->create();
    }

    private function offerData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Ergonomie-Schulung',
            'description' => 'Grundlagen am Arbeitsplatz',
            'location_id' => null,
            'online_url' => 'https://meeting.example.de/abc',
            'contact_name' => 'Petra Lang',
            'target_group' => 'Alle Mitarbeitenden',
            'area' => 'Bereich Nord',
            'allocation_mode' => 'instant',
            'bookable_from' => now()->subDay()->format('Y-m-d H:i'),
            'bookable_until' => now()->addMonth()->format('Y-m-d H:i'),
            'wish_deadline' => '',
            'one_booking_per_person' => '1',
            'reminders_enabled' => '1',
        ], $overrides);
    }

    public function test_organizer_creates_a_draft_offer_and_the_change_is_logged(): void
    {
        $this->signInAs($this->organizer)
            ->post(route('manage.offers.store'), $this->offerData())
            ->assertSessionHasNoErrors();

        $offer = Offer::query()->sole();

        $this->assertSame(OfferStatus::Draft, $offer->status);
        $this->assertSame($this->organizer->id, $offer->owner_user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'offer.created', 'actor_username' => $this->organizer->username]);
    }

    public function test_offer_needs_a_location_or_an_online_link(): void
    {
        $this->signInAs($this->organizer)
            ->post(route('manage.offers.store'), $this->offerData(['online_url' => '']))
            ->assertSessionHasErrors(['location_id']);

        $this->assertDatabaseCount('offers', 0);
    }

    public function test_wish_offer_requires_a_deadline(): void
    {
        $this->signInAs($this->organizer)
            ->post(route('manage.offers.store'), $this->offerData(['allocation_mode' => 'wishes', 'wish_deadline' => '']))
            ->assertSessionHasErrors(['wish_deadline']);
    }

    public function test_publishing_requires_at_least_one_slot(): void
    {
        $offer = Offer::factory()->draft()->create(['owner_user_id' => $this->organizer->id]);

        $this->signInAs($this->organizer)
            ->post(route('manage.offers.publish', $offer))
            ->assertSessionHasErrors(['offer']);

        $this->assertSame(OfferStatus::Draft, $offer->fresh()->status);

        Slot::factory()->create(['offer_id' => $offer->id]);

        $this->signInAs($this->organizer)->post(route('manage.offers.publish', $offer));

        $this->assertSame(OfferStatus::Published, $offer->fresh()->status);
    }

    public function test_grid_creates_one_slot_per_interval(): void
    {
        $offer = Offer::factory()->create(['owner_user_id' => $this->organizer->id]);
        $day = now()->addDays(5)->format('Y-m-d');

        $slots = app(OfferService::class)->addGrid(
            $this->organizer,
            $offer,
            CarbonImmutable::parse($day),
            '09:00',
            '12:00',
            30,
        );

        $this->assertCount(6, $slots);
        $this->assertSame('09:00', $slots[0]->starts_at->format('H:i'));
        $this->assertSame('12:00', $slots[5]->ends_at->format('H:i'));
    }

    public function test_grid_that_does_not_divide_evenly_is_rejected(): void
    {
        $offer = Offer::factory()->create(['owner_user_id' => $this->organizer->id]);

        $this->signInAs($this->organizer)
            ->post(route('manage.slots.grid', $offer), [
                'date' => now()->addDays(5)->format('Y-m-d'),
                'from' => '09:00',
                'to' => '10:00',
                'minutes' => 45,
            ])
            ->assertSessionHasErrors(['slot']);

        $this->assertDatabaseCount('slots', 0);
    }

    public function test_overlapping_slot_is_rejected(): void
    {
        $offer = Offer::factory()->create(['owner_user_id' => $this->organizer->id]);
        Slot::factory()->create([
            'offer_id' => $offer->id,
            'starts_at' => now()->addDays(3)->setTime(9, 0),
            'ends_at' => now()->addDays(3)->setTime(9, 30),
        ]);

        $this->signInAs($this->organizer)
            ->post(route('manage.slots.store', $offer), [
                'date' => now()->addDays(3)->format('Y-m-d'),
                'start' => '09:15',
                'end' => '09:45',
            ])
            ->assertSessionHasErrors(['slot']);

        $this->assertDatabaseCount('slots', 1);
    }

    public function test_copying_a_slot_keeps_its_duration(): void
    {
        $offer = Offer::factory()->create(['owner_user_id' => $this->organizer->id]);
        $slot = Slot::factory()->create([
            'offer_id' => $offer->id,
            'starts_at' => now()->addDays(3)->setTime(9, 0),
            'ends_at' => now()->addDays(3)->setTime(9, 45),
        ]);

        $this->signInAs($this->organizer)
            ->post(route('manage.slots.copy', $slot), [
                'date' => now()->addDays(6)->format('Y-m-d'),
                'start' => '14:00',
            ])
            ->assertSessionHasNoErrors();

        $copy = Slot::query()->where('id', '!=', $slot->id)->sole();

        $this->assertSame('14:00', $copy->starts_at->format('H:i'));
        $this->assertSame('14:45', $copy->ends_at->format('H:i'));
    }

    public function test_slot_with_a_confirmed_booking_cannot_be_deleted(): void
    {
        $offer = Offer::factory()->create(['owner_user_id' => $this->organizer->id]);
        $slot = Slot::factory()->create(['offer_id' => $offer->id]);
        Booking::factory()->forSlot($slot)->create();

        $this->signInAs($this->organizer)
            ->delete(route('manage.slots.destroy', $slot))
            ->assertSessionHasErrors(['slot']);

        $this->assertDatabaseHas('slots', ['id' => $slot->id]);
    }

    public function test_cancelling_an_offer_cancels_its_bookings_and_notifies_once(): void
    {
        $offer = Offer::factory()->create(['owner_user_id' => $this->organizer->id]);
        $slot = Slot::factory()->create(['offer_id' => $offer->id]);
        $booking = Booking::factory()->forSlot($slot)->create();

        Event::fake([OfferCancelled::class]);

        $this->signInAs($this->organizer)
            ->post(route('manage.offers.cancel', $offer), ['note' => 'Raum nicht verfügbar'])
            ->assertSessionHasNoErrors();

        $offer->refresh();

        $this->assertSame(OfferStatus::Cancelled, $offer->status);
        $this->assertSame('Raum nicht verfügbar', $offer->cancellation_note);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);

        Event::assertDispatchedTimes(OfferCancelled::class, 1);

        $this->signInAs($this->organizer)
            ->post(route('manage.offers.cancel', $offer))
            ->assertSessionHasErrors(['offer']);
    }

    public function test_organizer_changes_are_logged_with_field_names_only(): void
    {
        $offer = Offer::factory()->create(['owner_user_id' => $this->organizer->id]);

        app(OfferService::class)->update($this->organizer, $offer, ['title' => 'Neuer Titel']);

        $log = AuditLog::query()->where('action', 'offer.updated')->sole();

        $this->assertSame(['fields' => ['title']], $log->details);
        $this->assertSame($offer->id, $log->offer_id);
    }
}
