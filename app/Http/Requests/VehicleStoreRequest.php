<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleStoreRequest extends FormRequest
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
            'internal_code' => ['required', 'string', 'max:20'],
            'plate' => ['required', 'string', 'max:6'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'brand' => ['required', 'string', 'max:50'],
            'line' => ['required', 'string', 'max:50'],
            'model_year' => ['required', 'integer'],
            'type' => ['required', 'in:bus,buseta,van,automobile'],
            'engine_number' => ['required', 'string', 'max:50'],
            'chassis_number' => ['required', 'string', 'max:50'],
            'capacity' => ['required', 'integer'],
            'city' => ['required', 'string', 'max:100'],
            'is_third_party' => ['required'],
            'third_party_id' => ['nullable', 'integer', 'exists:third_parties,id'],
            'soat_due_date' => ['required', 'date'],
            'rtm_due_date' => ['required', 'date'],
            'operation_card_due_date' => ['required', 'date'],
            'status' => ['required', 'in:active,maintenance,retired'],
        ];
    }
}
