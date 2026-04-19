<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class VehicleLocationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::REGISTER_VEHICLE_LOCATION->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'recorded_at' => ['required', 'date'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_manual' => ['required', 'boolean'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
            'accuracy.max' => 'La precisión no puede superar los 10000 metros.',
        ];
    }
}
