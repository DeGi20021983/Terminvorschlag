{{-- Ein Termin in der Buchungsansicht. Belegte Termine zeigen nie einen Namen. --}}
@php($status = $state($termin))
@if ($status === 'free')
    <a class="slot-button slot-button--free @if ($selected?->id === $termin->id) slot-button--selected @endif"
       href="{{ route('offers.show', ['offer' => $offer, 'termin' => $termin->id]) }}"
       @if ($selected?->id === $termin->id) aria-current="true" @endif>
        <span>{{ $termin->dateLabel() }}</span>
        <span class="slot-button__time">{{ $termin->timeRange() }}</span>
        <span class="slot-button__state">@if ($selected?->id === $termin->id) Ausgewählt @else Frei · auswählen @endif</span>
    </a>
@elseif ($status === 'wish')
    <div class="slot-button slot-button--wish">
        <span>{{ $termin->dateLabel() }}</span>
        <span class="slot-button__time">{{ $termin->timeRange() }}</span>
        <span class="slot-button__state">Wunschtermin</span>
    </div>
@elseif ($status === 'mine')
    <div class="slot-button slot-button--mine">
        <span>{{ $termin->dateLabel() }}</span>
        <span class="slot-button__time">{{ $termin->timeRange() }}</span>
        <span class="slot-button__state">Ihr Termin</span>
    </div>
@elseif ($status === 'booked')
    <div class="slot-button slot-button--booked" aria-disabled="true">
        <span>{{ $termin->dateLabel() }}</span>
        <span class="slot-button__time">{{ $termin->timeRange() }}</span>
        <span class="slot-button__state">Belegt</span>
    </div>
@else
    <div class="slot-button slot-button--booked" aria-disabled="true">
        <span>{{ $termin->dateLabel() }}</span>
        <span class="slot-button__time">{{ $termin->timeRange() }}</span>
        <span class="slot-button__state">{{ $status === 'past' ? 'Vorbei' : 'Nicht buchbar' }}</span>
    </div>
@endif
