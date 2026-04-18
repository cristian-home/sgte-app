<?php

namespace App\Rules;

use App\Models\Invoice;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects `total_value` updates on an invoice whose `services_count > 0`.
 *
 * Once services are attached, total_value is authoritatively computed
 * by `InvoiceTotalCalculator` — accepting a manual value here would
 * silently diverge from the sum of attached services + billing-affecting
 * incidents. The rule permits unchanged values so an untouched form
 * submit (user edits notes only, leaves total_value alone) still passes.
 */
class TotalValueLockedWhenServicesAttached implements ValidationRule
{
    public function __construct(private readonly ?Invoice $invoice) {}

    /**
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->invoice === null) {
            return;
        }

        if ($this->invoice->services()->count() === 0) {
            return;
        }

        // Permit unchanged values so form-wide submits that don't touch
        // total_value still pass. Compare as floats to avoid decimal
        // string-representation drift (e.g. "1000" vs "1000.00").
        if ((float) $value === (float) $this->invoice->total_value) {
            return;
        }

        $fail('El valor total se calcula automáticamente cuando hay servicios asociados.');
    }
}
