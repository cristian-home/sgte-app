import { Link } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type DashboardOverdueInvoice = {
    id: number;
    invoice_number: string | null;
    total_value: string;
    customer_name: string;
    days_since_issue: number;
};

const copFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});

/**
 * Top-5 overdue invoices with customer + monto + days since issue.
 * `days_since_issue` is the urgency proxy (no due_at column on
 * invoices); the controller sorts oldest first so this list is
 * already ordered by urgency.
 */
export function OverdueInvoicesCard({
    invoices,
    className,
}: {
    invoices: DashboardOverdueInvoice[];
    className?: string;
}) {
    return (
        <Card className={className}>
            <CardHeader>
                <div className="flex items-center justify-between gap-2">
                    <CardTitle className="flex items-center gap-2 text-sm">
                        <Receipt
                            className="size-4 text-destructive"
                            aria-hidden
                        />
                        Facturas vencidas
                    </CardTitle>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/invoices?filter[payment_status]=overdue">
                            Ver todas →
                        </Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                {invoices.length === 0 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        Sin facturas vencidas. Todo al día.
                    </p>
                ) : (
                    <ul className="divide-y divide-border">
                        {invoices.map((invoice) => (
                            <li key={invoice.id}>
                                <Link
                                    href={`/invoices/${invoice.id}`}
                                    className="-mx-2 flex items-center justify-between gap-4 rounded-md px-2 py-3 text-sm transition-colors hover:bg-muted/50"
                                >
                                    <div className="flex min-w-0 flex-col">
                                        <span className="font-mono font-medium">
                                            {invoice.invoice_number ??
                                                `#${invoice.id}`}
                                        </span>
                                        <span className="truncate text-muted-foreground">
                                            {invoice.customer_name}
                                        </span>
                                    </div>
                                    <div className="flex flex-col items-end gap-1 text-right">
                                        <span className="font-mono font-semibold tabular-nums">
                                            {copFormatter.format(
                                                Number(invoice.total_value),
                                            )}
                                        </span>
                                        <Badge variant="destructive">
                                            {invoice.days_since_issue} día
                                            {invoice.days_since_issue === 1
                                                ? ''
                                                : 's'}
                                        </Badge>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
