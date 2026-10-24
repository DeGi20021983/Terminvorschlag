<x-mail::message>
# Ein Terminvorschlag für Sie

Guten Tag {{ $assignment->user->name() }},

für Ihre Wunschtermine bei **{{ $assignment->offer->title }}** war leider kein Termin mehr frei. Folgender freie Termin wurde Ihnen vorgeschlagen:

**{{ $assignment->slot->dateLabel() }}, {{ $assignment->slot->timeRange() }} Uhr**
Ort: {{ $assignment->offer->locationLabel() }}

Bitte nehmen Sie den Vorschlag oder lehnen Sie ihn unter „Meine Termine“ ab.

<x-mail::button :url="route('bookings.mine')">Vorschlag beantworten</x-mail::button>

Viele Grüße
{{ config('app.name') }}
</x-mail::message>
