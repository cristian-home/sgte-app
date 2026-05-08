<?php

namespace App\Http\Requests;

use App\Enums\BillingUnitType;
use App\Enums\ContractObject;
use App\Enums\Permission;
use App\Support\Tz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContractUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::UPDATE_CONTRACTS->value);
    }

    public function rules(): array
    {
        return [
            'contract_number' => [Rule::when(! $this->boolean('is_generic'), ['required', 'string', 'max:50'], ['nullable', 'string', 'max:50'])],
            'third_party_id' => ['required', 'integer', 'exists:third_parties,id'],
            'contract_object' => ['required', Rule::enum(ContractObject::class)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date'],
            'route_description' => ['required', 'string'],
            'is_generic' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'billing_unit_type' => ['nullable', Rule::enum(BillingUnitType::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $timezone = $this->input('timezone');
        if (! is_string($timezone) || $timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = Tz::operation();
        }
        $this->merge(['timezone' => $timezone]);

        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');

        if (is_string($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $this->merge(['start_at' => Tz::startOfDayInTzAsUtc($startDate, $timezone)->utc()->toIso8601String()]);
        }
        if (is_string($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $this->merge(['end_at' => Tz::endOfDayInTzAsUtc($endDate, $timezone)->utc()->toIso8601String()]);
        }
    }
}
