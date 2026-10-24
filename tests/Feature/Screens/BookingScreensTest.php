<?php

namespace Tests\Feature\Screens;

use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsIn;
use Tests\TestCase;

/**
 * Prüft die Bildschirme für Mitarbeitende. Wichtig: belegte Termine zeigen nie einen Namen.
 */
class BookingScreensTest extends TestCase
{
    use RefreshDatabase, SignsIn;

    protected function setUp(): void
    {
        parent::setUp();

        config(['terminvorschlag.force_https' => false]);
    }

    public function test_catalog_shows_free_counts_and_disables_fully_booked_offers(): void
    {
        $open = Offer::factory()->create(['title' => 'Offenes Angebot', 'area' => 'Bereich Nord']);
        Slot::factory()->create(['offer_id' => $open->id]);

        $full = Offer::factory()->create(['title' => 'Ausgebuchtes Angebot']);
        $fullSlot = Slot::factory()->create(['offer_id' => $full->id]);
        Booking::factory()->forSlot($fullSlot)->create();

        $response = $this->signInAs(User::factory()->create())->get('/angebote');

        $response->assertOk()
            ->assertSee('Offenes Angebot')
            ->assertSee('1 von 1 Terminen frei')
            ->assertSee('Ausgebucht')
            ->assertSee('Termine ansehen');
    }

    public function test_only_free_filter_hides_fully_booked_offers(): void
    {
        $full = Offer::factory()->create(['title' => 'Ausgebucht XYZ']);
        Booking::factory()->forSlot(Slot::factory()->create(['offer_id' => $full->id]))->create();

        $this->signInAs(User::factory()->create())
            ->get('/angebote?frei=1')
            ->assertOk()
            ->assertDontSee('Ausgebucht XYZ');
    }

    public function test_booked_slot_shows_no_name_to_other_employees(): void
    {
        $offer = Offer::factory()->create();
        $slot = Slot::factory()->create(['offer_id' => $offer->id]);
        $colleague = User::factory()->create(['display_name' => 'Geheim Kollegin']);
        Booking::factory()->forSlot($slot)->create(['user_id' => $colleague->id]);

        $this->signInAs(User::factory()->create())
            ->get(route('offers.show', $offer))
            ->assertOk()
            ->assertSee('Belegt')
            ->assertDontSee('Geheim Kollegin');
    }

    public function test_selecting_a_free_slot_enables_the_confirm_button(): void
    {
        $offer = Offer::factory()->create();
        $slot = Slot::factory()->create(['offer_id' => $offer->id]);
        $anna = User::factory()->create();

        $this->signInAs($anna)
            ->get(route('offers.show', ['offer' => $offer, 'termin' => $slot->id]))
            ->assertOk()
            ->assertSee(route('bookings.store', [$offer, $slot]), false)
            ->assertSee('Buchung bestätigen');
    }

    public function test_without_selection_the_confirm_button_is_disabled(): void
    {
        $offer = Offer::factory()->create();
        Slot::factory()->create(['offer_id' => $offer->id]);

        $this->signInAs(User::factory()->create())
            ->get(route('offers.show', $offer))
            ->assertOk()
            ->assertSee('Wählen Sie einen freien Termin, um fortzufahren.')
            ->assertSee('disabled', false);
    }

    public function test_own_booking_is_marked_and_switching_uses_the_move_route(): void
    {
        $offer = Offer::factory()->create();
        $mine = Slot::factory()->create(['offer_id' => $offer->id]);
        $other = Slot::factory()->create(['offer_id' => $offer->id, 'starts_at' => now()->addDays(4)->setTime(9, 0), 'ends_at' => now()->addDays(4)->setTime(9, 30)]);
        $anna = User::factory()->create();
        $booking = Booking::factory()->forSlot($mine)->create(['user_id' => $anna->id]);

        $this->signInAs($anna)
            ->get(route('offers.show', ['offer' => $offer, 'termin' => $other->id]))
            ->assertOk()
            ->assertSee('Ihr Termin')
            ->assertSee('Der alte Platz bleibt bis zur Bestätigung des neuen Termins gebucht.')
            ->assertSee(route('bookings.move', $booking), false)
            ->assertSee('Umbuchung bestätigen');
    }

    public function test_my_bookings_lists_upcoming_with_actions_and_past_without(): void
    {
        $anna = User::factory()->create();

        $upcoming = Booking::factory()->forSlot(Slot::factory()->create(['starts_at' => now()->addDays(2)->setTime(10, 0), 'ends_at' => now()->addDays(2)->setTime(10, 30)]))->create(['user_id' => $anna->id]);
        $past = Booking::factory()->forSlot(Slot::factory()->create(['starts_at' => now()->subDays(2)->setTime(10, 0), 'ends_at' => now()->subDays(2)->setTime(10, 30)]))->create(['user_id' => $anna->id]);

        $response = $this->signInAs($anna)->get(route('bookings.mine'));

        $response->assertOk()
            ->assertSee('Bestätigt')
            ->assertSee('Abgeschlossen')
            ->assertSee(route('bookings.cancel', $upcoming), false)
            ->assertDontSee(route('bookings.cancel', $past), false);
    }

    public function test_navigation_is_limited_to_the_roles_of_the_person(): void
    {
        $this->signInAs(User::factory()->create())
            ->get('/angebote')
            ->assertOk()
            ->assertDontSee('Angebote verwalten')
            ->assertDontSee('Administration');
    }

    public function test_organizer_sees_management_navigation_and_admin_sees_administration(): void
    {
        $this->signInAs(User::factory()->organizer()->create())
            ->get('/angebote')
            ->assertSee('Angebote verwalten')
            ->assertSee('Teilnehmerliste')
            ->assertSee('Wunschverteilung')
            ->assertDontSee('Administration');

        $this->signInAs(User::factory()->admin()->create())
            ->get('/angebote')
            ->assertSee('Administration')
            ->assertDontSee('Angebote verwalten');
    }

    public function test_privacy_notice_is_reachable(): void
    {
        $this->signInAs(User::factory()->create())
            ->get('/datenschutz')
            ->assertOk()
            ->assertSee('Datenschutzhinweis');
    }

    public function test_forbidden_access_shows_the_german_error_page(): void
    {
        $owner = User::factory()->organizer()->create();
        $offer = Offer::factory()->create(['owner_user_id' => $owner->id]);

        $this->signInAs(User::factory()->organizer()->create())
            ->get(route('manage.offers.edit', $offer))
            ->assertForbidden()
            ->assertSee('Kein Zugriff');
    }
}
