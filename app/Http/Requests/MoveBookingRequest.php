<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveBookingRequest extends FormRequest
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
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
        ];
    }
}
