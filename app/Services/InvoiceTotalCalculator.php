<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * Single source of truth for invoice total computation.
 *
 * Called by the three invoice billing endpoints (attachServices,
 * detachService, recomputeTotal) — each one routes through
 * `recomputeFor()` so the persisted total can never drift from the
 * computation rule. The pure `computeFor()` variant is used by the
 * show-page controller to surface stale-total detection without
 * side effects.
 *
 * Formula: sum(service.unit_value * service.quantity)
 *          + sum(service_incident.additional_value where affects_billing)
 *
 * Null-safety: services with null unit_value or null quantity
 * contribute 0 to the services sum. Incidents with null
 * additional_value contribute 0 to the incidents sum.
 */
class InvoiceTotalCalculator
{
    /**
     * Compute and persist the invoice total. Returns the newly-written
     * decimal string for convenience.
     */
    public function recomputeFor(Invoice $invoice): string
    {
        $total = $this->computeFor($invoice);
        $invoice->update(['total_value' => $total]);

        return $total;
    }

    /**
     * Compute the invoice total WITHOUT persisting. Used by the show
     * controller to pass `computed_total` alongside `invoice` so the
     * frontend can surface a "Total desactualizado" pill when the
     * persisted `total_value` drifts from the current computation.
     */
    public function computeFor(Invoice $invoice): string
    {
        $invoice->loadMissing('services.serviceIncidents');

        $servicesTotal = $invoice->services->sum(function ($service) {
            if ($service->unit_value === null || $service->quantity === null) {
                return 0;
            }

            return (float) $service->unit_value * (int) $service->quantity;
        });

        $incidentsTotal = $invoice->services->sum(function ($service) {
            return $service->serviceIncidents
                ->where('affects_billing', true)
                ->sum(function ($incident) {
                    return $incident->additional_value === null
                        ? 0
                        : (float) $incident->additional_value;
                });
        });

        return number_format($servicesTotal + $incidentsTotal, 2, '.', '');
    }
}
