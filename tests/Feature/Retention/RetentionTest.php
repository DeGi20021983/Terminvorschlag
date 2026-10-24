<?php

namespace Tests\Feature\Retention;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\PurgeRun;
use App\Models\Slot;
use App\Models\User;
use App\Models\WishEntry;
use App\Models\WishRun;
use App\Services\RetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use RefreshDatabase;

    private function pastSlot(Offer $offer): Slot
    {
        return Slot::factory()->create([
            'offer_id' => $offer->id,
            'starts_at' => now()->subDays(2)->setTime(9, 0),
            'ends_at' => now()->subDays(2)->setTime(9, 30),
        ]);
    }

    public function test_personal_booking_data_is_removed_after_the_slot_has_ended(): void
    {
        $offer = Offer::factory()->create();
        $past = $this->pastSlot($offer);
        Booking::factory()->forSlot($past)->create();

        $future = Slot::factory()->create(['offer_id' => $offer->id]);
        Booking::factory()->forSlot($future)->create();

        $purge = app(RetentionService::class)->run(CarbonImmutable::now());

        $this->assertSame('success', $purge->status);
        $this->assertSame(0, Booking::query()->where('slot_id', $past->id)->count());
        $this->assertSame(1, Booking::query()->where('slot_id', $future->id)->count());
        $this->assertSame(1, $purge->deleted_bookings);
    }

    public function test_anonymous_statistic_survives_the_purge(): void
    {
        $offer = Offer::factory()->create();
        $past = $this->pastSlot($offer);
        Booking::factory()->forSlot($past)->create();

        app(RetentionService::class)->run(CarbonImmutable::now());

        $this->assertTrue($past->fresh()->was_booked, 'Der Termin bleibt als „belegt“ erhalten.');
        $this->assertDatabaseHas('slots', ['id' => $past->id]);
    }

    public function test_wish_records_of_ended_slots_and_offers_are_removed(): void
    {
        $offer = Offer::factory()->withWishes()->create(['wish_deadline' => now()->subDays(5)]);
        $past = $this->pastSlot($offer);
        $person = User::factory()->create();
        WishEntry::query()->create(['offer_id' => $offer->id, 'user_id' => $person->id, 'slot_id' => $past->id, 'priority' => 1, 'submitted_at' => now()->subDays(6)]);
        WishRun::query()->create(['offer_id' => $offer->id, 'status' => 'released', 'calculated_at' => now()->subDays(4)]);

        $activeOffer = Offer::factory()->withWishes()->create();
        $activeSlot = Slot::factory()->create(['offer_id' => $activeOffer->id]);
        WishEntry::query()->create(['offer_id' => $activeOffer->id, 'user_id' => $person->id, 'slot_id' => $activeSlot->id, 'priority' => 1, 'submitted_at' => now()]);

        app(RetentionService::class)->run(CarbonImmutable::now());

        $this->assertSame(0, WishEntry::query()->where('offer_id', $offer->id)->count());
        $this->assertSame(0, WishRun::query()->where('offer_id', $offer->id)->count());
        $this->assertSame(1, WishEntry::query()->where('offer_id', $activeOffer->id)->count());
    }

    public function test_audit_entries_are_removed_after_the_retention_period(): void
    {
        config(['terminvorschlag.audit_retention_days' => 365]);
        $actor = User::factory()->organizer()->create();

        AuditLog::query()->create(['actor_user_id' => $actor->id, 'actor_username' => $actor->username, 'action' => 'offer.created', 'created_at' => now()->subYears(2)]);
        AuditLog::query()->create(['actor_user_id' => $actor->id, 'actor_username' => $actor->username, 'action' => 'offer.updated', 'created_at' => now()->subDays(10)]);

        $purge = app(RetentionService::class)->run(CarbonImmutable::now());

        $this->assertSame(1, AuditLog::query()->count());
        $this->assertSame(1, $purge->deleted_audit_entries);
    }

    public function test_accounts_without_any_reference_are_removed_and_referenced_ones_stay(): void
    {
        $orphan = User::factory()->create(['last_seen_at' => now()->subDays(60)]);
        $withBooking = User::factory()->create(['last_seen_at' => now()->subDays(60)]);
        Booking::factory()->forSlot(Slot::factory()->create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addMinutes(30)]))
            ->create(['user_id' => $withBooking->id]);

        $purge = app(RetentionService::class)->run(CarbonImmutable::now());

        $this->assertDatabaseMissing('users', ['id' => $orphan->id]);
        $this->assertDatabaseHas('users', ['id' => $withBooking->id]);
        $this->assertSame(1, $purge->deleted_users);
    }

    public function test_command_records_the_run_and_reports_success(): void
    {
        $this->artisan('terminvorschlag:loeschen')->assertSuccessful();

        $this->assertSame(1, PurgeRun::query()->count());
        $this->assertSame('success', PurgeRun::query()->sole()->status);
    }
}
