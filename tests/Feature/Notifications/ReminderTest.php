<?php

namespace Tests\Feature\Notifications;

use App\Enums\AllocationMode;
use App\Mail\OrganizerWishDueMail;
use App\Mail\ReminderMail;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function bookingStartingIn(int $hours, bool $remindersEnabled = true): Booking
    {
        $offer = Offer::factory()->create(['reminders_enabled' => $remindersEnabled]);
        $slot = Slot::factory()->create([
            'offer_id' => $offer->id,
            'starts_at' => now()->addHours($hours),
            'ends_at' => now()->addHours($hours)->addMinutes(30),
        ]);

        return Booking::factory()->forSlot($slot)->create(['user_id' => User::factory()->create()->id]);
    }

    public function test_reminder_is_sent_once_within_the_window(): void
    {
        $booking = $this->bookingStartingIn(10);

        $this->artisan('terminvorschlag:erinnerungen')->assertSuccessful();
        $this->artisan('terminvorschlag:erinnerungen')->assertSuccessful();

        Mail::assertQueued(ReminderMail::class, 1);
        $this->assertNotNull($booking->fresh()->reminder_sent_at);
    }

    public function test_reminder_is_not_sent_outside_the_window(): void
    {
        $this->bookingStartingIn(72);

        $this->artisan('terminvorschlag:erinnerungen')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_reminder_respects_the_per_offer_switch(): void
    {
        $this->bookingStartingIn(10, remindersEnabled: false);

        $this->artisan('terminvorschlag:erinnerungen')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_organizer_is_told_once_when_the_wish_deadline_has_passed(): void
    {
        $organizer = User::factory()->organizer()->create(['email' => 'olaf.organizer@example.de']);
        Offer::factory()->create([
            'owner_user_id' => $organizer->id,
            'allocation_mode' => AllocationMode::Wishes,
            'wish_deadline' => now()->subMinutes(5),
        ]);

        $this->artisan('terminvorschlag:wunschfristen')->assertSuccessful();
        $this->artisan('terminvorschlag:wunschfristen')->assertSuccessful();

        Mail::assertQueued(OrganizerWishDueMail::class, fn ($mail) => $mail->hasTo('olaf.organizer@example.de'));
        Mail::assertQueued(OrganizerWishDueMail::class, 1);
    }
}
