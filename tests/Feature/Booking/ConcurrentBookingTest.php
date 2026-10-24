<?php

namespace Tests\Feature\Booking;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Echte Parallelität: Jeder Prozess hat eine eigene Datenbankverbindung und startet
 * zum selben Zeitpunkt. Ohne Transaktionsschutz würde mehr als eine Buchung gelingen.
 *
 * Braucht die Erweiterung pcntl und eine Datenbank, die Sperren unterstützt (MariaDB).
 */
class ConcurrentBookingTest extends TestCase
{
    use DatabaseTruncation;

    private const OUTCOME_BOOKED = 0;

    private const OUTCOME_REJECTED = 10;

    private const OUTCOME_ERROR = 20;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl ist nicht verfügbar.');
        }

        config(['terminvorschlag.force_https' => false]);
    }

    public function test_only_one_of_several_simultaneous_bookings_for_a_slot_succeeds(): void
    {
        $offer = Offer::factory()->create();
        $slot = Slot::factory()->create(['offer_id' => $offer->id]);
        $users = User::factory()->count(8)->create();

        $jobs = $users->map(fn (User $user) => fn () => app(BookingService::class)->book($user, Slot::query()->findOrFail($slot->id)))->all();

        $outcomes = $this->runConcurrently($jobs);

        $this->assertSame(1, $this->countOutcomes($outcomes, self::OUTCOME_BOOKED), 'Genau eine Buchung darf gelingen.');
        $this->assertSame(7, $this->countOutcomes($outcomes, self::OUTCOME_REJECTED));
        $this->assertSame(0, $this->countOutcomes($outcomes, self::OUTCOME_ERROR), 'Keine unerwarteten Fehler.');
        $this->assertSame(1, Booking::query()->confirmed()->where('slot_id', $slot->id)->count());
    }

    public function test_one_person_cannot_book_two_slots_of_the_same_offer_in_parallel(): void
    {
        $offer = Offer::factory()->create();
        $anna = User::factory()->create();
        $slots = collect(range(0, 4))->map(fn (int $i) => Slot::factory()->create([
            'offer_id' => $offer->id,
            'starts_at' => now()->addDays(3)->setTime(9 + $i, 0),
            'ends_at' => now()->addDays(3)->setTime(9 + $i, 30),
        ]));

        $jobs = $slots->map(fn (Slot $slot) => fn () => app(BookingService::class)->book($anna, Slot::query()->findOrFail($slot->id)))->all();

        $outcomes = $this->runConcurrently($jobs);

        $this->assertSame(1, $this->countOutcomes($outcomes, self::OUTCOME_BOOKED));
        $this->assertSame(4, $this->countOutcomes($outcomes, self::OUTCOME_REJECTED));
        $this->assertSame(1, Booking::query()->confirmed()->where('user_id', $anna->id)->count());
    }

    protected function tearDown(): void
    {
        // DatabaseTruncation leert nur vor einem Test. Damit andere Tests mit leerer Datenbank starten,
        // werden die committeten Daten dieses Tests danach ebenfalls entfernt.
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    /**
     * Führt jede Aufgabe in einem eigenen Prozess aus und liefert je Aufgabe das Ergebnis.
     *
     * @param  list<callable(): mixed>  $jobs
     * @return list<int>
     */
    private function runConcurrently(array $jobs): array
    {
        // Vor dem Fork darf keine Verbindung offen sein, sonst teilen sich Prozesse einen Socket.
        DB::disconnect();

        $startAt = microtime(true) + 0.5;
        $children = [];

        foreach ($jobs as $job) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('fork fehlgeschlagen');
            }

            if ($pid === 0) {
                $code = self::OUTCOME_ERROR;

                try {
                    while (microtime(true) < $startAt) {
                        usleep(500);
                    }

                    DB::reconnect();
                    $job();
                    $code = self::OUTCOME_BOOKED;
                } catch (BookingException) {
                    $code = self::OUTCOME_REJECTED;
                } catch (Throwable) {
                    $code = self::OUTCOME_ERROR;
                }

                DB::disconnect();
                exit($code);
            }

            $children[] = $pid;
        }

        $outcomes = [];

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $outcomes[] = pcntl_wexitstatus($status);
        }

        return $outcomes;
    }

    /**
     * @param  list<int>  $outcomes
     */
    private function countOutcomes(array $outcomes, int $expected): int
    {
        return count(array_filter($outcomes, fn (int $outcome) => $outcome === $expected));
    }
}
