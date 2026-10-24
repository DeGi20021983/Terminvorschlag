<?php

namespace App\Http\Requests;

use App\Enums\AllocationMode;
use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Die Feinprüfung (eigenes Angebot) passiert in der Policy im Controller.
        return $this->user()?->is_organizer === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'online_url' => ['nullable', 'string', 'url:https', 'max:500'],
            'contact_name' => ['required', 'string', 'max:150'],
            'target_group' => ['nullable', 'string', 'max:200'],
            'area' => ['nullable', 'string', 'max:120'],
            'allocation_mode' => ['required', Rule::enum(AllocationMode::class)],
            'bookable_from' => ['required', 'date'],
            'bookable_until' => ['required', 'date', 'after_or_equal:bookable_from'],
            'wish_deadline' => ['nullable', 'date', 'before:bookable_until'],
            'one_booking_per_person' => ['sometimes', 'boolean'],
            'reminders_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('location_id') && ! $this->filled('online_url')) {
                    $validator->errors()->add('location_id', 'Bitte einen Ort oder einen Online-Link angeben.');
                }
            },
        ];
    }

    /**
     * Bei Wunschterminen ohne Angabe gilt die Standardfrist aus der Administration.
     *
     * @param  array<string, mixed>  $validated
     */
    private function wishDeadline(array $validated): ?CarbonImmutable
    {
        if (($validated['allocation_mode'] ?? null) !== AllocationMode::Wishes->value) {
            return null;
        }

        if (! empty($validated['wish_deadline'])) {
            return CarbonImmutable::parse($validated['wish_deadline']);
        }

        $days = (int) AppSetting::get('wish_deadline_days_before', (string) config('terminvorschlag.defaults.wish_deadline_days_before'));

        return CarbonImmutable::parse($validated['bookable_from'])->subDays($days);
    }

    /**
     * Die Daten in der Form, wie der Service sie braucht.
     *
     * @return array<string, mixed>
     */
    public function offerData(): array
    {
        $validated = $this->validated();

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'online_url' => $validated['online_url'] ?? null,
            'contact_name' => $validated['contact_name'],
            'target_group' => $validated['target_group'] ?? null,
            'area' => $validated['area'] ?? null,
            'allocation_mode' => $validated['allocation_mode'],
            'bookable_from' => CarbonImmutable::parse($validated['bookable_from']),
            'bookable_until' => CarbonImmutable::parse($validated['bookable_until']),
            'wish_deadline' => $this->wishDeadline($validated),
            'one_booking_per_person' => $this->boolean('one_booking_per_person', true),
            'reminders_enabled' => $this->boolean('reminders_enabled', true),
        ];
    }
}
