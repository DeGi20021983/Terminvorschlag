<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Einzeltermin: Datum, Beginn und Ende.
 */
class SlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->input('date').' '.$this->input('start'));
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->input('date').' '.$this->input('end'));
    }
}
