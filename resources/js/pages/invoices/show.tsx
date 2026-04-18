import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    FileDown,
    FileText,
    ListPlus,
    Pencil,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import { Can } from '@/components/can';
import { PaymentStatusPill } from '@/components/invoices/payment-status-pill';
import ServicePickerDialog, {
    type ServicePickerRow,
} from '@/components/invoices/service-picker-dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Permission } from '@/enums/Permission';
import AppLayout from '@/layouts/app-layout';
import { dateFormatter, parseDueDate } from '@/lib/document-status';
import contracts from '@/routes/contracts';
import invoices from '@/routes/invoices';
import services from '@/routes/services';
import thirdParties from '@/routes/third-parties';

import type { BreadcrumbItem } from '@/types';
import type { DocumentType, Invoice, ThirdParty } from '@/types/models';

// Local shape — does NOT extend Invoice because the global Invoice
// type uses `relation?: T` (undefined-only) while the show payload
// returns `relation: T | null`. `Pick + & relations` keeps both
// compatible (matches drivers / vehicles / contracts show pattern).
type ShowInvoice = Pick<
    Invoice,
    | 'id'
    | 'invoice_number'
    | 'third_party_id'
    | 'total_value'
    | 'issue_date'
    | 'payment_status'
    | 'notes'
> & {
    services_count?: number;
    third_party?:
        | (Pick<
              ThirdParty,
              | 'id'
              | 'identification_number'
              | 'is_natural_person'
              | 'first_name'
              | 'first_lastname'
              | 'company_name'
              | 'is_customer'
              | 'is_provider'
          > & {
              document_type?: Pick<DocumentType, 'id' | 'code' | 'name'> | null;
          })
        | null;
};

interface RecentServiceRow {
    id: number;
    service_date: string;
    service_status: string;
    unit_value: string | null;
    quantity: number | null;
    vehicle?: { id: number; plate: string } | null;
    contract?: { id: number; contract_number: string } | null;
}

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});

function formatDate(date: string | null): string {
    const parsed = parseDueDate(date);
    if (parsed === null) {
        return '—';
    }
    return dateFormatter.format(parsed);
}

function customerName(tp: ShowInvoice['third_party']): string {
    if (!tp) return '—';
    if (tp.is_natural_person) {
        return (
            [tp.first_name, tp.first_lastname]
                .filter(Boolean)
                .join(' ')
                .trim() || '—'
        );
    }
    return tp.company_name ?? '—';
}

function serviceValue(service: RecentServiceRow): string {
    if (service.unit_value === null || service.quantity === null) {
        return '—';
    }
    const total = Number(service.unit_value) * service.quantity;
    if (Number.isNaN(total)) {
        return '—';
    }
    return currencyFormatter.format(total);
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <div className="font-medium">{children}</div>
        </div>
    );
}

export default function InvoicesShow({
    invoice,
    recentServices,
    computedTotal,
    candidateServices,
}: {
    invoice: ShowInvoice;
    recentServices: RecentServiceRow[];
    computedTotal: string;
    candidateServices: ServicePickerRow[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Facturas', href: invoices.index().url },
        { title: invoice.invoice_number, href: '#' },
    ];

    const tp = invoice.third_party ?? null;
    const totalValueFormatted = currencyFormatter.format(
        Number(invoice.total_value),
    );
    const isPending = invoice.payment_status === 'pending';
    const servicesCount = invoice.services_count ?? 0;
    const hasAttachedServices = servicesCount > 0;
    const isTotalStale =
        hasAttachedServices &&
        Number(invoice.total_value) !== Number(computedTotal);

    const [pickerOpen, setPickerOpen] = useState(false);
    const [detachingServiceId, setDetachingServiceId] = useState<number | null>(
        null,
    );

    function handleMarkPaid() {
        router.post(
            InvoiceController.markPaid(invoice.id).url,
            {},
            { preserveScroll: true },
        );
    }

    function handleRecompute() {
        router.post(
            InvoiceController.recomputeTotal(invoice.id).url,
            {},
            { preserveScroll: true },
        );
    }

    function handleDetachConfirm() {
        if (detachingServiceId === null) return;
        router.delete(
            InvoiceController.detachService([invoice.id, detachingServiceId])
                .url,
            {
                preserveScroll: true,
                onFinish: () => setDetachingServiceId(null),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={invoice.invoice_number} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header card */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <FileText className="size-8 text-muted-foreground" />
                                <div>
                                    <CardTitle className="font-mono text-2xl">
                                        {invoice.invoice_number}
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        {customerName(tp)}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <PaymentStatusPill invoice={invoice} />
                                <Can
                                    permission={
                                        Permission.ASSIGN_SERVICES_TO_INVOICES
                                    }
                                >
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setPickerOpen(true)}
                                    >
                                        <ListPlus className="mr-1 size-4" />
                                        Asignar Servicios
                                    </Button>
                                </Can>
                                <Can permission={Permission.VIEW_INVOICES}>
                                    <Button asChild size="sm" variant="outline">
                                        <a
                                            href={
                                                InvoiceController.pdf(
                                                    invoice.id,
                                                ).url
                                            }
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <FileDown className="mr-1 size-4" />
                                            Descargar PDF
                                        </a>
                                    </Button>
                                </Can>
                                <Button asChild size="sm" variant="outline">
                                    <Link href={invoices.edit(invoice.id).url}>
                                        <Pencil className="mr-1 size-4" />
                                        Editar
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Datos de la Factura */}
                <Card>
                    <CardHeader>
                        <CardTitle>Datos de la Factura</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-6 md:grid-cols-3">
                            <Field label="Número">
                                <span className="font-mono">
                                    {invoice.invoice_number}
                                </span>
                            </Field>
                            <Field label="Fecha de Emisión">
                                {formatDate(invoice.issue_date)}
                            </Field>
                            <div className="md:text-right">
                                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                    Valor Total
                                </p>
                                <p className="text-3xl font-bold tabular-nums">
                                    {totalValueFormatted}
                                </p>
                                {hasAttachedServices && (
                                    <p className="text-xs text-muted-foreground italic">
                                        (calculado automáticamente)
                                    </p>
                                )}
                                {hasAttachedServices && (
                                    <div className="mt-2 flex flex-wrap items-center gap-2 md:justify-end">
                                        {isTotalStale && (
                                            <Badge
                                                variant="outline"
                                                className="border-amber-500 text-amber-700 dark:text-amber-400"
                                            >
                                                Total desactualizado
                                            </Badge>
                                        )}
                                        <Can
                                            permission={
                                                Permission.ASSIGN_SERVICES_TO_INVOICES
                                            }
                                        >
                                            <Button
                                                size="sm"
                                                variant={
                                                    isTotalStale
                                                        ? 'default'
                                                        : 'ghost'
                                                }
                                                onClick={handleRecompute}
                                            >
                                                <RefreshCw className="mr-1 size-4" />
                                                Recalcular
                                            </Button>
                                        </Can>
                                    </div>
                                )}
                            </div>
                        </div>
                        <div className="mt-6 flex flex-wrap items-center justify-between gap-4 border-t pt-4">
                            <div className="flex items-center gap-2 text-sm">
                                <span className="text-muted-foreground">
                                    Estado:
                                </span>
                                <PaymentStatusPill invoice={invoice} />
                            </div>
                            {isPending && (
                                <Button
                                    size="sm"
                                    variant="default"
                                    onClick={handleMarkPaid}
                                >
                                    <CheckCircle2 className="mr-1 size-4" />
                                    Marcar como pagado
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Cliente */}
                <Card>
                    <CardHeader>
                        <CardTitle>Cliente</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tp ? (
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p className="font-medium">
                                        {customerName(tp)}
                                    </p>
                                    <p className="font-mono text-sm text-muted-foreground">
                                        {(tp.document_type?.code ?? '?') +
                                            ' ' +
                                            tp.identification_number}
                                    </p>
                                </div>
                                <Button asChild size="sm" variant="outline">
                                    <Link href={thirdParties.show(tp.id).url}>
                                        Ver tercero
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Sin cliente asociado.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Observaciones */}
                <Card>
                    <CardHeader>
                        <CardTitle>Observaciones</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {invoice.notes ? (
                            <p className="text-sm whitespace-pre-wrap">
                                {invoice.notes}
                            </p>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Sin observaciones.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Servicios Facturados */}
                <Card>
                    <CardHeader>
                        <CardTitle>Servicios Facturados (últimos 5)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentServices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Sin servicios facturados.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Contrato</TableHead>
                                        <TableHead>Vehículo</TableHead>
                                        <TableHead className="text-right">
                                            Valor
                                        </TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentServices.map((service) => (
                                        <TableRow key={service.id}>
                                            <TableCell>
                                                <Link
                                                    href={
                                                        services.show(
                                                            service.id,
                                                        ).url
                                                    }
                                                    className="text-primary hover:underline"
                                                >
                                                    {formatDate(
                                                        service.service_date,
                                                    )}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {service.contract ? (
                                                    <Link
                                                        href={
                                                            contracts.show(
                                                                service.contract
                                                                    .id,
                                                            ).url
                                                        }
                                                        className="font-mono text-sm text-primary hover:underline"
                                                    >
                                                        {
                                                            service.contract
                                                                .contract_number
                                                        }
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="font-mono">
                                                {service.vehicle?.plate ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {serviceValue(service)}
                                            </TableCell>
                                            <TableCell>
                                                <Can
                                                    permission={
                                                        Permission.ASSIGN_SERVICES_TO_INVOICES
                                                    }
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Desvincular"
                                                        onClick={() =>
                                                            setDetachingServiceId(
                                                                service.id,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-4 text-destructive" />
                                                    </Button>
                                                </Can>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <ServicePickerDialog
                open={pickerOpen}
                onOpenChange={setPickerOpen}
                invoiceId={invoice.id}
                candidates={candidateServices}
            />

            <AlertDialog
                open={detachingServiceId !== null}
                onOpenChange={(open) => {
                    if (!open) setDetachingServiceId(null);
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            ¿Desvincular servicio?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Esta acción quitará el servicio de la factura y
                            recalculará el valor total.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDetachConfirm}>
                            Confirmar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
