import { Badge } from '@/components/ui/badge';

type InvoiceInput = {
    payment_status: string;
};

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pendiente',
    paid: 'Pagado',
    overdue: 'Vencido!',
};

const STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'secondary',
    paid: 'default',
    overdue: 'destructive',
};

const TOOLTIPS: Record<string, string> = {
    pending: 'Factura pendiente de pago',
    paid: 'Factura pagada',
    overdue: 'Factura vencida — requiere acción',
};

/**
 * Single Badge summarizing an invoice's payment state.
 *
 * Unlike the document/contract pills, the status here is a manual
 * enum column — no date math. This component lives alongside the
 * invoices feature folder rather than inside `lib/document-status.ts`
 * because payment status is NOT a date-derived axis.
 */
export function PaymentStatusPill({
    invoice,
    className,
}: {
    invoice: InvoiceInput;
    className?: string;
}) {
    const status = invoice.payment_status;
    const label = STATUS_LABELS[status] ?? status;
    const variant = STATUS_VARIANTS[status] ?? 'outline';

    return (
        <Badge variant={variant} title={TOOLTIPS[status]} className={className}>
            {label}
        </Badge>
    );
}

/**
 * Public helper exposed so the invoices index can compute the row
 * tint without re-instantiating the pill component. Returns the
 * shadcn utility class(es) to merge onto the row.
 */
export function paymentStatusRowTint(
    invoice: InvoiceInput,
): string | undefined {
    switch (invoice.payment_status) {
        case 'overdue':
            return 'bg-destructive/10 hover:bg-destructive/15';
        case 'pending':
            return 'bg-amber-100/60 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/30';
        default:
            return undefined;
    }
}

export default PaymentStatusPill;
