<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\PurgeRun;
use App\Models\Slot;
use App\Models\User;
use App\Models\WishEntry;
use App\Models\WishRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Löschlauf: entfernt personenbezogene Buchungsdaten nach Terminende.
 *
 * Bleiben erhalten: Termine mit dem anonymen Merker „belegt“ (slots.was_booked) sowie
 * Angebote selbst. Protokolleinträge werden nach der Aufbewahrungsfrist entfernt.
 */
final class RetentionService
{
    /**
     * @return array{status: string, anonymized_slots: int, deleted_bookings: int, deleted_wish_records: int, deleted_audit_entries: int, deleted_users: int}
     */
    public function run(CarbonImmutable $now): PurgeRun
    {
        $purge = PurgeRun::query()->create([
            'started_at' => $now,
            'status' => 'running',
        ]);

        try {
            $counts = DB::transaction(fn () => $this->purge($now));

            $purge->update(['status' => 'success', 'finished_at' => now()] + $counts);
        } catch (Throwable $exception) {
            report($exception);

            $purge->update([
                'status' => 'failed',
                'finished_at' => now(),
                'message' => Str::limit($exception->getMessage(), 500),
            ]);
        }

        return $purge->refresh();
    }

    /**
     * @return array<string, int>
     */
    private function purge(CarbonImmutable $now): array
    {
        $cutoff = $now->subHours((int) config('terminvorschlag.purge_grace_hours'));

        $endedSlotIds = Slot::query()->where('ends_at', '<', $cutoff)->pluck('id');

        // Anonymer Merker: der Termin war belegt. Danach fehlt jeder Personenbezug.
        $bookedSlotIds = Booking::query()->whereIn('slot_id', $endedSlotIds)->distinct()->pluck('slot_id');
        $anonymized = Slot::query()->whereIn('id', $bookedSlotIds)->where('was_booked', false)->update(['was_booked' => true]);

        $deletedBookings = Booking::query()->whereIn('slot_id', $endedSlotIds)->delete();

        $deletedWish = WishEntry::query()->whereIn('slot_id', $endedSlotIds)->delete();
        $deletedWish += DB::table('wish_assignments')->whereIn('slot_id', $endedSlotIds)->delete();
        $deletedWish += DB::table('wish_draws')->whereIn('slot_id', $endedSlotIds)->delete();

        // Angebote, deren letzter Termin vorbei ist: Verteilungsläufe komplett entfernen.
        $endedOfferIds = Offer::query()
            ->whereHas('slots')
            ->whereDoesntHave('slots', fn ($slot) => $slot->where('ends_at', '>=', $cutoff))
            ->pluck('id');

        $deletedWish += WishEntry::query()->whereIn('offer_id', $endedOfferIds)->delete();
        $deletedWish += WishRun::query()->whereIn('offer_id', $endedOfferIds)->delete();

        $deletedAudit = AuditLog::query()
            ->where('created_at', '<', $now->subDays((int) config('terminvorschlag.audit_retention_days')))
            ->delete();

        // Konten ohne jeden Bezug und ohne Anmeldung seit 30 Tagen. Anmeldung legt sie bei Bedarf neu an.
        $deletedUsers = User::query()
            ->whereDoesntHave('bookings')
            ->whereDoesntHave('offers')
            ->whereDoesntHave('wishEntries')
            ->whereDoesntHave('wishAssignments')
            ->whereDoesntHave('auditLogs')
            ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $now->subDays(30)))
            ->delete();

        return [
            'anonymized_slots' => $anonymized,
            'deleted_bookings' => $deletedBookings,
            'deleted_wish_records' => $deletedWish,
            'deleted_audit_entries' => $deletedAudit,
            'deleted_users' => $deletedUsers,
        ];
    }
}
