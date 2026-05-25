<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Models\Service;
use App\Rules\FuecPreGenerationChecks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates the input of `FuecController@store`. Accepts a single
 * `service_id` and delegates the full gauntlet of domain checks to
 * `FuecPreGenerationChecks`. The Blueprint scaffold's per-field
 * rules (consecutive_number, qr_code, status, pdf_url) have been
 * removed — those values are all computed server-side by
 * `FuecGenerator`, not submitted by the user.
 */
class FuecStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::GENERATE_FUEC->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    // Syntactic validation already failed (e.g. non-existent
                    // service_id); no point running domain checks.
                    return;
                }

                $service = Service::query()
                    ->with(['contract', 'vehicle', 'driver'])
                    ->find($this->input('service_id'));

                (new FuecPreGenerationChecks($service))->run($validator);
            },
        ];
    }
}
