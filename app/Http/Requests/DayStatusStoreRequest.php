<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DayStatusStoreRequest extends FormRequest
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
            'date' => ['required', 'date', 'unique:day_statuses,date'],
            'status' => ['required', 'in:projected,executed'],
            'executor_id' => ['nullable', 'integer', 'exists:users,id'],
            'executed_at' => ['nullable'],
        ];
    }
}
