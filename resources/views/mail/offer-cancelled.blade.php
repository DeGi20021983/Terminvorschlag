<x-mail::message>
# Angebot abgesagt

Guten Tag {{ $booking->user->name() }},

das Angebot **{{ $booking->offer->title }}** wurde abgesagt. Ihr Termin am {{ $booking->slot->dateLabel() }}, {{ $booking->slot->timeRange() }} Uhr entfällt.
@if ($note)

Hinweis des Organisators: {{ $note }}
@endif

Wir bitten um Verständnis.

Viele Grüße
{{ config('app.name') }}
</x-mail::message>
