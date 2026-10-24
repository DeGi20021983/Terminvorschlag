<?php

namespace Tests\Feature\Wishes;

use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use App\Models\WishAssignment;
use App\Models\WishEntry;
use App\Services\WishDistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsIn;
use Tests\TestCase;

class WishSubmissionTest extends TestCase
{
    use RefreshDatabase, SignsIn;

    private Offer $offer;

    /** @var list<Slot> */
    private array $slots = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['terminvorschlag.force_https' => false]);

        $this->offer = Offer::factory()->withWishes()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->slots[] = Slot::factory()->create([
                'offer_id' => $this->offer->id,
                'starts_at' => now()->addDays(3)->setTime(9 + $i, 0),
                'ends_at' => now()->addDays(3)->setTime(9 + $i, 30),
            ]);
        }
    }

    public function test_employee_submits_three_wishes_in_order(): void
    {
        $anna = User::factory()->create();

        $this->signInAs($anna)
            ->post(route('wishes.store', $this->offer), [
                'wishes' => [1 => $this->slots[2]->id, 2 => $this->slots[0]->id, 3 => $this->slots[1]->id],
            ])
            ->assertSessionHasNoErrors();

        $entries = WishEntry::query()->where('user_id', $anna->id)->orderBy('priority')->get();

        $this->assertSame([1, 2, 3], $entries->pluck('priority')->all());
        $this->assertSame([$this->slots[2]->id, $this->slots[0]->id, $this->slots[1]->id], $entries->pluck('slot_id')->all());
    }

    public function test_resubmitting_replaces_the_previous_wishes(): void
    {
        $anna = User::factory()->create();

        $this->signInAs($anna)->post(route('wishes.store', $this->offer), [
            'wishes' => [1 => $this->slots[0]->id, 2 => $this->slots[1]->id],
        ]);
        $this->signInAs($anna)->post(route('wishes.store', $this->offer), [
            'wishes' => [1 => $this->slots[3]->id, 2 => $this->slots[2]->id],
        ]);

        $this->assertSame(2, WishEntry::query()->where('user_id', $anna->id)->count());
        $this->assertDatabaseHas('wish_entries', ['user_id' => $anna->id, 'slot_id' => $this->slots[3]->id, 'priority' => 1]);
    }

    public function test_single_wish_is_rejected_when_at_least_two_slots_are_free(): void
    {
        $this->signInAs(User::factory()->create())
            ->post(route('wishes.store', $this->offer), ['wishes' => [1 => $this->slots[0]->id]])
            ->assertSessionHasErrors(['wish']);

        $this->assertDatabaseCount('wish_entries', 0);
    }

    public function test_wishes_must_be_filled_in_order(): void
    {
        $this->signInAs(User::factory()->create())
            ->post(route('wishes.store', $this->offer), [
                'wishes' => [1 => '', 2 => $this->slots[0]->id, 3 => $this->slots[1]->id],
            ])
            ->assertSessionHasErrors(['wish']);

        $this->assertDatabaseCount('wish_entries', 0);
    }

    public function test_same_slot_twice_is_rejected(): void
    {
        $this->signInAs(User::factory()->create())
            ->post(route('wishes.store', $this->offer), [
                'wishes' => [1 => $this->slots[0]->id, 2 => $this->slots[0]->id],
            ])
            ->assertSessionHasErrors(['wish']);
    }

    public function test_wishes_are_rejected_after_the_deadline(): void
    {
        $this->offer->update(['wish_deadline' => now()->subMinute()]);

        $this->signInAs(User::factory()->create())
            ->post(route('wishes.store', $this->offer), [
                'wishes' => [1 => $this->slots[0]->id, 2 => $this->slots[1]->id],
            ])
            ->assertSessionHasErrors(['wish']);

        $this->assertDatabaseCount('wish_entries', 0);
    }

    public function test_wish_for_a_slot_that_is_already_booked_is_rejected(): void
    {
        $booked = $this->slots[0];
        Booking::factory()->forSlot($booked)->create();

        $this->signInAs(User::factory()->create())
            ->post(route('wishes.store', $this->offer), [
                'wishes' => [1 => $booked->id, 2 => $this->slots[1]->id],
            ])
            ->assertSessionHasErrors(['wish']);
    }

    public function test_wish_form_is_shown_while_the_deadline_is_open(): void
    {
        $this->signInAs(User::factory()->create())
            ->get(route('offers.show', $this->offer))
            ->assertOk()
            ->assertSee('Ihre Wunschtermine')
            ->assertSee('Wunschtermin');
    }

    public function test_proposals_can_be_accepted_from_my_bookings(): void
    {
        $this->offer->update(['wish_deadline' => now()->subHour(), 'owner_user_id' => User::factory()->organizer()->create()->id]);

        // Zwei Personen wollen nur Termin 0. Eine gewinnt, die andere bekommt einen Vorschlag.
        WishEntry::query()->create(['offer_id' => $this->offer->id, 'user_id' => User::factory()->create()->id, 'slot_id' => $this->slots[0]->id, 'priority' => 1, 'submitted_at' => now()]);
        WishEntry::query()->create(['offer_id' => $this->offer->id, 'user_id' => User::factory()->create()->id, 'slot_id' => $this->slots[0]->id, 'priority' => 1, 'submitted_at' => now()]);

        $service = app(WishDistributionService::class);
        $service->calculate($this->offer->owner, $this->offer);
        $service->release($this->offer->owner, $this->offer);

        $proposal = WishAssignment::query()->where('alternative_response', 'pending')->sole();
        $loser = $proposal->user;

        $this->signInAs($loser)
            ->get(route('bookings.mine'))
            ->assertOk()
            ->assertSee('Alternative vorgeschlagen')
            ->assertSee(route('wishes.accept', $proposal), false);

        $this->signInAs($loser)
            ->post(route('wishes.accept', $proposal))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['user_id' => $loser->id, 'slot_id' => $proposal->slot_id, 'source' => 'wish']);
    }
}
