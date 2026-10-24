<?php

namespace Tests\Feature\Screens;

use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsIn;
use Tests\TestCase;

class OrganizerScreensTest extends TestCase
{
    use RefreshDatabase, SignsIn;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['terminvorschlag.force_https' => false]);
        $this->organizer = User::factory()->organizer()->create();
    }

    public function test_edit_screen_shows_the_booking_holder_to_the_responsible_organizer(): void
    {
        $offer = Offer::factory()->create(['owner_user_id' => $this->organizer->id, 'title' => 'Eigenes Angebot']);
        $slot = Slot::factory()->create(['offer_id' => $offer->id]);
        $holder = User::factory()->create(['display_name' => 'Petra Muster']);
        Booking::factory()->forSlot($slot)->create(['user_id' => $holder->id]);

        $this->signInAs($this->organizer)
            ->get(route('manage.offers.edit', $offer))
            ->assertOk()
            ->assertSee('Gebucht von')
            ->assertSee('Petra Muster')
            ->assertSee('Gefahrenbereich')
            ->assertSee('Zeitraster für einen Tag');
    }

    public function test_participant_list_offers_only_the_own_offers(): void
    {
        Offer::factory()->create(['owner_user_id' => $this->organizer->id, 'title' => 'Meine Schulung']);
        Offer::factory()->create(['title' => 'Fremde Schulung']);

        $this->signInAs($this->organizer)
            ->get(route('manage.participants.index'))
            ->assertOk()
            ->assertSee('Meine Schulung')
            ->assertDontSee('Fremde Schulung');
    }

    public function test_participant_list_shows_names_and_departments_of_own_bookings(): void
    {
        $offer = Offer::factory()->create(['owner_user_id' => $this->organizer->id]);
        $slot = Slot::factory()->create(['offer_id' => $offer->id]);
        $person = User::factory()->create(['display_name' => 'Lukas Beispiel', 'department' => 'Bereich Süd']);
        Booking::factory()->forSlot($slot)->create(['user_id' => $person->id]);

        $this->signInAs($this->organizer)
            ->get(route('manage.participants.index', ['offer' => $offer->id]))
            ->assertOk()
            ->assertSee('Lukas Beispiel')
            ->assertSee('Bereich Süd')
            ->assertSee('Stand:');
    }

    public function test_offer_list_shows_only_own_offers_and_create_form_renders(): void
    {
        Offer::factory()->create(['owner_user_id' => $this->organizer->id, 'title' => 'Mein Angebot XY']);
        Offer::factory()->create(['title' => 'Angebot Dritter']);

        $this->signInAs($this->organizer)
            ->get(route('manage.offers.index'))
            ->assertOk()
            ->assertSee('Mein Angebot XY')
            ->assertDontSee('Angebot Dritter');

        $this->signInAs($this->organizer)
            ->get(route('manage.offers.create'))
            ->assertOk()
            ->assertSee('Vergabeverfahren')
            ->assertSee('Höchstens ein Termin je Person und Angebot');
    }

    public function test_employee_cannot_open_management_screens(): void
    {
        $this->signInAs(User::factory()->create())
            ->get(route('manage.offers.index'))
            ->assertForbidden();

        $this->signInAs(User::factory()->create())
            ->get(route('manage.participants.index'))
            ->assertForbidden();
    }

    public function test_administration_is_only_for_admins(): void
    {
        $this->signInAs($this->organizer)
            ->get(route('admin.index'))
            ->assertForbidden();

        $this->signInAs(User::factory()->admin()->create())
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Rollen und AD-Gruppen');
    }
}
