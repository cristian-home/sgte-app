<?php

namespace App\Http\Requests;

use App\Enums\DayStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DayStatusUpdateRequest extends FormRequest
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
            'date' => ['required', 'date', Rule::unique('day_statuses', 'date')->ignore($this->route('day_status'))],
            'status' => ['required', Rule::enum(DayStatusEnum::class)],
            'executor_id' => ['nullable', 'integer', 'exists:users,id'],
            'executed_at' => ['nullable'],
        ];
    }
}
