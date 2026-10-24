<?php

namespace Tests\Unit\Models;

use App\Enums\AllocationMode;
use App\Enums\OfferStatus;
use App\Models\Offer;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class OfferWindowTest extends TestCase
{
    private function offer(array $attributes = []): Offer
    {
        $offer = new Offer;
        $offer->forceFill(array_merge([
            'status' => OfferStatus::Published,
            'allocation_mode' => AllocationMode::Instant,
            'bookable_from' => CarbonImmutable::parse('2026-11-01 00:00'),
            'bookable_until' => CarbonImmutable::parse('2026-11-30 23:59'),
            'wish_deadline' => null,
            'wishes_released_at' => null,
        ], $attributes));

        return $offer;
    }

    public function test_booking_window_includes_both_boundaries(): void
    {
        $offer = $this->offer();

        $this->assertTrue($offer->isInBookingWindow(CarbonImmutable::parse('2026-11-01 00:00')));
        $this->assertTrue($offer->isInBookingWindow(CarbonImmutable::parse('2026-11-30 23:59')));
        $this->assertFalse($offer->isInBookingWindow(CarbonImmutable::parse('2026-10-31 23:59')));
        $this->assertFalse($offer->isInBookingWindow(CarbonImmutable::parse('2026-12-01 00:00')));
    }

    public function test_draft_and_cancelled_offers_are_never_in_booking_window(): void
    {
        $now = CarbonImmutable::parse('2026-11-10 10:00');

        $this->assertFalse($this->offer(['status' => OfferStatus::Draft])->isInBookingWindow($now));
        $this->assertFalse($this->offer(['status' => OfferStatus::Cancelled])->isInBookingWindow($now));
    }

    public function test_instant_booking_is_open_only_after_wish_release(): void
    {
        $wishOffer = $this->offer([
            'allocation_mode' => AllocationMode::Wishes,
            'wish_deadline' => CarbonImmutable::parse('2026-10-28 12:00'),
        ]);

        $this->assertFalse($wishOffer->instantBookingOpen());

        $wishOffer->wishes_released_at = CarbonImmutable::parse('2026-10-29 09:00');

        $this->assertTrue($wishOffer->instantBookingOpen());
    }

    public function test_wishes_are_open_until_deadline_and_before_release(): void
    {
        $offer = $this->offer([
            'allocation_mode' => AllocationMode::Wishes,
            'wish_deadline' => CarbonImmutable::parse('2026-10-28 12:00'),
        ]);

        $this->assertTrue($offer->wishesOpen(CarbonImmutable::parse('2026-10-28 11:59')));
        $this->assertFalse($offer->wishesOpen(CarbonImmutable::parse('2026-10-28 12:00')));

        $offer->wishes_released_at = CarbonImmutable::parse('2026-10-27 08:00');

        $this->assertFalse($offer->wishesOpen(CarbonImmutable::parse('2026-10-27 09:00')));
    }
}
