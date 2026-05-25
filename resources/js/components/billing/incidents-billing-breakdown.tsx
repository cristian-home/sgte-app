import { type ReactNode } from 'react';

export interface BillingIncidentRow {
    id: number;
    additional_value: string | number | null;
    description: string | null;
    reported_at?: string | null;
    incident_type?: { id: number; name: string } | null;
}

interface Props {
    unitValue: number | string | null | undefined;
    quantity: number | string | null | undefined;
    incidents: BillingIncidentRow[];
    /**
     * When true, the table still renders even if there are no incidents.
     * Defaults to false so the host card can hide the section entirely.
     */
    alwaysShow?: boolean;
    /** Optional trailing slot rendered below the totals row. */
    footnote?: ReactNode;
}

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});

function truncate(value: string, max: number): string {
    return value.length > max ? `${value.slice(0, max - 1)}…` : value;
}

/**
 * Mirrors the "Novedades que afectan facturación" totals section of the
 * invoice PDF (`resources/views/invoices/pdf.blade.php:300-355`) for a
 * single service: base row, one row per billing-affecting incident, total.
 * The numbers must match `InvoiceTotalCalculator::computeFor()` for the
 * same input — that service stays the source of truth.
 */
export function IncidentsBillingBreakdown({
    unitValue,
    quantity,
    incidents,
    alwaysShow = false,
    footnote,
}: Props) {
    if (!alwaysShow && incidents.length === 0) {
        return null;
    }

    const unit = Number(unitValue ?? 0);
    const qty = Number(quantity ?? 0);
    const baseTotal = Number.isFinite(unit * qty) ? unit * qty : 0;
    const incidentsTotal = incidents.reduce(
        (sum, incident) => sum + Number(incident.additional_value ?? 0),
        0,
    );
    const grandTotal = baseTotal + incidentsTotal;

    return (
        <div className="space-y-2">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <th className="pb-2 font-medium">Concepto</th>
                        <th className="pb-2 text-right font-medium">Valor</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    <tr>
                        <td className="py-2">
                            <span className="font-medium">Valor base</span>
                            <span className="ml-2 text-xs text-muted-foreground">
                                ({currencyFormatter.format(unit)} × {qty})
                            </span>
                        </td>
                        <td className="py-2 text-right tabular-nums">
                            {currencyFormatter.format(baseTotal)}
                        </td>
                    </tr>
                    {incidents.map((incident) => (
                        <tr key={incident.id}>
                            <td className="py-2">
                                <span className="font-medium">
                                    {incident.incident_type?.name ?? 'Novedad'}
                                </span>
                                {incident.description && (
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        — {truncate(incident.description, 100)}
                                    </span>
                                )}
                            </td>
                            <td className="py-2 text-right tabular-nums">
                                {incident.additional_value === null
                                    ? '—'
                                    : currencyFormatter.format(
                                          Number(incident.additional_value),
                                      )}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    {incidents.length > 0 && (
                        <tr className="text-xs text-muted-foreground">
                            <td className="pt-2 pb-1">Subtotal novedades</td>
                            <td className="pt-2 pb-1 text-right tabular-nums">
                                {currencyFormatter.format(incidentsTotal)}
                            </td>
                        </tr>
                    )}
                    <tr className="border-t-2">
                        <td className="pt-2 font-semibold">Total</td>
                        <td className="pt-2 text-right text-base font-bold tabular-nums">
                            {currencyFormatter.format(grandTotal)}
                        </td>
                    </tr>
                </tfoot>
            </table>
            {footnote}
        </div>
    );
}
