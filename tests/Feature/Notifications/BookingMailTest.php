<?php

namespace Tests\Feature\Notifications;

use App\Enums\AllocationMode;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingMovedMail;
use App\Mail\OfferCancelledMail;
use App\Mail\OfferChangedMail;
use App\Mail\WishProposalMail;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use App\Models\WishEntry;
use App\Services\BookingService;
use App\Services\IcsBuilder;
use App\Services\OfferService;
use App\Services\WishDistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingMailTest extends TestCase
{
    use RefreshDatabase;

    private Offer $offer;

    private Slot $slot;

    private User $anna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->offer = Offer::factory()->create(['title' => 'Schulung, Teil 1']);
        $this->slot = Slot::factory()->create(['offer_id' => $this->offer->id]);
        $this->anna = User::factory()->create(['email' => 'anna.schmidt@example.de']);

        Mail::fake();
    }

    public function test_booking_sends_a_confirmation_to_the_person(): void
    {
        app(BookingService::class)->book($this->anna, $this->slot);

        Mail::assertQueued(BookingConfirmedMail::class, fn ($mail) => $mail->hasTo('anna.schmidt@example.de'));
    }

    public function test_self_cancellation_and_organizer_removal_send_mails(): void
    {
        $booking = app(BookingService::class)->book($this->anna, $this->slot);

        app(BookingService::class)->cancel($this->anna, $booking);

        Mail::assertQueued(BookingCancelledMail::class, fn ($mail) => $mail->reason === 'self');

        $second = Slot::factory()->create(['offer_id' => $this->offer->id, 'starts_at' => now()->addDays(4)->setTime(9, 0), 'ends_at' => now()->addDays(4)->setTime(9, 30)]);
        $booking = app(BookingService::class)->book($this->anna, $second);
        app(BookingService::class)->cancelByOrganizer($booking);

        Mail::assertQueued(BookingCancelledMail::class, fn ($mail) => $mail->reason === 'organizer');
    }

    public function test_moving_a_booking_sends_a_move_mail(): void
    {
        $booking = app(BookingService::class)->book($this->anna, $this->slot);
        $target = Slot::factory()->create(['offer_id' => $this->offer->id, 'starts_at' => now()->addDays(4)->setTime(9, 0), 'ends_at' => now()->addDays(4)->setTime(9, 30)]);

        app(BookingService::class)->move($this->anna, $booking, $target);

        Mail::assertQueued(BookingMovedMail::class, fn ($mail) => $mail->hasTo('anna.schmidt@example.de'));
    }

    public function test_moving_a_slot_informs_everyone_booked_on_it(): void
    {
        app(BookingService::class)->book($this->anna, $this->slot);

        app(OfferService::class)->moveSlot(
            User::factory()->organizer()->create(),
            $this->slot,
            now()->addDays(5)->setTime(14, 0)->toImmutable(),
            now()->addDays(5)->setTime(14, 30)->toImmutable(),
        );

        Mail::assertQueued(OfferChangedMail::class, fn ($mail) => $mail->hasTo('anna.schmidt@example.de'));
    }

    public function test_cancelling_an_offer_informs_every_booked_person_once(): void
    {
        $ben = User::factory()->create(['email' => 'ben.becker@example.de']);
        $second = Slot::factory()->create(['offer_id' => $this->offer->id, 'starts_at' => now()->addDays(4)->setTime(9, 0), 'ends_at' => now()->addDays(4)->setTime(9, 30)]);

        app(BookingService::class)->book($this->anna, $this->slot);
        app(BookingService::class)->book($ben, $second);

        app(OfferService::class)->cancel(User::factory()->organizer()->create(), $this->offer, 'Raum defekt');

        Mail::assertQueued(OfferCancelledMail::class, 2);
        Mail::assertQueued(OfferCancelledMail::class, fn ($mail) => $mail->note === 'Raum defekt' && $mail->hasTo('ben.becker@example.de'));
    }

    public function test_released_wish_proposal_is_mailed_to_the_person(): void
    {
        $this->offer->update(['allocation_mode' => AllocationMode::Wishes, 'wish_deadline' => now()->subHour(), 'owner_user_id' => User::factory()->organizer()->create()->id]);
        $ben = User::factory()->create(['email' => 'ben.becker@example.de']);

        // Ein zweiter Termin, damit die leer ausgehende Person einen Vorschlag bekommt.
        Slot::factory()->create(['offer_id' => $this->offer->id, 'starts_at' => now()->addDays(4)->setTime(9, 0), 'ends_at' => now()->addDays(4)->setTime(9, 30)]);

        foreach ([$this->anna, $ben] as $person) {
            WishEntry::query()->create(['offer_id' => $this->offer->id, 'user_id' => $person->id, 'slot_id' => $this->slot->id, 'priority' => 1, 'submitted_at' => now()]);
        }

        $organizer = $this->offer->owner;
        app(WishDistributionService::class)->calculate($organizer, $this->offer);
        app(WishDistributionService::class)->release($organizer, $this->offer);

        Mail::assertQueued(WishProposalMail::class, 1);
    }

    public function test_person_without_email_gets_no_mail_but_the_booking_stands(): void
    {
        $noMail = User::factory()->create(['email' => null]);

        $booking = app(BookingService::class)->book($noMail, $this->slot);

        Mail::assertNothingQueued();
        $this->assertSame($noMail->id, $booking->fresh()->user_id);
    }

    public function test_ics_file_contains_the_booking_as_an_event_in_utc(): void
    {
        $booking = app(BookingService::class)->book($this->anna, $this->slot);

        $ics = app(IcsBuilder::class)->forBooking($booking);

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("BEGIN:VEVENT\r\n", $ics);
        $this->assertStringContainsString('DTSTART:'.$this->slot->starts_at->utc()->format('Ymd\THis\Z')."\r\n", $ics);
        $this->assertStringContainsString('DTEND:'.$this->slot->ends_at->utc()->format('Ymd\THis\Z')."\r\n", $ics);
        $this->assertStringContainsString('UID:booking-'.$booking->id.'@', $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
    }

    public function test_ics_escapes_separators_and_keeps_lines_within_75_octets(): void
    {
        $booking = app(BookingService::class)->book($this->anna, $this->slot);

        $ics = app(IcsBuilder::class)->forBooking($booking);

        // Kommas im Titel müssen maskiert sein.
        $this->assertStringContainsString('SUMMARY:Schulung\\, Teil 1', $ics);

        // Zeilenumbrüche in der Beschreibung sind genau einmal maskiert (\n), nicht doppelt.
        $this->assertStringContainsString('\\n', $ics);
        $this->assertStringNotContainsString('\\\\n', $ics);

        foreach (explode("\r\n", rtrim($ics, "\r\n")) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'Zeile zu lang: '.$line);
        }
    }

    public function test_confirmation_mail_carries_the_ics_attachment(): void
    {
        $booking = app(BookingService::class)->book($this->anna, $this->slot);

        $attachments = (new BookingConfirmedMail($booking))->attachments();

        $this->assertCount(1, $attachments);
        $this->assertSame('termin.ics', $attachments[0]->as);
    }
}
