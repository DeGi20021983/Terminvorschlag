<x-mail::message>
# Die Wunschfrist ist abgelaufen

Die Wunschfrist für **{{ $offer->title }}** ist abgelaufen. Sie können die Wunschverteilung jetzt berechnen, prüfen und freigeben.

<x-mail::button :url="route('manage.wishes.index', ['offer' => $offer->id])">Wunschverteilung öffnen</x-mail::button>

Viele Grüße
{{ config('app.name') }}
</x-mail::message>
