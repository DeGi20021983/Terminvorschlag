{{-- Formular für Anlegen und Bearbeiten. Erwartet $offer (oder null), $locations, $action, $method, $submit. --}}
@php
    use App\Enums\AllocationMode;

    $dt = fn ($value) => $value?->format('Y-m-d\TH:i');
    $hasOld = session()->hasOldInput();
@endphp

<form method="post" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="field">
        <label for="title">Titel <span aria-hidden="true">*</span></label>
        <input id="title" name="title" type="text" maxlength="200" required
               value="{{ old('title', $offer?->title) }}"
               @error('title') aria-invalid="true" aria-describedby="title-fehler" @enderror>
        @error('title')<p id="title-fehler" class="field__help hint">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="description">Beschreibung</label>
        <textarea id="description" name="description" maxlength="5000">{{ old('description', $offer?->description) }}</textarea>
    </div>

    <div class="form-row form-row--2">
        <div class="field">
            <label for="location_id">Standort</label>
            <select id="location_id" name="location_id">
                <option value="">Kein Standort</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) old('location_id', $offer?->location_id) === (string) $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
            @error('location_id')<p class="field__help hint">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="online_url">Online-Link (https)</label>
            <input id="online_url" name="online_url" type="url" maxlength="500" value="{{ old('online_url', $offer?->online_url) }}">
            <p class="field__help">Ort oder Online-Link angeben. Beides ist möglich.</p>
        </div>
    </div>

    <div class="form-row form-row--2">
        <div class="field">
            <label for="contact_name">Ansprechperson <span aria-hidden="true">*</span></label>
            <input id="contact_name" name="contact_name" type="text" maxlength="150" required value="{{ old('contact_name', $offer?->contact_name) }}">
        </div>
        <div class="field">
            <label for="target_group">Zielgruppe</label>
            <input id="target_group" name="target_group" type="text" maxlength="200" value="{{ old('target_group', $offer?->target_group) }}">
        </div>
    </div>

    <div class="field">
        <label for="area">Bereich / Organisation</label>
        <input id="area" name="area" type="text" maxlength="120" value="{{ old('area', $offer?->area) }}">
    </div>

    <fieldset class="fieldset">
        <legend>Vergabeverfahren</legend>
        <div class="options">
            @foreach (AllocationMode::cases() as $mode)
                <label class="field--check">
                    <input type="radio" name="allocation_mode" value="{{ $mode->value }}"
                           @checked(old('allocation_mode', $offer?->allocation_mode?->value ?? 'instant') === $mode->value)>
                    <span>{{ $mode->label() }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <div class="form-row form-row--2">
        <div class="field">
            <label for="bookable_from">Buchbar ab <span aria-hidden="true">*</span></label>
            <input id="bookable_from" name="bookable_from" type="datetime-local" required
                   value="{{ old('bookable_from', $dt($offer?->bookable_from)) }}">
        </div>
        <div class="field">
            <label for="bookable_until">Buchbar bis <span aria-hidden="true">*</span></label>
            <input id="bookable_until" name="bookable_until" type="datetime-local" required
                   value="{{ old('bookable_until', $dt($offer?->bookable_until)) }}">
        </div>
    </div>

    <div class="field">
        <label for="wish_deadline">Frist für Wunschtermine</label>
        <input id="wish_deadline" name="wish_deadline" type="datetime-local"
               value="{{ old('wish_deadline', $dt($offer?->wish_deadline)) }}">
        <p class="field__help">Nur bei Wunschtermin-Vergabe. Leer lassen: Standardfrist aus der Administration.</p>
    </div>

    <fieldset class="fieldset">
        <legend>Regeln</legend>
        <div class="options">
            <label class="field--check">
                <input type="checkbox" name="one_booking_per_person" value="1"
                       @checked($hasOld ? (bool) old('one_booking_per_person') : ($offer?->one_booking_per_person ?? true))>
                <span>Höchstens ein Termin je Person und Angebot</span>
            </label>
            <label class="field--check">
                <input type="checkbox" name="reminders_enabled" value="1"
                       @checked($hasOld ? (bool) old('reminders_enabled') : ($offer?->reminders_enabled ?? true))>
                <span>Erinnerung einen Tag vor dem Termin senden</span>
            </label>
        </div>
    </fieldset>

    <div class="button-row">
        <button class="btn btn--primary" type="submit">{{ $submit }}</button>
    </div>
</form>
