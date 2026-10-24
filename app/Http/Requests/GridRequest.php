<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Zeitraster für einen Tag, z. B. 9:00 bis 12:00 in 30-Minuten-Slots.
 */
class GridRequest extends FormRequest
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
            'from' => ['required', 'date_format:H:i'],
            'to' => ['required', 'date_format:H:i', 'after:from'],
            'minutes' => ['required', 'integer', 'in:5,10,15,20,30,45,60,90,120'],
        ];
    }

    public function day(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->input('date'))->startOfDay();
    }
}
