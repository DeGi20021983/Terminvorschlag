<?php

namespace Tests\Feature\Wishes;

use App\Enums\AlternativeResponse;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\WishOutcome;
use App\Enums\WishRunStatus;
use App\Events\WishesReleased;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use App\Models\WishAssignment;
use App\Models\WishDraw;
use App\Models\WishEntry;
use App\Models\WishRun;
use App\Services\BookingService;
use App\Services\WishDistributionService;
use App\Services\WishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Die Tests prüfen Invarianten, die für jeden Ausgang einer Auslosung gelten.
 */
class WishDistributionTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Offer $offer;

    /** @var list<Slot> */
    private array $slots = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->organizer()->create();

        // Die Frist liegt in der Vergangenheit, damit die Verteilung sofort berechnet werden darf.
        $this->offer = Offer::factory()->withWishes()->create([
            'owner_user_id' => $this->organizer->id,
            'wish_deadline' => now()->subHour(),
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->slots[] = Slot::factory()->create([
                'offer_id' => $this->offer->id,
                'starts_at' => now()->addDays(3)->setTime(9 + $i, 0),
                'ends_at' => now()->addDays(3)->setTime(9 + $i, 30),
            ]);
        }
    }

    /**
     * @param  list<int>  $slotIndexes  Wünsche in Reihenfolge, als Index in $this->slots
     */
    private function wish(User $user, array $slotIndexes, int $minutesAgo = 10): void
    {
        foreach ($slotIndexes as $position => $index) {
            WishEntry::query()->create([
                'offer_id' => $this->offer->id,
                'user_id' => $user->id,
                'slot_id' => $this->slots[$index]->id,
                'priority' => $position + 1,
                'submitted_at' => now()->subMinutes($minutesAgo),
            ]);
        }
    }

    private function calculate(): WishRun
    {
        return app(WishDistributionService::class)->calculate($this->organizer, $this->offer);
    }

    private function release(): WishRun
    {
        return app(WishDistributionService::class)->release($this->organizer, $this->offer);
    }

    /**
     * Zwei Personen wollen beide nur Termin 0. Eine gewinnt, die andere bekommt den frühesten freien Termin als Vorschlag.
     */
    private function setUpLoserWithProposal(): WishAssignment
    {
        $this->wish(User::factory()->create(), [0]);
        $this->wish(User::factory()->create(), [0]);

        $this->calculate();
        $this->release();

        return WishAssignment::query()->where('alternative_response', AlternativeResponse::Pending)->sole();
    }

    public function test_exactly_one_person_wins_a_contested_first_wish_by_lot(): void
    {
        $anna = User::factory()->create();
        $ben = User::factory()->create();
        $this->wish($anna, [0]);
        $this->wish($ben, [0, 1]);

        $run = $this->calculate();

        $winners = $run->assignments()->where('slot_id', $this->slots[0]->id)->get();

        $this->assertCount(1, $winners, 'Termin 0 geht an genau eine Person.');
        $this->assertSame(WishOutcome::Wish1, $winners->first()->outcome);
        $this->assertTrue($winners->first()->by_lot, 'Bei Gleichstand entscheidet ein Los.');

        $loser = $run->assignments()->where('id', '!=', $winners->first()->id)->sole();

        // Der Verlierer bekommt Termin 1: als Zweitwunsch (Ben) oder als frühester freier Termin (Anna).
        $this->assertSame($this->slots[1]->id, $loser->slot_id);
        $this->assertContains($loser->outcome, [WishOutcome::Wish2, WishOutcome::Alternative]);
    }

    public function test_lot_is_protocolled_with_candidates_and_winner(): void
    {
        $people = User::factory()->count(3)->create();

        foreach ($people as $person) {
            $this->wish($person, [0]);
        }

        $run = $this->calculate();

        /** @var WishDraw $draw */
        $draw = $run->draws()->sole();

        $this->assertSame(1, $draw->round);
        $this->assertSame($this->slots[0]->id, $draw->slot_id);
        $this->assertEqualsCanonicalizing($people->pluck('id')->all(), $draw->candidate_user_ids);
        $this->assertContains($draw->winner_user_id, $draw->candidate_user_ids);
    }

    public function test_no_slot_and_no_person_is_assigned_twice(): void
    {
        $people = User::factory()->count(6)->create();

        foreach ($people as $index => $person) {
            $this->wish($person, [$index % 3, ($index + 1) % 3]);
        }

        $run = $this->calculate();

        $slotIds = $run->assignments()->whereNotNull('slot_id')->pluck('slot_id');

        $this->assertSame($slotIds->count(), $slotIds->unique()->count(), 'Jeder Termin höchstens einmal.');
        $this->assertSame(
            $run->assignments()->count(),
            $run->assignments()->distinct()->count('user_id'),
            'Jede Person höchstens einmal.',
        );
    }

    public function test_person_without_any_free_slot_gets_no_slot(): void
    {
        $people = User::factory()->count(4)->create();

        foreach ($people as $person) {
            $this->wish($person, [0, 1, 2]);
        }

        $run = $this->calculate();

        $this->assertSame(3, $run->assignments()->whereNotNull('slot_id')->count());
        $this->assertSame(1, $run->assignments()->where('outcome', WishOutcome::None)->count());
    }

    public function test_calculation_before_deadline_is_rejected(): void
    {
        $this->offer->update(['wish_deadline' => now()->addDay()]);
        $this->wish(User::factory()->create(), [0]);

        $this->expectException(BookingException::class);

        $this->calculate();
    }

    public function test_recalculation_supersedes_the_previous_proposal(): void
    {
        $this->wish(User::factory()->create(), [0]);

        $first = $this->calculate();
        $second = $this->calculate();

        $this->assertSame(WishRunStatus::Superseded, $first->fresh()->status);
        $this->assertSame(WishRunStatus::Proposed, $second->fresh()->status);
    }

    public function test_release_books_the_direct_winner_and_keeps_the_proposal_open(): void
    {
        Event::fake([WishesReleased::class]);

        $proposal = $this->setUpLoserWithProposal();

        $winner = WishAssignment::query()
            ->where('wish_run_id', $proposal->wish_run_id)
            ->where('alternative_response', null)
            ->sole();

        $this->assertDatabaseHas('bookings', [
            'user_id' => $winner->user_id,
            'slot_id' => $this->slots[0]->id,
            'source' => BookingSource::Wish->value,
            'status' => BookingStatus::Confirmed->value,
        ]);

        $this->assertSame(0, Booking::query()->where('user_id', $proposal->user_id)->count());
        $this->assertSame($this->slots[1]->id, $proposal->slot_id);
        $this->assertSame(WishOutcome::Alternative, $proposal->outcome);
        $this->assertNotNull($this->offer->fresh()->wishes_released_at);
        $this->assertSame(WishRunStatus::Released, $proposal->run->fresh()->status);

        Event::assertDispatched(WishesReleased::class);
    }

    public function test_pending_proposal_reserves_its_slot_against_instant_booking(): void
    {
        $proposal = $this->setUpLoserWithProposal();

        $this->expectException(BookingException::class);

        app(BookingService::class)->book(User::factory()->create(), $proposal->slot);
    }

    public function test_accepting_a_proposal_books_the_slot(): void
    {
        $proposal = $this->setUpLoserWithProposal();

        app(WishService::class)->accept($proposal->user, $proposal);

        $this->assertSame(AlternativeResponse::Accepted, $proposal->fresh()->alternative_response);
        $this->assertDatabaseHas('bookings', [
            'slot_id' => $proposal->slot_id,
            'user_id' => $proposal->user_id,
            'status' => BookingStatus::Confirmed->value,
            'source' => BookingSource::Wish->value,
        ]);
    }

    public function test_declining_a_proposal_frees_the_slot_for_instant_booking(): void
    {
        $proposal = $this->setUpLoserWithProposal();

        app(WishService::class)->decline($proposal->user, $proposal);

        $this->assertSame(AlternativeResponse::Declined, $proposal->fresh()->alternative_response);

        $booking = app(BookingService::class)->book(User::factory()->create(), $proposal->slot);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }

    public function test_another_person_cannot_answer_a_proposal(): void
    {
        $proposal = $this->setUpLoserWithProposal();

        $this->expectException(BookingException::class);

        app(WishService::class)->accept(User::factory()->create(), $proposal);
    }

    public function test_organizer_can_change_a_proposed_slot_but_not_to_a_taken_one(): void
    {
        $anna = User::factory()->create();
        $ben = User::factory()->create();
        $this->wish($anna, [0]);
        $this->wish($ben, [1]);

        $run = $this->calculate();
        $annaAssignment = $run->assignments()->where('user_id', $anna->id)->sole();
        $service = app(WishDistributionService::class);

        $service->changeAssignment($this->organizer, $annaAssignment, $this->slots[2]);
        $this->assertSame($this->slots[2]->id, $annaAssignment->fresh()->slot_id);

        $this->expectException(BookingException::class);

        // Termin 1 gehört bereits Ben.
        $service->changeAssignment($this->organizer, $annaAssignment, $this->slots[1]);
    }

    public function test_changing_after_release_is_rejected(): void
    {
        $this->wish(User::factory()->create(), [0]);

        $assignment = $this->calculate()->assignments()->sole();

        $this->release();

        $this->expectException(BookingException::class);

        app(WishDistributionService::class)->changeAssignment($this->organizer, $assignment, $this->slots[2]);
    }

    public function test_release_without_calculation_is_rejected(): void
    {
        $this->expectException(BookingException::class);

        $this->release();
    }
}
