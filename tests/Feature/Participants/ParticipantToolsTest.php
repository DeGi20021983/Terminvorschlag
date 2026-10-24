<?php

namespace Tests\Feature\Participants;

use App\Enums\BookingSource;
use App\Mail\ParticipantMessageMail;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\SignsIn;
use Tests\Support\FakeDirectoryService;
use Tests\TestCase;

class ParticipantToolsTest extends TestCase
{
    use RefreshDatabase, SignsIn;

    private User $organizer;

    private Offer $offer;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        config(['terminvorschlag.force_https' => false]);

        $this->organizer = User::factory()->organizer()->create();
        $this->offer = Offer::factory()->create(['owner_user_id' => $this->organizer->id, 'title' => 'Ergonomie Teil 1']);
        $this->slot = Slot::factory()->create(['offer_id' => $this->offer->id]);
    }

    private function bookedPerson(string $name, ?string $email = null, ?Slot $slot = null): User
    {
        $person = User::factory()->create(['display_name' => $name, 'email' => $email ?? strtolower(str_replace(' ', '.', $name)).'@example.de', 'department' => 'Bereich Nord']);
        Booking::factory()->forSlot($slot ?? $this->slot)->create(['user_id' => $person->id]);

        return $person;
    }

    private function streamed(TestResponse $response): string
    {
        ob_start();
        $response->baseResponse->sendContent();

        return (string) ob_get_clean();
    }

    public function test_csv_export_contains_the_participants_with_a_bom_and_semicolons(): void
    {
        $this->bookedPerson('Lukas Beispiel');

        $response = $this->signInAs($this->organizer)->get(route('manage.participants.csv', $this->offer));

        $response->assertOk();
        $content = $this->streamed($response);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Datum;Beginn;Ende;Status;Person;Organisationseinheit;E-Mail', $content);
        $this->assertStringContainsString('Belegt;"Lukas Beispiel";"Bereich Nord"', $content);
    }

    public function test_csv_export_neutralises_formulas(): void
    {
        $this->bookedPerson('=HYPERLINK("https://evil.example")');

        $content = $this->streamed($this->signInAs($this->organizer)->get(route('manage.participants.csv', $this->offer)));

        $this->assertStringNotContainsString(';=HYPERLINK', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function test_excel_export_is_a_real_spreadsheet_with_the_participant(): void
    {
        $this->bookedPerson('Mara Muster');

        $response = $this->signInAs($this->organizer)->get(route('manage.participants.xlsx', $this->offer));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = storage_path('framework/testing/teilnehmer-'.uniqid().'.xlsx');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $this->streamed($response));

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('Person', $sheet->getCell('E1')->getValue());
        $this->assertSame('Mara Muster', $sheet->getCell('E2')->getValue());

        @unlink($path);
    }

    public function test_exports_and_print_are_limited_to_the_responsible_organizer(): void
    {
        $other = User::factory()->organizer()->create();

        foreach (['manage.participants.csv', 'manage.participants.xlsx', 'manage.participants.print', 'manage.participants.live'] as $route) {
            $this->signInAs($other)->get(route($route, $this->offer))->assertForbidden();
        }
    }

    public function test_print_page_lists_booked_persons_with_signature_column(): void
    {
        $this->bookedPerson('Nora Druck');

        $this->signInAs($this->organizer)
            ->get(route('manage.participants.print', $this->offer))
            ->assertOk()
            ->assertSee('Anwesenheitsliste: Ergonomie Teil 1')
            ->assertSee('Nora Druck')
            ->assertSee('Unterschrift');
    }

    public function test_live_fragment_returns_the_table_with_a_timestamp_header(): void
    {
        $this->bookedPerson('Otto Live');

        $response = $this->signInAs($this->organizer)->get(route('manage.participants.live', $this->offer));

        $response->assertOk()
            ->assertHeader('X-Stand')
            ->assertSee('Otto Live')
            ->assertDontSee('<html', false);
    }

    public function test_organizer_adds_a_person_from_the_directory_to_a_free_slot(): void
    {
        $this->directory()->add('paula.frei', 'Paula Frei', 'Bereich Süd', 'paula.frei@example.de');

        $this->signInAs($this->organizer)
            ->post(route('manage.participants.add', $this->offer), ['slot_id' => $this->slot->id, 'username' => 'Paula.Frei'])
            ->assertSessionHasNoErrors();

        $booking = Booking::query()->confirmed()->where('slot_id', $this->slot->id)->sole();

        $this->assertSame(BookingSource::Manual, $booking->source);
        $this->assertSame($this->organizer->id, $booking->booked_by_user_id);
        $this->assertSame('paula.frei', $booking->user->username);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.manual', 'actor_username' => $this->organizer->username]);
    }

    public function test_unknown_account_cannot_be_added(): void
    {
        $this->signInAs($this->organizer)
            ->post(route('manage.participants.add', $this->offer), ['slot_id' => $this->slot->id, 'username' => 'niemand'])
            ->assertSessionHasErrors(['participant']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_manual_entry_into_a_taken_slot_is_rejected(): void
    {
        $this->bookedPerson('Erste Person');
        $this->directory()->add('zweite.person', 'Zweite Person');

        $this->signInAs($this->organizer)
            ->post(route('manage.participants.add', $this->offer), ['slot_id' => $this->slot->id, 'username' => 'zweite.person'])
            ->assertSessionHasErrors(['participant']);

        $this->assertSame(1, Booking::query()->confirmed()->count());
    }

    public function test_manual_entry_is_not_allowed_for_a_started_slot(): void
    {
        $started = Slot::factory()->create(['offer_id' => $this->offer->id, 'starts_at' => now()->subMinutes(5), 'ends_at' => now()->addMinutes(25)]);
        $this->directory()->add('spaet.eintrag');

        $this->signInAs($this->organizer)
            ->post(route('manage.participants.add', $this->offer), ['slot_id' => $started->id, 'username' => 'spaet.eintrag'])
            ->assertSessionHasErrors(['participant']);
    }

    public function test_removing_a_person_is_logged_and_frees_the_slot(): void
    {
        $person = $this->bookedPerson('Ruth Austrag');
        $booking = Booking::query()->where('user_id', $person->id)->sole();

        $this->signInAs($this->organizer)
            ->delete(route('manage.bookings.remove', $booking))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.removed', 'offer_id' => $this->offer->id]);
        $this->assertSame(0, $this->slot->bookings()->confirmed()->count());
    }

    public function test_message_goes_only_to_booked_persons_with_an_address(): void
    {
        Mail::fake();
        $this->bookedPerson('Heinz Post');
        $this->bookedPerson('Ida Ohne', email: '', slot: Slot::factory()->create(['offer_id' => $this->offer->id, 'starts_at' => now()->addDays(4)->setTime(9, 0), 'ends_at' => now()->addDays(4)->setTime(9, 30)]));

        $this->signInAs($this->organizer)
            ->post(route('manage.participants.message', $this->offer), ['subject' => 'Raumwechsel', 'body' => 'Bitte Raum 102 beachten.'])
            ->assertSessionHasNoErrors();

        Mail::assertQueued(ParticipantMessageMail::class, 1);
        Mail::assertQueued(ParticipantMessageMail::class, fn ($mail) => $mail->hasTo('heinz.post@example.de'));

        $log = AuditLog::query()->where('action', 'offer.message_sent')->sole();
        $this->assertSame(1, $log->details['recipients']);
        $this->assertStringNotContainsString('Raum 102', json_encode($log->details));
    }

    public function test_message_without_participants_is_rejected(): void
    {
        Mail::fake();

        $this->signInAs($this->organizer)
            ->post(route('manage.participants.message', $this->offer), ['subject' => 'Hallo', 'body' => 'Niemand da.'])
            ->assertSessionHasErrors(['message']);

        Mail::assertNothingQueued();
    }

    public function test_only_admins_see_the_protocol_and_it_can_be_filtered(): void
    {
        AuditLog::query()->create(['actor_username' => 'olaf.organizer', 'action' => 'offer.created', 'offer_id' => $this->offer->id, 'created_at' => now()]);
        AuditLog::query()->create(['actor_username' => 'petra.planerin', 'action' => 'slot.created', 'offer_id' => $this->offer->id, 'created_at' => now()]);

        $this->signInAs($this->organizer)->get(route('admin.index'))->assertForbidden();

        $admin = User::factory()->admin()->create();

        $this->signInAs($admin)
            ->get(route('admin.index', ['aktion' => 'offer.created']))
            ->assertOk()
            ->assertSee('<td>offer.created</td>', false)
            ->assertDontSee('<td>slot.created</td>', false);
    }

    public function test_admin_changes_settings_and_the_change_is_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->signInAs($admin)
            ->put(route('admin.settings'), [
                'wish_deadline_days_before' => 5,
                'reminder_hours_before' => 48,
                'confirmation_text' => 'Bitte pünktlich erscheinen.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('48', AppSetting::get('reminder_hours_before'));
        $this->assertSame('Bitte pünktlich erscheinen.', AppSetting::get('confirmation_text'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'actor_username' => $admin->username]);
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $this->signInAs(User::factory()->admin()->create())
            ->put(route('admin.settings'), ['wish_deadline_days_before' => 0, 'reminder_hours_before' => 0, 'confirmation_text' => 'x'])
            ->assertSessionHasErrors(['reminder_hours_before']);
    }

    public function test_admin_manages_locations_and_deactivates_them(): void
    {
        $admin = User::factory()->admin()->create();

        $this->signInAs($admin)
            ->post(route('admin.locations.store'), ['name' => 'Raum 7', 'address' => 'Am Hof 2', 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $location = Location::query()->where('name', 'Raum 7')->sole();
        $this->assertTrue($location->is_active);

        $this->signInAs($admin)
            ->put(route('admin.locations.update', $location), ['name' => 'Raum 7', 'address' => 'Am Hof 2'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($location->fresh()->is_active, 'Ohne Häkchen wird der Standort inaktiv.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'location.updated']);
    }

    private function directory(): FakeDirectoryService
    {
        return $this->directory;
    }
}
