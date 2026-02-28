<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleLocationStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'recorded_at' => ['required'],
            'latitude' => ['required', 'numeric', 'between:-99.99999999,99.99999999'],
            'longitude' => ['required', 'numeric', 'between:-999.99999999,999.99999999'],
            'is_manual' => ['required'],
        ];
    }
}
