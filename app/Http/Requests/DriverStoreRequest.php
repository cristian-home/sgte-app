<?php

namespace App\Http\Requests;

use App\Enums\LicenseCategory;
use App\Enums\Permission;
use App\Support\Tz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DriverStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows(Permission::CREATE_DRIVERS->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'identification_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'second_name' => ['nullable', 'string', 'max:100'],
            'first_lastname' => ['required', 'string', 'max:100'],
            'second_lastname' => ['nullable', 'string', 'max:100'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'license_category' => ['required', 'string', Rule::enum(LicenseCategory::class)],
            // "After today in operation TZ" — fixes F-004 vs the legacy
            // `after:today` rule which used the server's PHP TZ (UTC).
            'license_due_date' => ['required', 'date', 'after:'.Tz::nowIn(Tz::operation())->toDateString()],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            'eps_id' => ['required', 'integer', 'exists:eps,id'],
            'pension_fund_id' => ['required', 'integer', 'exists:pension_funds,id'],
            'severance_fund_id' => ['required', 'integer', 'exists:severance_funds,id'],
            'has_social_security' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ];
    }
}
