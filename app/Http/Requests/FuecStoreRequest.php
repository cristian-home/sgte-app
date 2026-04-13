<?php

namespace App\Http\Requests;

use App\Enums\FuecStatus;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FuecStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows(Permission::GENERATE_FUEC->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'consecutive_number' => ['required', 'integer'],
            'generated_at' => ['required'],
            'qr_code' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(FuecStatus::class)],
            'pdf_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
