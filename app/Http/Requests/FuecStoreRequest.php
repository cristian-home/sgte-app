<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FuecStoreRequest extends FormRequest
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
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'consecutive_number' => ['required', 'integer'],
            'generated_at' => ['required'],
            'qr_code' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,cancelled'],
            'pdf_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
