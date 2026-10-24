<?php

namespace Database\Seeders;

use App\Enums\AllocationMode;
use App\Enums\BookingSource;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demodaten für die lokale Entwicklung. Keine echten Personen; die Konten stammen aus config/directory.php.
 * Nicht in Produktion ausführen.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $raum = Location::query()->create(['name' => 'Raum 101', 'address' => 'Hauptstraße 1, 01067 Dresden', 'is_active' => true]);
        Location::query()->create(['name' => 'Schulungsraum Süd', 'address' => 'Am Park 4, 01097 Dresden', 'is_active' => true]);
        Location::query()->create(['name' => 'Alter Standort', 'address' => null, 'is_active' => false]);

        $olaf = User::factory()->organizer()->create(['username' => 'olaf.organizer', 'display_name' => 'Olaf Organisator', 'department' => 'Personalentwicklung']);
        $petra = User::factory()->organizer()->create(['username' => 'petra.planerin', 'display_name' => 'Petra Planerin', 'department' => 'Personalentwicklung']);
        $anna = User::factory()->create(['username' => 'anna.schmidt', 'display_name' => 'Anna Schmidt', 'department' => 'Bereich Nord']);
        $ben = User::factory()->create(['username' => 'ben.becker', 'display_name' => 'Ben Becker', 'department' => 'Bereich Süd']);
        $clara = User::factory()->create(['username' => 'clara.wolf', 'display_name' => 'Clara Wolf', 'department' => 'Bereich Nord']);

        $ergonomie = Offer::factory()->create([
            'owner_user_id' => $olaf->id,
            'title' => 'Ergonomie am Arbeitsplatz',
            'description' => 'Einstellung von Stuhl, Tisch und Bildschirm. Die Teilnahme dauert 30 Minuten.',
            'location_id' => $raum->id,
            'contact_name' => 'Olaf Organisator',
            'target_group' => 'Alle Mitarbeitenden',
            'area' => 'Personalentwicklung',
            'allocation_mode' => AllocationMode::Instant,
            'bookable_from' => now()->subWeek(),
            'bookable_until' => now()->addMonths(2),
        ]);

        $sprechstunde = Offer::factory()->create([
            'owner_user_id' => $petra->id,
            'title' => 'Sprechstunde Elternzeit',
            'description' => 'Fragen zu Elternzeit und Teilzeit. Bitte Unterlagen mitbringen.',
            'location_id' => null,
            'online_url' => 'https://meet.example.de/sprechstunde',
            'contact_name' => 'Petra Planerin',
            'target_group' => 'Eltern und Führungskräfte',
            'area' => 'Personalentwicklung',
            'allocation_mode' => AllocationMode::Wishes,
            'bookable_from' => now()->subDay(),
            'bookable_until' => now()->addMonth(),
            'wish_deadline' => now()->addDays(5),
        ]);

        $start = now()->addDays(7)->setTime(9, 0);

        for ($i = 0; $i < 6; $i++) {
            $slot = Slot::query()->create([
                'offer_id' => $ergonomie->id,
                'starts_at' => (clone $start)->addMinutes($i * 30),
                'ends_at' => (clone $start)->addMinutes(($i + 1) * 30),
            ]);

            if ($i < 2) {
                Booking::query()->create([
                    'offer_id' => $ergonomie->id,
                    'slot_id' => $slot->id,
                    'user_id' => $i === 0 ? $ben->id : $clara->id,
                    'status' => 'confirmed',
                    'source' => BookingSource::Instant,
                    'booked_at' => now(),
                ]);
            }
        }

        for ($i = 0; $i < 4; $i++) {
            Slot::query()->create([
                'offer_id' => $sprechstunde->id,
                'starts_at' => now()->addDays(10)->setTime(10 + $i, 0),
                'ends_at' => now()->addDays(10)->setTime(10 + $i, 45),
            ]);
        }

        AppSetting::put('wish_deadline_days_before', '3');
        AppSetting::put('reminder_hours_before', '24');
        AppSetting::put('confirmation_text', 'Bitte sagen Sie den Termin rechtzeitig ab, falls Sie verhindert sind.');

        $anna->update(['last_seen_at' => now()]);
    }
}
