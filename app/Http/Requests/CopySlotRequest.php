<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Termin kopieren: neues Datum und neue Startzeit. Die Dauer bleibt gleich.
 */
class CopySlotRequest extends FormRequest
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
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->input('date').' '.$this->input('start'));
    }
}
