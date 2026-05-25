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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
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

        $start = $this->normalizeYmd($this->input('start_date'));
        $end = $this->normalizeYmd($this->input('end_date'));

        if ($start !== null) {
            $this->merge(['start_at' => Tz::startOfDayInTzAsUtc($start, $timezone)->utc()->toIso8601String()]);
        }
        if ($end !== null) {
            $this->merge(['end_at' => Tz::endOfDayInTzAsUtc($end, $timezone)->utc()->toIso8601String()]);
        }
    }

    protected function normalizeYmd(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (! is_string($value) || $value === '') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
            return $m[1];
        }

        return null;
    }
}
